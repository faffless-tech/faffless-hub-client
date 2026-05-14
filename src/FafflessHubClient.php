<?php

namespace Faffless\HubClient;

use Faffless\HubClient\Exceptions\MissingCredentialsException;
use Faffless\HubClient\Responses\InteractionResponse;
use Faffless\HubClient\Responses\VerificationResponse;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class FafflessHubClient
{
    public function get(string $path, array $query = []): Response
    {
        return $this->request()
            ->get($this->url($path), $this->clean($query))
            ->throw();
    }

    public function post(string $path, array $payload = [], ?string $idempotencyKey = null): Response
    {
        return $this->request($idempotencyKey)
            ->post($this->url($path), $this->clean($payload))
            ->throw();
    }

    public function patch(string $path, array $payload = []): Response
    {
        return $this->request()
            ->patch($this->url($path), $this->clean($payload))
            ->throw();
    }

    public function sendInteraction(string $path, array $payload = [], ?string $idempotencyKey = null): array
    {
        return $this->json($this->post($path, $payload, $idempotencyKey));
    }

    public function email(array $payload, ?string $idempotencyKey = null): InteractionResponse
    {
        return InteractionResponse::from($this->json(
            $this->post('/api/v1/email', $payload, $idempotencyKey)
        ));
    }

    public function sms(array $payload, ?string $idempotencyKey = null): InteractionResponse
    {
        return InteractionResponse::from($this->json(
            $this->post('/api/v1/sms', $payload, $idempotencyKey)
        ));
    }

    public function whatsapp(array $payload, ?string $idempotencyKey = null): InteractionResponse
    {
        return InteractionResponse::from($this->json(
            $this->post('/api/v1/whatsapp', $payload, $idempotencyKey)
        ));
    }

    public function trackerCommand(array $payload, ?string $idempotencyKey = null): InteractionResponse
    {
        return InteractionResponse::from($this->json(
            $this->post('/api/v1/tracker/command', $payload, $idempotencyKey)
        ));
    }

    public function startVerification(array $payload, ?string $idempotencyKey = null): VerificationResponse
    {
        return VerificationResponse::from($this->json(
            $this->post('/api/v1/verification', $payload, $idempotencyKey)
        ));
    }

    public function checkVerification(string $verificationId, string $code): array
    {
        return $this->json($this->post("/api/v1/verification/{$verificationId}/check", [
            'code' => $code,
        ]));
    }

    public function nextVerification(string $verificationId, array $payload = [], ?string $idempotencyKey = null): VerificationResponse
    {
        return VerificationResponse::from($this->json(
            $this->post("/api/v1/verification/{$verificationId}/next", $payload, $idempotencyKey)
        ));
    }

    public function interaction(int|string $interactionId): array
    {
        return $this->json($this->get("/api/v1/interactions/{$interactionId}"));
    }

    public function attempts(int|string $interactionId): array
    {
        return $this->json($this->get("/api/v1/interactions/{$interactionId}/attempts"))['attempts'] ?? [];
    }

    public function events(int|string $interactionId): array
    {
        return $this->json($this->get("/api/v1/interactions/{$interactionId}/events"))['events'] ?? [];
    }

    public function artifacts(int|string $interactionId): array
    {
        return $this->json($this->get("/api/v1/interactions/{$interactionId}/artifacts"))['artifacts'] ?? [];
    }

    public function applications(array $filters = []): array
    {
        return $this->json($this->get('/api/v1/applications', $filters));
    }

    public function application(int|string $applicationId): array
    {
        return $this->json($this->get("/api/v1/applications/{$applicationId}"));
    }

    public function createApplication(array $payload): array
    {
        return $this->json($this->post('/api/v1/applications', $payload));
    }

    public function updateApplication(int|string $applicationId, array $payload): array
    {
        return $this->json($this->patch("/api/v1/applications/{$applicationId}", $payload));
    }

    public function rotateApplicationSecret(int|string $applicationId): array
    {
        return $this->json($this->post("/api/v1/applications/{$applicationId}/rotate-secret"));
    }

    public function disableApplication(int|string $applicationId): array
    {
        return $this->json($this->post("/api/v1/applications/{$applicationId}/disable"));
    }

    public function providerAccounts(array $filters = []): array
    {
        return $this->json($this->get('/api/v1/provider-accounts', $filters));
    }

    public function providerAccount(int|string $providerAccountId): array
    {
        return $this->json($this->get("/api/v1/provider-accounts/{$providerAccountId}"));
    }

    protected function request(?string $idempotencyKey = null): PendingRequest
    {
        $appId = config('faffless-hub.app_id');
        $secret = config('faffless-hub.secret');

        if (! $appId || ! $secret) {
            throw MissingCredentialsException::make();
        }

        return Http::timeout((int) config('faffless-hub.timeout', 10))
            ->withHeaders(array_filter([
                'X-Faffless-Hub-App-Id' => $appId,
                'X-Faffless-Hub-Secret' => $secret,
                'Idempotency-Key' => $idempotencyKey,
            ]));
    }

    protected function url(string $path): string
    {
        return rtrim((string) config('faffless-hub.url'), '/').'/'.ltrim($path, '/');
    }

    protected function clean(array $payload): array
    {
        return array_filter($payload, fn ($value) => $value !== null && $value !== '');
    }

    protected function json(Response $response): array
    {
        $json = $response->json();

        return is_array($json) ? $json : [];
    }
}
