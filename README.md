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
FAFFLESS_HUB_APP_ID=hub_example
FAFFLESS_HUB_APP_SECRET=secret-from-hub
```

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

$interactionId = $response->interactionId;
$events = $hub->events($interactionId);
```

Read management data:

```php
$applications = $hub->applications(['active' => true]);
$created = $hub->createApplication(['name' => 'Rental PWA']);
$secret = $created['secret'];
$providerAccounts = $hub->providerAccounts(['channel' => 'email']);
$provider = $hub->createProviderAccount([
    'name' => 'Handy Rentals SendGrid',
    'provider' => 'sendgrid',
    'channel' => 'email',
    'secret_key' => 'api_key',
    'secret' => 'SG.secret',
]);
$service = $hub->createProviderService($provider['provider_account']['id'], [
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

`interactionId` is the canonical lifecycle id for attempts, events, and artifacts.

Domain packages that need the raw hub response array can use `sendInteraction()`.
