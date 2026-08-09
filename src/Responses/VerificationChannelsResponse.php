<?php

namespace Faffless\HubClient\Responses;

class VerificationChannelsResponse
{
    public function __construct(
        public readonly ?string $country,
        public readonly ?string $primary,
        public readonly array $channels,
        public readonly array $data,
    ) {}

    public static function from(array $data): self
    {
        $resource = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data;

        return new self(
            country: isset($resource['country']) ? (string) $resource['country'] : null,
            primary: isset($resource['primary']) ? (string) $resource['primary'] : null,
            channels: is_array($resource['channels'] ?? null) ? $resource['channels'] : [],
            data: $data,
        );
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
