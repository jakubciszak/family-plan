<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Adapter;

use App\Notifications\Domain\Port\NotificationPortInterface;
use App\Notifications\Domain\ValueObject\NotificationChannel;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Domain\ValueObject\Recipient;
use Psr\Log\LoggerInterface;

final readonly class SmsApiAdapter implements NotificationPortInterface
{
    public function __construct(
        private string $apiUrl,
        private string $apiToken,
        private ?LoggerInterface $logger = null
    ) {
    }

    public function send(
        Recipient $recipient,
        NotificationMessage $message,
        NotificationChannel $channel
    ): void {
        if (!$channel->isSms()) {
            throw new \InvalidArgumentException('SmsApiAdapter only supports SMS channel');
        }

        if (!$recipient->isPhoneNumber()) {
            throw new \InvalidArgumentException('Recipient must be a phone number for SMS channel');
        }

        if (empty($this->apiToken)) {
            $this->logger?->warning('SMS API token not configured, skipping SMS send', [
                'recipient' => $recipient->value(),
            ]);
            return;
        }

        try {
            $this->sendViaSmsApi($recipient->value(), $message->content());
            
            $this->logger?->info('SMS notification sent via SMSAPI.pl', [
                'recipient' => $recipient->value(),
                'content_length' => strlen($message->content()),
            ]);
        } catch (\Exception $e) {
            $this->logger?->error('Failed to send SMS notification', [
                'recipient' => $recipient->value(),
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to send SMS: ' . $e->getMessage(), 0, $e);
        }
    }

    public function supports(NotificationChannel $channel): bool
    {
        return $channel->isSms();
    }

    private function sendViaSmsApi(string $phoneNumber, string $message): void
    {
        $url = rtrim($this->apiUrl, '/') . '/sms.do';
        
        // Prepare request data according to SMSAPI.pl documentation
        $data = [
            'to' => $phoneNumber,
            'message' => $message,
            'format' => 'json',
        ];

        $ch = curl_init();
        
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize cURL. Please ensure the cURL extension is installed and enabled.');
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiToken,
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('cURL error: ' . $error);
        }

        if ($httpCode >= 400) {
            $errorMessage = 'HTTP error ' . $httpCode;
            if ($response) {
                $decoded = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['message'])) {
                    $errorMessage .= ': ' . $decoded['message'];
                }
            }
            throw new \RuntimeException($errorMessage);
        }

        // Verify response is valid JSON
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON response from SMS API');
        }

        // Check for API-level errors in the response
        // SMSAPI.pl may return 200 OK but with error details in the response body
        if (isset($decoded['error'])) {
            $errorMessage = 'SMS API error';
            if (isset($decoded['message'])) {
                $errorMessage .= ': ' . $decoded['message'];
            }
            throw new \RuntimeException($errorMessage);
        }
    }
}
