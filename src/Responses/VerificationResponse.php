<?php

namespace Faffless\HubClient\Responses;

class VerificationResponse
{
    public function __construct(
        public readonly ?int $interactionId,
        public readonly ?string $verificationId,
        public readonly ?string $channel,
        public readonly ?string $status,
        public readonly ?string $expiresAt,
        public readonly array $data,
    ) {}

    public static function from(array $data): self
    {
        return new self(
            interactionId: isset($data['interaction_id']) ? (int) $data['interaction_id'] : null,
            verificationId: isset($data['verification_id']) ? (string) $data['verification_id'] : null,
            channel: isset($data['channel']) ? (string) $data['channel'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            expiresAt: isset($data['expires_at']) ? (string) $data['expires_at'] : null,
            data: $data,
        );
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
