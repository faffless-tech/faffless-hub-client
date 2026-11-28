<?php

namespace Faffless\HubClient;

use Faffless\HubClient\Exceptions\MissingCredentialsException;
use Faffless\HubClient\Exceptions\FafflessHubException;
use Faffless\HubClient\Responses\InteractionResponse;
use Faffless\HubClient\Responses\VerificationChannelsResponse;
use Faffless\HubClient\Responses\VerificationResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FafflessHubClient
{
    protected const APPLICATION_API_PREFIX = '/api/v1/application';

    protected const TENANT_API_PREFIX = '/api/v1/tenant';

    protected const INTERNAL_API_PREFIX = '/api/v1/internal';

    protected const FAFFLESS_API_PREFIX = '/api/v1/faffless';

    protected ?string $applicationApiKey = null;

    protected ?string $tenantApiKey = null;

    protected ?string $fafflessApiKey = null;

    public function withTenantApiKey(string $apiKey): self
    {
        $client = clone $this;
        $client->tenantApiKey = $apiKey;

        return $client;
    }

    public function withFafflessApiKey(string $apiKey): self
    {
        $client = clone $this;
        $client->fafflessApiKey = $apiKey;

        return $client;
    }

    public function fafflessTenantApiKeys(string $tenantUuid, array $filters = []): array
    {
        return $this->json($this->request(credential: 'faffless_api_key')
            ->get($this->url(self::FAFFLESS_API_PREFIX."/tenants/{$tenantUuid}/api-keys"), $this->clean($filters))->throw());
    }

    public function issueFafflessTenantApiKey(string $tenantUuid, array $payload): array
    {
        $response = $this->request(credential: 'faffless_api_key')
            ->post($this->url(self::FAFFLESS_API_PREFIX."/tenants/{$tenantUuid}/api-keys"), $payload);

        return $this->operatorKeyResponse($response, 201, $tenantUuid);
    }

    public function retireFafflessTenantApiKey(string $tenantUuid, string $apiKeyUuid): array
    {
        $response = $this->request(credential: 'faffless_api_key')
            ->post($this->url(self::FAFFLESS_API_PREFIX."/tenants/{$tenantUuid}/api-keys/{$apiKeyUuid}/retire"));

        return $this->operatorKeyResponse($response, 200, $tenantUuid, $apiKeyUuid);
    }

    protected function operatorKeyResponse(Response $response, int $status, string $tenantUuid, ?string $keyUuid = null): array
    {
        $payload = $response->json();
        $data = is_array($payload) ? ($payload['data'] ?? null) : null;
        if ($response->status() !== $status || ! str_contains(strtolower($response->header('Content-Type')), 'application/json')
            || ! is_array($data) || ! is_string($data['uuid'] ?? null) || ! Str::isUuid($data['uuid'])
            || ($data['tenant_uuid'] ?? null) !== $tenantUuid
            || ($keyUuid !== null && $data['uuid'] !== $keyUuid)
            || ($data['status'] ?? null) !== ($keyUuid === null ? 'active' : 'revoked')
            || ($keyUuid === null && (! is_string($payload['api_key'] ?? null) || strlen($payload['api_key']) < 20))) {
            throw new FafflessHubException('The Hub did not return a valid confirmed operator key mutation. Inspect key metadata before retrying.');
        }

        return $payload;
    }

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

    public function verificationChannels(string $recipient): VerificationChannelsResponse
    {
        return VerificationChannelsResponse::from($this->json(
            $this->get($this->applicationPath('verification/channels'), [
                'recipient' => $recipient,
            ])
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

    public function tenantAccountUsage(string $accountUuid, array $filters = []): array
    {
        return $this->json($this->tenantGet("accounts/{$accountUuid}/usage", $filters));
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
        return $this->tenantApplicationMutation("accounts/{$accountUuid}/applications", $payload, 201);
    }

    public function issueTenantAccountApplicationApiKey(string $accountUuid, string $applicationUuid, array $payload = []): array
    {
        return $this->tenantApplicationMutation("accounts/{$accountUuid}/applications/{$applicationUuid}/api-keys", $payload, 201);
    }

    public function retireTenantAccountApplicationApiKey(string $accountUuid, string $applicationUuid, string $apiKeyUuid): array
    {
        return $this->tenantApplicationMutation("accounts/{$accountUuid}/applications/{$applicationUuid}/api-keys/{$apiKeyUuid}/retire", [], 200);
    }

    public function disableTenantAccountApplication(string $accountUuid, string $applicationUuid): array
    {
        return $this->tenantApplicationMutation("accounts/{$accountUuid}/applications/{$applicationUuid}/disable", [], 200);
    }

    public function tenantApplicationEnvironments(string $accountUuid, string $applicationUuid, array $filters = []): array
    {
        return $this->tenantEnvironmentRead($this->environmentPath($accountUuid, $applicationUuid), $filters);
    }

    public function tenantApplicationEnvironment(string $accountUuid, string $applicationUuid, string $environmentUuid): array
    {
        return $this->tenantEnvironmentRead($this->environmentPath($accountUuid, $applicationUuid, $environmentUuid));
    }

    public function createTenantApplicationEnvironment(string $accountUuid, string $applicationUuid, array $payload): array
    {
        return $this->tenantEnvironmentMutation($this->environmentPath($accountUuid, $applicationUuid), $payload, 201);
    }

    public function updateTenantApplicationEnvironment(string $accountUuid, string $applicationUuid, string $environmentUuid, array $payload): array
    {
        return $this->tenantEnvironmentMutation($this->environmentPath($accountUuid, $applicationUuid, $environmentUuid), $payload, 200, 'PATCH');
    }

    public function archiveTenantApplicationEnvironment(string $accountUuid, string $applicationUuid, string $environmentUuid, array $payload = []): array
    {
        return $this->tenantEnvironmentMutation($this->environmentPath($accountUuid, $applicationUuid, $environmentUuid).'/archive', $payload, 200);
    }

    public function tenantApplicationEnvironmentApiKeys(string $accountUuid, string $applicationUuid, string $environmentUuid): array
    {
        return $this->tenantEnvironmentRead($this->environmentPath($accountUuid, $applicationUuid, $environmentUuid).'/api-keys');
    }

    public function issueTenantApplicationEnvironmentApiKey(string $accountUuid, string $applicationUuid, string $environmentUuid, array $payload): array
    {
        return $this->tenantEnvironmentMutation($this->environmentPath($accountUuid, $applicationUuid, $environmentUuid).'/api-keys', $payload, 201);
    }

    public function retireTenantApplicationEnvironmentApiKey(string $accountUuid, string $applicationUuid, string $environmentUuid, string $apiKeyUuid): array
    {
        if (! Str::isUuid($apiKeyUuid)) {
            throw new FafflessHubException('The Application key identifier must be a UUID.');
        }

        return $this->tenantEnvironmentMutation($this->environmentPath($accountUuid, $applicationUuid, $environmentUuid).'/api-keys/'.rawurlencode($apiKeyUuid).'/retire', [], 200);
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
        $apiKey = match ($credential) {
            'tenant_api_key' => $this->tenantApiKey ?? config('faffless-hub.tenant_api_key'),
            'faffless_api_key' => $this->fafflessApiKey,
            default => $this->applicationApiKey ?: config('faffless-hub.'.$credential),
        };

        if (! $apiKey) {
            throw MissingCredentialsException::make();
        }

        $request = Http::timeout((int) config('faffless-hub.timeout', 30))
            ->connectTimeout((int) config('faffless-hub.connect_timeout', 3))
            ->withToken((string) $apiKey)
            ->withHeaders(array_filter([
                'Idempotency-Key' => $idempotencyKey,
            ]));

        return $credential === 'faffless_api_key' ? $request->timeout(10)->withoutRedirecting() : $request;
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

    /**
     * Consumers must additionally validate returned resource identity and state.
     */
    protected function tenantApplicationMutation(string $path, array $payload, int $expectedStatus): array
    {
        try {
            $response = $this->request(credential: 'tenant_api_key')
                ->connectTimeout(3)->timeout(10)->withoutRedirecting()
                ->post($this->url($this->tenantPath($path)), $this->clean($payload));
        } catch (ConnectionException) {
            throw new FafflessHubException('The Hub Application mutation could not be confirmed. Reconcile its state before retrying.');
        }

        $contentType = strtolower(trim(explode(';', $response->header('Content-Type'))[0]));
        if ($response->status() !== $expectedStatus || $contentType !== 'application/json') {
            throw new FafflessHubException('The Hub did not return a confirmed Application mutation. Reconcile its state before retrying.', $response->status());
        }

        try {
            $object = json_decode($response->body(), flags: JSON_THROW_ON_ERROR);
            if (! $object instanceof \stdClass || get_object_vars($object) === []) {
                throw new \JsonException;
            }

            return json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new FafflessHubException('The Hub returned an invalid Application mutation response. Reconcile its state before retrying.', $response->status());
        }
    }

    protected function tenantEnvironmentMutation(string $path, array $payload, int $expectedStatus, string $method = 'POST'): array
    {
        try {
            $response = $this->request(credential: 'tenant_api_key')
                ->connectTimeout(3)->timeout(10)->withoutRedirecting()
                ->send($method, $this->url($this->tenantPath($path)), ['json' => $payload]);
        } catch (ConnectionException) {
            throw new FafflessHubException('The Hub Application mutation could not be confirmed. Reconcile its state before retrying.');
        }

        $contentType = strtolower(trim(explode(';', $response->header('Content-Type'))[0]));
        if ($response->status() !== $expectedStatus || $contentType !== 'application/json') {
            throw new FafflessHubException('The Hub did not return a confirmed Application mutation. Reconcile its state before retrying.', $response->status());
        }

        try {
            $object = json_decode($response->body(), flags: JSON_THROW_ON_ERROR);
            if (! $object instanceof \stdClass || get_object_vars($object) === []) {
                throw new \JsonException;
            }

            return json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new FafflessHubException('The Hub returned an invalid Application mutation response. Reconcile its state before retrying.', $response->status());
        }
    }

    protected function environmentPath(string $accountUuid, string $applicationUuid, ?string $environmentUuid = null): string
    {
        foreach ([$accountUuid, $applicationUuid, ...($environmentUuid === null ? [] : [$environmentUuid])] as $uuid) {
            if (! Str::isUuid($uuid)) {
                throw new FafflessHubException('Application environment identifiers must be UUIDs.');
            }
        }
        $path = 'accounts/'.rawurlencode($accountUuid).'/applications/'.rawurlencode($applicationUuid).'/environments';

        return $environmentUuid === null ? $path : $path.'/'.rawurlencode($environmentUuid);
    }

    protected function tenantEnvironmentRead(string $path, array $query = []): array
    {
        try {
            $response = $this->request(credential: 'tenant_api_key')
                ->connectTimeout(3)->timeout(10)->withoutRedirecting()
                ->get($this->url($this->tenantPath($path)), $this->clean($query));
        } catch (ConnectionException) {
            throw new FafflessHubException('The Hub Application environment could not be read.');
        }
        $contentType = strtolower(trim(explode(';', $response->header('Content-Type'))[0]));
        if ($response->status() !== 200 || $contentType !== 'application/json') {
            throw new FafflessHubException('The Hub did not return a confirmed Application environment response.', $response->status());
        }
        try {
            $object = json_decode($response->body(), flags: JSON_THROW_ON_ERROR);
            if (! $object instanceof \stdClass || get_object_vars($object) === []) {
                throw new \JsonException;
            }

            return json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new FafflessHubException('The Hub returned an invalid Application environment response.', $response->status());
        }
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
