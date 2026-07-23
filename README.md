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
$verification = $hub->startVerification([
    'channel' => 'sms', // or 'call' to start with a voice call
    'recipient' => '+15551234567',
    'purpose' => 'login',
]);

$result = $hub->checkVerification(
    verificationId: $verification->verificationId,
    code: '123456',
);
```

Use `channel: call` when the consuming app should start with voice verification instead of the normal SMS-first flow. Use `nextVerification()` to trigger fallback steps such as SMS to call escalation.

## Contract

Creation methods return typed response objects with the raw response in `toArray()`.

`interactionUuid` is the canonical lifecycle id for attempts, events, and artifacts.

Domain packages that need the raw hub response array can use `sendInteraction()`
with an application API relative path such as `email`, `sms`, or
`tracker/command`.
