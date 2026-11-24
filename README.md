# Faffless Hub Client

Shared PHP client foundation for Faffless Hub integrations.

This package owns the common HTTP contract for domain packages such as:

- `faffless/communication-channel`
- `faffless/tracker-channel`
- `faffless/document-signing`
- `faffless/voice-ai`
- `faffless/surveys`

## Install

```bash
composer require faffless/hub-client
```

Publish config if needed:

```bash
php artisan vendor:publish --tag=faffless-hub-config
```

Configure credentials:

```dotenv
FAFFLESS_HUB_URL=https://communication.faffless.ai
FAFFLESS_HUB_API_KEY=hub_example
FAFFLESS_HUB_TENANT_API_KEY=tenant_key_for_tenant_automation
```

The client authenticates with a single API key using an `Authorization: Bearer` header. The old app-id/app-secret credential names are not supported.

## Usage

```php
use Faffless\HubClient\FafflessHubClient;

$hub = app(FafflessHubClient::class);

$response = $hub->email([
    'to' => 'customer@example.com',
    'subject' => 'Hire update',
    'text' => 'Your hire is ready.',
    'purpose' => 'hire_update',
]);

$interactionUuid = $response->interactionUuid;
$events = $hub->events($interactionUuid);
```

For an application serving multiple Tenants, use a per-instance credential:

```php
$tenantClient = $hub->withTenantApiKey($serverSideTenantKey);
$readiness = $tenantClient->tenantAccountReadiness($accountUuid);
$usage = $tenantClient->tenantAccountUsage($accountUuid, ['page' => 2, 'per_page' => 25]);
```

The original client and shared configuration stay unchanged. An explicitly empty
Tenant override fails closed. Keep each Tenant key server-side and derive its
Account UUID from an authorized Tenant context.

Trusted Faffless operators can use `withFafflessApiKey($operatorKey)` with
`fafflessTenantApiKeys($tenantUuid)`, `issueFafflessTenantApiKey($tenantUuid,
$payload)`, and `retireFafflessTenantApiKey($tenantUuid, $keyUuid)`. These calls
use the `/api/v1/faffless` surface and require the explicit operator credential;
they never fall back to runtime or Tenant credentials. Key issuance returns a
one-time secret: persist it securely, never log it, and inspect/retire orphaned
key metadata before repeating an ambiguous failed issue request.

Use the tenant automation API for managed accounts and applications:

```php
$accounts = $hub->tenantAccounts();
$account = $hub->createTenantAccount([
    'name' => 'Handy Rentals',
]);

$accountUuid = $account['data']['uuid'];

$onboarding = $hub->createTenantAccountProviderOnboarding($accountUuid, [
    'idempotency_key' => 'rpos:handy-rentals:communications:v1',
    'bundles' => ['sms', 'phone_verification'],
    'providers' => [
        'twilio' => [
            'mode' => 'import',
            'account_sid' => 'AC...',
            'api_key_sid' => 'SK...',
            'api_key_secret' => $secretFromProtectedStorage,
            'messaging_service_sid' => 'MG...',
            'verify_service_sid' => 'VA...',
        ],
    ],
]);

$readiness = $hub->tenantAccountReadiness($accountUuid);

$application = $hub->createTenantAccountApplication($accountUuid, [
    'name' => 'Rental PWA',
    'api_key_name' => 'Rental PWA production',
]);

$applicationUuid = $application['data']['uuid'];

$apiKey = $hub->issueTenantAccountApplicationApiKey(
    accountUuid: $accountUuid,
    applicationUuid: $applicationUuid,
    payload: ['name' => 'Second production key'],
);

$hub->retireTenantAccountApplicationApiKey(
    accountUuid: $accountUuid,
    applicationUuid: $applicationUuid,
    apiKeyUuid: $apiKey['data']['uuid'],
);
```

Use the temporary internal bridge only for first-party console/admin workflows
that still depend on `/api/v1/internal/...`:

```php
$applications = $hub->applications(['active' => true]);
$created = $hub->createApplication(['name' => 'Rental PWA']);
$apiKey = $hub->issueApplicationApiKey($created['application']['uuid'], [
    'name' => 'Console-issued key',
]);
$providerAccounts = $hub->providerAccounts(['channel' => 'email']);
$provider = $hub->createProviderAccount([
    'name' => 'Handy Rentals SendGrid',
    'provider' => 'sendgrid',
    'channel' => 'email',
    'secret_key' => 'api_key',
    'secret' => 'SG.secret',
]);
$service = $hub->createProviderService($provider['provider_account']['uuid'], [
    'name' => 'Transactional Email',
    'service_type' => 'email',
    'status' => 'active',
]);
```

Phone verification:

```php
$routes = $hub->verificationChannels('+64211234567');
// NZ example: call first, then WhatsApp when those routes are active.

$verification = $hub->startVerification([
    // Omit channel to use the primary route returned by Faffless.
    'recipient' => '+64211234567',
    'purpose' => 'login',
]);

$result = $hub->checkVerification(
    verificationId: $verification->verificationId,
    code: '123456',
);
```

Applications should render the ordered channels returned by
`verificationChannels()` rather than hard-coding country rules. Australia is
SMS-first when configured; New Zealand is voice-first and does not offer SMS by
default. `sms`, `call`, and `whatsapp` all use the same Faffless-owned challenge.
Use `nextVerification()` when the user selects another offered channel.

## Contract

Creation methods return typed response objects with the raw response in `toArray()`.

`interactionUuid` is the canonical lifecycle id for attempts, events, and artifacts.

Domain packages that need the raw hub response array can use `sendInteraction()`
with an application API relative path such as `email`, `sms`, or
`tracker/command`.

## Contributor checks and releases

After installing dependencies with `composer install`, run `composer ci:check`
before pushing. `composer check` is an alias for the same gate: strict package
metadata validation, dependency audit, the existing package lint and tests,
CycloneDX SBOM generation, and the blocking repository-owned Semgrep policy.
`composer security:semgrep` runs that same deterministic local policy; the
weekly community-rule audit remains a separate check.

These are package checks. Releases use reviewed semantic-version tags and
consumer dependency updates; there is no package UAT or Production deployment.
Existing released tags must never be changed or reused.
