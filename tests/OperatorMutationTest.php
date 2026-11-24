<?php

require dirname(__DIR__).'/vendor/autoload.php';

use Faffless\HubClient\Exceptions\FafflessHubException;
use Faffless\HubClient\FafflessHubClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

function config(string $key, mixed $default = null): mixed
{
    return ['faffless-hub.url' => 'https://hub.example.test', 'faffless-hub.timeout' => 30][$key] ?? $default;
}

$tenantUuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$keyUuid = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
$metadata = ['uuid' => $keyUuid, 'tenant_uuid' => $tenantUuid, 'status' => 'revoked'];
$cases = [
    [302, '', ['Location' => 'https://untrusted.example.test'], true],
    [307, '', ['Location' => 'https://untrusted.example.test'], true],
    [200, '<html>sign in</html>', ['Content-Type' => 'text/html'], true],
    [200, ['data' => [...$metadata, 'uuid' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc']], [], true],
    [200, ['data' => [...$metadata, 'tenant_uuid' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc']], [], true],
    [200, ['data' => [...$metadata, 'status' => 'active']], [], true],
    [200, ['data' => $metadata], [], false],
    [201, '<html>sign in</html>', ['Content-Type' => 'text/html'], false],
    [201, ['data' => [...$metadata, 'status' => 'active']], [], false],
    [302, '', ['Location' => 'https://untrusted.example.test'], false],
    [307, '', ['Location' => 'https://untrusted.example.test'], false],
];
foreach ($cases as [$status, $body, $headers, $retire]) {
    Http::swap(new Factory);
    $options = [];
    Http::fake(function ($request, array $requestOptions) use ($status, $body, $headers, &$options) {
        $options = $requestOptions;
        return Http::response($body, $status, $headers);
    });
    $client = (new FafflessHubClient)->withFafflessApiKey('operator-fixture');
    try {
        $retire ? $client->retireFafflessTenantApiKey($tenantUuid, $keyUuid)
            : $client->issueFafflessTenantApiKey($tenantUuid, ['name' => 'Fixture', 'abilities' => ['tenant:usage:read']]);
        throw new RuntimeException('An unconfirmed mutation was accepted.');
    } catch (FafflessHubException) {
        // Invalid transport success must never be treated as a confirmed mutation.
    }
    if (($options['allow_redirects'] ?? null) !== false || ($options['timeout'] ?? null) !== 10
        || ($options['connect_timeout'] ?? null) !== 3 || Http::recorded()->count() !== 1) {
        throw new RuntimeException('Operator timeout, redirect or no-retry boundary was lost.');
    }
}

echo "Operator mutation confirmation checks passed.\n";
