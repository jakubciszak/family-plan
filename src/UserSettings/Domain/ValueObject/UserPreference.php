<?php

declare(strict_types=1);

namespace App\UserSettings\Domain\ValueObject;

final readonly class UserPreference
{
    /**
     * @param PreferenceOption[] $options
     */
    private function __construct(
        private PreferenceType $type,
        private array $options
    ) {
    }

    /**
     * @param PreferenceOption[] $options
     */
    public static function create(PreferenceType $type, array $options): self
    {
        return new self($type, $options);
    }

    public function type(): PreferenceType
    {
        return $this->type;
    }

    /**
     * @return PreferenceOption[]
     */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * @return PreferenceOption[]
     */
    public function getEnabledOptions(): array
    {
        return array_values(array_filter(
            $this->options,
            fn(PreferenceOption $option) => $option->isEnabled()
        ));
    }

    public function isOptionEnabled(string $optionName): bool
    {
        foreach ($this->options as $option) {
            if ($option->name() === $optionName && $option->isEnabled()) {
                return true;
            }
        }
        return false;
    }

    public function updateOption(string $optionName, bool $enabled): self
    {
        $updatedOptions = array_map(
            fn(PreferenceOption $option) => 
                $option->name() === $optionName 
                    ? PreferenceOption::create($optionName, $enabled)
                    : $option,
            $this->options
        );

        return new self($this->type, $updatedOptions);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type->value(),
            'options' => array_map(
                fn(PreferenceOption $option) => $option->toArray(),
                $this->options
            ),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            PreferenceType::fromString($data['type']),
            array_map(
                fn(array $optionData) => PreferenceOption::fromArray($optionData),
                $data['options']
            )
        );
    }
}
