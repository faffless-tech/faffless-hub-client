<?php

require dirname(__DIR__).'/vendor/autoload.php';

use Faffless\HubClient\Exceptions\MissingCredentialsException;
use Faffless\HubClient\FafflessHubClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

$configuration = [
    'faffless-hub.url' => 'https://hub.example.test',
    'faffless-hub.api_key' => 'application-fixture',
    'faffless-hub.tenant_api_key' => 'configured-tenant-fixture',
    'faffless-hub.timeout' => 10,
];

function config(string $key, mixed $default = null): mixed
{
    global $configuration;

    return $configuration[$key] ?? $default;
}

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

Http::swap(new Factory);
Http::preventStrayRequests();
Http::fake(['hub.example.test/*' => Http::response(['data' => []])]);

$base = new FafflessHubClient;
$alpha = $base->withTenantApiKey('tenant-alpha-fixture');
$beta = $base->withTenantApiKey('tenant-beta-fixture');
$alpha->tenantAccountReadiness('account-alpha');
$beta->tenantAccountUsage('account-beta');
$alpha->tenantAccountUsage('account-alpha');
$base->tenantAccountUsage('configured-account');
$base->get('/api/v1/application/interactions');
$operator = $base->withFafflessApiKey('operator-fixture');
$operator->issueFafflessTenantApiKey('tenant-alpha', [
    'name' => 'Account Manager',
    'abilities' => ['tenant:readiness:read'],
]);
$operator->fafflessTenantApiKeys('tenant-alpha');
$operator->retireFafflessTenantApiKey('tenant-alpha', 'key-alpha');
$beta->tenantAccountUsage('account-beta');

$recorded = Http::recorded();
$expected = [
    'tenant-alpha-fixture', 'tenant-beta-fixture', 'tenant-alpha-fixture',
    'configured-tenant-fixture', 'application-fixture', 'operator-fixture',
    'operator-fixture', 'operator-fixture', 'tenant-beta-fixture',
];
foreach ($expected as $index => $key) {
    $assert($recorded[$index][0]->hasHeader('Authorization', 'Bearer '.$key), 'Credential crossed request boundaries.');
}
$assert($recorded[5][0]->url() === 'https://hub.example.test/api/v1/faffless/tenants/tenant-alpha/api-keys', 'Operator issue path mismatch.');
$assert($recorded[5][0]['abilities'] === ['tenant:readiness:read'], 'Explicit abilities were not preserved.');
$assert($recorded[7][0]->url() === 'https://hub.example.test/api/v1/faffless/tenants/tenant-alpha/api-keys/key-alpha/retire', 'Operator retire path mismatch.');
$assert(config('faffless-hub.tenant_api_key') === 'configured-tenant-fixture', 'Shared configuration was mutated.');

foreach ([
    fn () => $base->withTenantApiKey('')->tenantAccountUsage('account-alpha'),
    fn () => $base->issueFafflessTenantApiKey('tenant-alpha', ['name' => 'Missing operator']),
    fn () => $base->withFafflessApiKey('')->fafflessTenantApiKeys('tenant-alpha'),
] as $request) {
    try {
        $request();
        throw new RuntimeException('Missing explicit credentials did not fail closed.');
    } catch (MissingCredentialsException) {
        // Expected: no HTTP request is sent.
    }
}
$assert(Http::recorded()->count() === count($expected), 'An unauthorized request reached the HTTP client.');

echo "Credential isolation behavior checks passed.\n";
