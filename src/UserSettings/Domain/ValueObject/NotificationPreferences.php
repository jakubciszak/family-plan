<?php

declare(strict_types=1);

namespace App\UserSettings\Domain\ValueObject;

final readonly class NotificationPreferences
{
    private function __construct(
        private bool $emailEnabled,
        private bool $smsEnabled
    ) {
    }

    public static function create(bool $emailEnabled, bool $smsEnabled): self
    {
        return new self($emailEnabled, $smsEnabled);
    }

    public static function default(): self
    {
        return new self(emailEnabled: true, smsEnabled: false);
    }

    public function isEmailEnabled(): bool
    {
        return $this->emailEnabled;
    }

    public function isSmsEnabled(): bool
    {
        return $this->smsEnabled;
    }

    public function getEnabledChannels(): array
    {
        $channels = [];
        
        if ($this->emailEnabled) {
            $channels[] = 'email';
        }
        
        if ($this->smsEnabled) {
            $channels[] = 'sms';
        }
        
        return $channels;
    }

    public function hasAnyChannelEnabled(): bool
    {
        return $this->emailEnabled || $this->smsEnabled;
    }

    public function toArray(): array
    {
        return [
            'email_enabled' => $this->emailEnabled,
            'sms_enabled' => $this->smsEnabled,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['email_enabled'] ?? true,
            $data['sms_enabled'] ?? false
        );
    }
}
