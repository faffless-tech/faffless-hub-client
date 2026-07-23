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
    protected const APPLICATION_API_PREFIX = '/api/v1/application';

    protected const TENANT_API_PREFIX = '/api/v1/tenant';

    protected const INTERNAL_API_PREFIX = '/api/v1/internal';

    protected ?string $applicationApiKey = null;

    public function withApplicationApiKey(string $apiKey): self
    {
        $client = clone $this;
        $client->applicationApiKey = $apiKey;

        return $client;
    }

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

    public function applicationPost(string $path, array $payload = [], ?string $idempotencyKey = null): Response
    {
        return $this->post($this->applicationPath($path), $payload, $idempotencyKey);
    }

    public function sendInteraction(string $path, array $payload = [], ?string $idempotencyKey = null): array
    {
        return $this->json($this->applicationPost($path, $payload, $idempotencyKey));
    }

    public function email(array $payload, ?string $idempotencyKey = null): InteractionResponse
    {
        return InteractionResponse::from($this->json(
            $this->post($this->applicationPath('email'), $payload, $idempotencyKey)
        ));
    }

    public function sms(array $payload, ?string $idempotencyKey = null): InteractionResponse
    {
        return InteractionResponse::from($this->json(
            $this->post($this->applicationPath('sms'), $payload, $idempotencyKey)
        ));
    }

    public function whatsapp(array $payload, ?string $idempotencyKey = null): InteractionResponse
    {
        return InteractionResponse::from($this->json(
            $this->post($this->applicationPath('whatsapp'), $payload, $idempotencyKey)
        ));
    }

    public function trackerCommand(array $payload, ?string $idempotencyKey = null): InteractionResponse
    {
        return InteractionResponse::from($this->json(
            $this->post($this->applicationPath('tracker/command'), $payload, $idempotencyKey)
        ));
    }

    public function startVerification(array $payload, ?string $idempotencyKey = null): VerificationResponse
    {
        return VerificationResponse::from($this->json(
            $this->post($this->applicationPath('verification'), $payload, $idempotencyKey)
        ));
    }

    public function checkVerification(string $verificationId, string $code): array
    {
        return $this->json($this->post($this->applicationPath("verification/{$verificationId}/check"), [
            'code' => $code,
        ]));
    }

    public function nextVerification(string $verificationId, array $payload = [], ?string $idempotencyKey = null): VerificationResponse
    {
        return VerificationResponse::from($this->json(
            $this->post($this->applicationPath("verification/{$verificationId}/next"), $payload, $idempotencyKey)
        ));
    }

    public function interactions(array $filters = []): array
    {
        return $this->json($this->get($this->applicationPath('interactions'), $filters));
    }

    public function interaction(string $interactionUuid): array
    {
        return $this->json($this->get($this->applicationPath("interactions/{$interactionUuid}")));
    }

    public function attempts(string $interactionUuid): array
    {
        return $this->json($this->get($this->applicationPath("interactions/{$interactionUuid}/attempts")))['data'] ?? [];
    }

    public function events(string $interactionUuid): array
    {
        return $this->json($this->get($this->applicationPath("interactions/{$interactionUuid}/events")))['data'] ?? [];
    }

    public function artifacts(string $interactionUuid): array
    {
        return $this->json($this->get($this->applicationPath("interactions/{$interactionUuid}/artifacts")))['data'] ?? [];
    }

    public function threads(array $filters = []): array
    {
        return $this->json($this->get($this->applicationPath('threads'), $filters));
    }

    public function thread(string $threadUuid): array
    {
        return $this->json($this->get($this->applicationPath("threads/{$threadUuid}")));
    }

    public function threadInteractions(string $threadUuid): array
    {
        return $this->json($this->get($this->applicationPath("threads/{$threadUuid}/interactions")));
    }

    public function tenantAccounts(array $filters = []): array
    {
        return $this->json($this->tenantGet('accounts', $filters));
    }

    public function tenantAccount(string $accountUuid): array
    {
        return $this->json($this->tenantGet("accounts/{$accountUuid}"));
    }

    public function createTenantAccount(array $payload): array
    {
        return $this->json($this->tenantPost('accounts', $payload));
    }

    public function updateTenantAccount(string $accountUuid, array $payload): array
    {
        return $this->json($this->tenantPatch("accounts/{$accountUuid}", $payload));
    }

    public function disableTenantAccount(string $accountUuid): array
    {
        return $this->json($this->tenantPost("accounts/{$accountUuid}/disable"));
    }

    public function tenantAccountUsage(string $accountUuid): array
    {
        return $this->json($this->tenantGet("accounts/{$accountUuid}/usage"));
    }

    public function tenantAccountWallets(string $accountUuid): array
    {
        return $this->json($this->tenantGet("accounts/{$accountUuid}/wallets"));
    }

    public function tenantAccountLedgerEntries(string $accountUuid): array
    {
        return $this->json($this->tenantGet("accounts/{$accountUuid}/ledger-entries"));
    }

    public function tenantAccountBillingSummary(string $accountUuid): array
    {
        return $this->json($this->tenantGet("accounts/{$accountUuid}/billing-summary"));
    }

    public function tenantAccountProviderOnboardings(string $accountUuid, array $filters = []): array
    {
        return $this->json($this->tenantGet("accounts/{$accountUuid}/provider-onboardings", $filters));
    }

    public function tenantAccountProviderOnboarding(string $accountUuid, string $onboardingUuid): array
    {
        return $this->json($this->tenantGet("accounts/{$accountUuid}/provider-onboardings/{$onboardingUuid}"));
    }

    public function createTenantAccountProviderOnboarding(string $accountUuid, array $payload): array
    {
        return $this->json($this->tenantPost("accounts/{$accountUuid}/provider-onboardings", $payload));
    }

    public function tenantAccountReadiness(string $accountUuid): array
    {
        return $this->json($this->tenantGet("accounts/{$accountUuid}/readiness"));
    }

    public function tenantAccountApplications(string $accountUuid, array $filters = []): array
    {
        return $this->json($this->tenantGet("accounts/{$accountUuid}/applications", $filters));
    }

    public function tenantAccountApplication(string $accountUuid, string $applicationUuid): array
    {
        return $this->json($this->tenantGet("accounts/{$accountUuid}/applications/{$applicationUuid}"));
    }

    public function createTenantAccountApplication(string $accountUuid, array $payload): array
    {
        return $this->json($this->tenantPost("accounts/{$accountUuid}/applications", $payload));
    }

    public function issueTenantAccountApplicationApiKey(string $accountUuid, string $applicationUuid, array $payload = []): array
    {
        return $this->json($this->tenantPost("accounts/{$accountUuid}/applications/{$applicationUuid}/api-keys", $payload));
    }

    public function retireTenantAccountApplicationApiKey(string $accountUuid, string $applicationUuid, string $apiKeyUuid): array
    {
        return $this->json($this->tenantPost("accounts/{$accountUuid}/applications/{$applicationUuid}/api-keys/{$apiKeyUuid}/retire"));
    }

    public function disableTenantAccountApplication(string $accountUuid, string $applicationUuid): array
    {
        return $this->json($this->tenantPost("accounts/{$accountUuid}/applications/{$applicationUuid}/disable"));
    }

    public function applications(array $filters = []): array
    {
        return $this->json($this->get($this->internalPath('applications'), $filters));
    }

    public function application(string $applicationUuid): array
    {
        return $this->json($this->get($this->internalPath("applications/{$applicationUuid}")));
    }

    public function createApplication(array $payload): array
    {
        return $this->json($this->post($this->internalPath('applications'), $payload));
    }

    public function updateApplication(string $applicationUuid, array $payload): array
    {
        return $this->json($this->patch($this->internalPath("applications/{$applicationUuid}"), $payload));
    }

    public function issueApplicationApiKey(string $applicationUuid, array $payload = []): array
    {
        return $this->json($this->post($this->internalPath("applications/{$applicationUuid}/api-keys"), $payload));
    }

    public function retireApplicationApiKey(string $applicationUuid, string $apiKeyUuid): array
    {
        return $this->json($this->post($this->internalPath("applications/{$applicationUuid}/api-keys/{$apiKeyUuid}/retire")));
    }

    public function disableApplication(string $applicationUuid): array
    {
        return $this->json($this->post($this->internalPath("applications/{$applicationUuid}/disable")));
    }

    public function providerAccounts(array $filters = []): array
    {
        return $this->json($this->get($this->internalPath('provider-accounts'), $filters));
    }

    public function providerAccount(string $providerAccountUuid): array
    {
        return $this->json($this->get($this->internalPath("provider-accounts/{$providerAccountUuid}")));
    }

    public function createProviderAccount(array $payload): array
    {
        return $this->json($this->post($this->internalPath('provider-accounts'), $payload));
    }

    public function updateProviderAccount(string $providerAccountUuid, array $payload): array
    {
        return $this->json($this->patch($this->internalPath("provider-accounts/{$providerAccountUuid}"), $payload));
    }

    public function updateProviderAccountSecret(string $providerAccountUuid, array $payload): array
    {
        return $this->json($this->post($this->internalPath("provider-accounts/{$providerAccountUuid}/secrets"), $payload));
    }

    public function disableProviderAccount(string $providerAccountUuid): array
    {
        return $this->json($this->post($this->internalPath("provider-accounts/{$providerAccountUuid}/disable")));
    }

    public function createProviderService(string $providerAccountUuid, array $payload): array
    {
        return $this->json($this->post($this->internalPath("provider-accounts/{$providerAccountUuid}/services"), $payload));
    }

    public function updateProviderService(string $providerServiceUuid, array $payload): array
    {
        return $this->json($this->patch($this->internalPath("provider-services/{$providerServiceUuid}"), $payload));
    }

    public function disableProviderService(string $providerServiceUuid): array
    {
        return $this->json($this->post($this->internalPath("provider-services/{$providerServiceUuid}/disable")));
    }

    protected function request(?string $idempotencyKey = null, string $credential = 'api_key'): PendingRequest
    {
        $apiKey = $credential === 'api_key' && $this->applicationApiKey
            ? $this->applicationApiKey
            : config('faffless-hub.'.$credential);

        if (! $apiKey) {
            throw MissingCredentialsException::make();
        }

        return Http::timeout((int) config('faffless-hub.timeout', 10))
            ->withToken((string) $apiKey)
            ->withHeaders(array_filter([
                'Idempotency-Key' => $idempotencyKey,
            ]));
    }

    protected function tenantGet(string $path, array $query = []): Response
    {
        return $this->request(credential: 'tenant_api_key')
            ->get($this->url($this->tenantPath($path)), $this->clean($query))
            ->throw();
    }

    protected function tenantPost(string $path, array $payload = [], ?string $idempotencyKey = null): Response
    {
        return $this->request($idempotencyKey, 'tenant_api_key')
            ->post($this->url($this->tenantPath($path)), $this->clean($payload))
            ->throw();
    }

    protected function tenantPatch(string $path, array $payload = []): Response
    {
        return $this->request(credential: 'tenant_api_key')
            ->patch($this->url($this->tenantPath($path)), $this->clean($payload))
            ->throw();
    }

    protected function url(string $path): string
    {
        return rtrim((string) config('faffless-hub.url'), '/').'/'.ltrim($path, '/');
    }

    protected function applicationPath(string $path): string
    {
        return self::APPLICATION_API_PREFIX.'/'.ltrim($path, '/');
    }

    protected function tenantPath(string $path): string
    {
        return self::TENANT_API_PREFIX.'/'.ltrim($path, '/');
    }

    protected function internalPath(string $path): string
    {
        return self::INTERNAL_API_PREFIX.'/'.ltrim($path, '/');
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
