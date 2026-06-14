<?php

namespace Faffless\HubClient\Responses;

class VerificationResponse
{
    public function __construct(
        public readonly ?string $uuid,
        public readonly ?string $interactionUuid,
        public readonly ?string $verificationId,
        public readonly ?string $channel,
        public readonly ?string $status,
        public readonly ?string $expiresAt,
        public readonly array $data,
    ) {}

    public static function from(array $data): self
    {
        $resource = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data;

        return new self(
            uuid: isset($resource['uuid']) ? (string) $resource['uuid'] : null,
            interactionUuid: isset($resource['interaction_uuid']) ? (string) $resource['interaction_uuid'] : null,
            verificationId: isset($resource['verification_id']) ? (string) $resource['verification_id'] : null,
            channel: isset($resource['channel']) ? (string) $resource['channel'] : null,
            status: isset($resource['status']) ? (string) $resource['status'] : null,
            expiresAt: isset($resource['expires_at']) ? (string) $resource['expires_at'] : null,
            data: $data,
        );
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
