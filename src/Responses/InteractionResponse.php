<?php

namespace Faffless\HubClient\Responses;

class InteractionResponse
{
    public function __construct(
        public readonly ?int $interactionId,
        public readonly ?int $id,
        public readonly ?string $status,
        public readonly array $data,
    ) {}

    public static function from(array $data): self
    {
        return new self(
            interactionId: isset($data['interaction_id']) ? (int) $data['interaction_id'] : null,
            id: isset($data['id']) ? (int) $data['id'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            data: $data,
        );
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
