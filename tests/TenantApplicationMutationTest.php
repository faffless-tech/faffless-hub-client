<?php

require dirname(__DIR__).'/vendor/autoload.php';

use Faffless\HubClient\Exceptions\FafflessHubException;
use Faffless\HubClient\Exceptions\MissingCredentialsException;
use Faffless\HubClient\FafflessHubClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;

$configuration = [
    'faffless-hub.url' => 'https://hub.example.test',
    'faffless-hub.api_key' => 'application-fixture',
    'faffless-hub.tenant_api_key' => 'configured-tenant-fixture',
    'faffless-hub.timeout' => 99,
    'faffless-hub.connect_timeout' => 77,
];
function config(string $key, mixed $default = null): mixed
{
    global $configuration;

    return $configuration[$key] ?? $default;
}
function checkMutation(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
function mutationTransport(Closure $handler): void
{
    Http::swap(new class($handler) extends Factory {
        public function __construct(private Closure $handler) { parent::__construct(); }
        protected function newPendingRequest()
        {
            return parent::newPendingRequest()->setClient(new Client(['handler' => HandlerStack::create($this->handler)]));
        }
    });
}

$account = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$application = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
$key = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
$prefix = '/api/v1/tenant/accounts/'.$account.'/applications';
$operations = [
    ['createTenantAccountApplication', [$account, ['name' => 'Test Application', 'test_mode' => true]], $prefix, 201, ['name' => 'Test Application', 'test_mode' => true]],
    ['issueTenantAccountApplicationApiKey', [$account, $application, ['name' => 'Test key', 'abilities' => ['application:interactions:read']]], $prefix.'/'.$application.'/api-keys', 201, ['name' => 'Test key', 'abilities' => ['application:interactions:read']]],
    ['retireTenantAccountApplicationApiKey', [$account, $application, $key], $prefix.'/'.$application.'/api-keys/'.$key.'/retire', 200, []],
    ['disableTenantAccountApplication', [$account, $application], $prefix.'/'.$application.'/disable', 200, []],
];
$secret = 'secret-response-body-or-redirect-target';
$validBody = json_encode(['data' => ['uuid' => $application], 'api_key' => $secret]);
$base = new FafflessHubClient;
$alpha = $base->withTenantApiKey('alpha-fixture');
$beta = $base->withTenantApiKey('beta-fixture');

foreach ($operations as [$method, $arguments, $path, $success, $payload]) {
    $cases = [
        [$success, $validBody, 'application/json; charset=UTF-8', false],
        [302, $secret, 'text/html', true],
        [307, $secret, 'application/json', true],
        [308, $secret, 'application/json', true],
        [$success === 201 ? 200 : 201, $validBody, 'application/json', true],
        [202, $validBody, 'application/json', true],
        [204, '', 'application/json', true],
        [$success, '<html>'.$secret.'</html>', 'text/html', true],
        [$success, '{"data":', 'application/json', true],
        [$success, '', 'application/json', true],
        [$success, '{}', 'application/json', true],
        [$success, 'null', 'application/json', true],
        [$success, '[]', 'application/json', true],
        [$success, '[{"data":{}}]', 'application/json', true],
        [$success, '"'.$secret.'"', 'application/json', true],
        [$success, 'true', 'application/json', true],
        [$success, $validBody, 'text/plain', true],
        [$success, $validBody, 'application/jsonp', true],
        [401, $validBody, 'application/json', true],
        [403, $validBody, 'application/json', true],
        [422, $validBody, 'application/json', true],
        [500, $validBody, 'application/json', true],
        [503, $validBody, 'application/json', true],
        [0, '', '', true],
        [-1, '', '', true],
    ];
    foreach ($cases as [$status, $body, $contentType, $fails]) {
        $sent = [];
        mutationTransport(function (RequestInterface $request, array $options) use (&$sent, $status, $body, $contentType, $secret) {
            $sent[] = [$request, $options];
            if ($status <= 0) {
                return Create::rejectionFor(new ConnectException(($status === -1 ? "cURL error 28: operation timed out " : "cURL error 7: connection failed ").$secret, $request));
            }

            return Create::promiseFor(new Response($status, ['Content-Type' => $contentType, 'Location' => 'https://redirect.example.test/'.$secret], $body));
        });
        try {
            $result = $alpha->{$method}(...$arguments);
            checkMutation(! $fails && $result === json_decode($validBody, true), 'Unconfirmed mutation accepted or confirmed response changed.');
        } catch (FafflessHubException $exception) {
            checkMutation($fails, 'Confirmed mutation rejected.');
            checkMutation($exception->getCode() === max(0, $status), 'Safe HTTP status was lost.');
            checkMutation($exception->getPrevious() === null && ! str_contains((string) $exception, $secret), 'Mutation exception leaked a body, redirect target or transport exception.');
        }
        checkMutation(count($sent) === 1, 'Mutation redirected or retried.');
        [$request, $options] = $sent[0];
        checkMutation($request->getMethod() === 'POST' && (string) $request->getUri() === 'https://hub.example.test'.$path, 'Mutation path/method changed.');
        checkMutation($request->getHeaderLine('Authorization') === 'Bearer alpha-fixture', 'Mutation used another credential.');
        checkMutation(json_decode((string) $request->getBody(), true) === $payload, 'Mutation payload changed.');
        checkMutation(($options['timeout'] ?? null) === 10 && ($options['connect_timeout'] ?? null) === 3, 'Mutation timeouts are not bounded.');
    }
}

$sent = [];
mutationTransport(function (RequestInterface $request, array $options) use (&$sent, $validBody) {
    $sent[] = [$request, $options];

    return Create::promiseFor(new Response(201, ['Content-Type' => 'application/json'], $validBody));
});
foreach ([$alpha, $beta, $base] as $client) {
    $client->createTenantAccountApplication($account, ['name' => 'Isolation']);
}
foreach (['alpha-fixture', 'beta-fixture', 'configured-tenant-fixture'] as $index => $credential) {
    checkMutation($sent[$index][0]->getHeaderLine('Authorization') === 'Bearer '.$credential, 'Tenant mutation credential isolation failed.');
}
checkMutation(config('faffless-hub.tenant_api_key') === 'configured-tenant-fixture', 'Mutation changed shared configuration.');
try {
    $base->withTenantApiKey('')->createTenantAccountApplication($account, ['name' => 'Forbidden']);
    throw new RuntimeException('Empty explicit Tenant credential did not fail closed.');
} catch (MissingCredentialsException) {
    checkMutation(count($sent) === 3, 'Unauthorized mutation reached transport.');
}

// Other Tenant calls retain their configurable timeout and permissive response contract.
$sent = [];
mutationTransport(function (RequestInterface $request, array $options) use (&$sent) {
    $sent[] = [$request, $options];

    return Create::promiseFor(new Response(200, ['Content-Type' => 'text/plain'], '[]'));
});
checkMutation($alpha->tenantAccountApplication($account, $application) === [], 'Tenant read behavior changed.');
checkMutation($alpha->createTenantAccountProviderOnboarding($account, ['providers' => []]) === [], 'Tenant onboarding behavior changed.');
foreach ($sent as [$request, $options]) {
    checkMutation($options['timeout'] === 99 && $options['connect_timeout'] === 77, 'Unrelated call timeouts changed.');
}

echo "Tenant Application mutation transport checks passed.\n";
