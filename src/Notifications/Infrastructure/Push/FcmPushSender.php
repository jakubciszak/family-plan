<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Push;

use App\Notifications\Domain\Entity\NativePushDevice;
use App\Notifications\Domain\Port\NativePushSenderInterface;
use App\Notifications\Domain\Port\PushDelivery;
use App\Notifications\Domain\ValueObject\DeliveryParameters;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Domain\ValueObject\PushOptions;
use App\Shared\Domain\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Firebase Cloud Messaging HTTP v1: the only way to reach an Android phone while the app is closed.
 *
 * The service account JSON (raw or base64) comes from FCM_SERVICE_ACCOUNT; without it the sender stays off.
 */
final class FcmPushSender implements NativePushSenderInterface
{
    /** Android channel the mobile app creates; it has to exist there for the notification to pop up. */
    public const ANDROID_CHANNEL = 'family-plan';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const TOKEN_URI = 'https://oauth2.googleapis.com/token';

    /** @var array{project_id: string, client_email: string, private_key: string, token_uri?: string}|false|null */
    private array|false|null $account = null;

    private ?string $accessToken = null;

    private int $accessTokenExpiresAt = 0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ClockInterface $clock,
        private readonly string $serviceAccount,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->account() !== null;
    }

    public function send(NativePushDevice $device, NotificationMessage $message, PushOptions $options): PushDelivery
    {
        $account = $this->account();

        if ($account === null) {
            $this->logger?->info('Phone push is switched off — no FCM service account configured');

            return PushDelivery::Failed;
        }

        try {
            $response = $this->httpClient->request(
                'POST',
                sprintf('https://fcm.googleapis.com/v1/projects/%s/messages:send', rawurlencode($account['project_id'])),
                [
                    'auth_bearer' => $this->accessToken($account),
                    'json' => ['message' => $this->message($device, $message, $options)],
                ]
            );

            $status = $response->getStatusCode();

            if ($status === 200) {
                return PushDelivery::Delivered;
            }

            $error = $response->toArray(false)['error'] ?? [];
        } catch (\Throwable $exception) {
            $this->logger?->error('Phone push could not be sent', ['error' => $exception->getMessage()]);

            return PushDelivery::Failed;
        }

        if ($status === 401) {
            $this->accessToken = null;
        }

        if ($this->isGone($status, $error)) {
            return PushDelivery::Gone;
        }

        $this->logger?->warning('FCM rejected a notification', [
            'status' => $status,
            'reason' => is_array($error) ? ($error['message'] ?? null) : null,
        ]);

        return PushDelivery::Failed;
    }

    private function message(NativePushDevice $device, NotificationMessage $message, PushOptions $options): array
    {
        $parameters = $message->additionalParameters();
        $notificationId = DeliveryParameters::notificationId($parameters)?->value();
        $tag = $options->tag ?? $notificationId;

        // FCM data values have to be strings.
        $data = array_filter([
            'url' => is_string($parameters['url'] ?? null) ? $parameters['url'] : '/',
            'tag' => $tag,
            'notificationId' => $notificationId,
            'event' => is_string($parameters['event'] ?? null) ? $parameters['event'] : null,
        ], static fn (?string $value) => $value !== null);

        $android = [
            // A notification somebody will see: high priority gets through Doze right away.
            'priority' => 'HIGH',
            'ttl' => $options->ttl . 's',
            'notification' => array_filter([
                'channel_id' => self::ANDROID_CHANNEL,
                // Same tag, same place in the tray: "approved" replaces "waiting for approval".
                'tag' => $tag,
            ]),
        ];

        if ($options->topic() !== null) {
            $android['collapse_key'] = $options->topic();
        }

        return [
            'token' => $device->token(),
            'notification' => [
                'title' => $message->subject() ?? 'Family Plan',
                'body' => $message->content(),
            ],
            'data' => $data,
            'android' => $android,
        ];
    }

    private function isGone(int $status, mixed $error): bool
    {
        if ($status === 404) {
            return true;
        }

        if (!is_array($error)) {
            return false;
        }

        foreach ($error['details'] ?? [] as $detail) {
            if (is_array($detail) && ($detail['errorCode'] ?? null) === 'UNREGISTERED') {
                return true;
            }
        }

        // INVALID_ARGUMENT also covers a malformed message, so only a token FCM does not recognise counts.
        return $status === 400
            && ($error['status'] ?? null) === 'INVALID_ARGUMENT'
            && str_contains(strtolower((string) ($error['message'] ?? '')), 'registration token');
    }

    /**
     * @param array{project_id: string, client_email: string, private_key: string, token_uri?: string} $account
     */
    private function accessToken(array $account): string
    {
        $now = $this->clock->now()->getTimestamp();

        if ($this->accessToken !== null && $now < $this->accessTokenExpiresAt - 60) {
            return $this->accessToken;
        }

        $tokenUri = $account['token_uri'] ?? self::TOKEN_URI;
        $token = $this->httpClient->request('POST', $tokenUri, [
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->assertion($account, $tokenUri, $now),
            ],
        ])->toArray();

        $this->accessToken = (string) $token['access_token'];
        $this->accessTokenExpiresAt = $now + (int) ($token['expires_in'] ?? 3600);

        return $this->accessToken;
    }

    /**
     * @param array{client_email: string, private_key: string} $account
     */
    private function assertion(array $account, string $tokenUri, int $now): string
    {
        $segments = [
            self::base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR)),
            self::base64Url(json_encode([
                'iss' => $account['client_email'],
                'scope' => self::SCOPE,
                'aud' => $tokenUri,
                'iat' => $now,
                'exp' => $now + 3600,
            ], JSON_THROW_ON_ERROR)),
        ];

        $key = openssl_pkey_get_private($account['private_key']);

        if ($key === false || !openssl_sign(implode('.', $segments), $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('The private key of the FCM service account cannot sign');
        }

        $segments[] = self::base64Url($signature);

        return implode('.', $segments);
    }

    /**
     * @return array{project_id: string, client_email: string, private_key: string, token_uri?: string}|null
     */
    private function account(): ?array
    {
        if ($this->account === null) {
            $this->account = self::parse($this->serviceAccount) ?? false;

            if ($this->account === false && trim($this->serviceAccount) !== '') {
                $this->logger?->error('FCM_SERVICE_ACCOUNT is not a service account JSON with project_id, client_email and private_key');
            }
        }

        return $this->account === false ? null : $this->account;
    }

    private static function parse(string $value): ?array
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $json = str_starts_with($value, '{') ? $value : base64_decode($value, true);
        $account = is_string($json) ? json_decode($json, true) : null;

        foreach (['project_id', 'client_email', 'private_key'] as $field) {
            if (!is_array($account) || !is_string($account[$field] ?? null) || $account[$field] === '') {
                return null;
            }
        }

        return $account;
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
