<?php

namespace Faffless\HubClient\Responses;

class InteractionResponse
{
    public function __construct(
        public readonly ?string $interactionUuid,
        public readonly ?string $status,
        public readonly array $data,
    ) {}

    public static function from(array $data): self
    {
        $resource = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data;

        return new self(
            interactionUuid: isset($resource['interaction_uuid']) ? (string) $resource['interaction_uuid'] : null,
            status: isset($resource['status']) ? (string) $resource['status'] : null,
            data: $data,
        );
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
