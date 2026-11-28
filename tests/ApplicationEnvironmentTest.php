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
function checkEnvironment(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
function environmentTransport(Closure $handler): void
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
$environment = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';
$prefix = '/api/v1/tenant/accounts/'.$account.'/applications/'.$application.'/environments';
$operations = [
    ['tenantApplicationEnvironments', [$account, $application, ['search' => 'Staging QA', 'page' => 2]], $prefix, 200, null, 'GET'],
    ['tenantApplicationEnvironment', [$account, $application, $environment], $prefix.'/'.$environment, 200, null, 'GET'],
    ['tenantApplicationEnvironmentApiKeys', [$account, $application, $environment], $prefix.'/'.$environment.'/api-keys', 200, null, 'GET'],
    ['createTenantApplicationEnvironment', [$account, $application, ['name' => 'QA', 'test_mode' => true]], $prefix, 201, ['name' => 'QA', 'test_mode' => true], 'POST'],
    ['updateTenantApplicationEnvironment', [$account, $application, $environment, ['name' => 'Renamed', 'test_email' => null, 'test_phone' => null, 'test_mode' => false, 'confirm_live' => true, 'urls' => []]], $prefix.'/'.$environment, 200, ['name' => 'Renamed', 'test_email' => null, 'test_phone' => null, 'test_mode' => false, 'confirm_live' => true, 'urls' => []], 'PATCH'],
    ['archiveTenantApplicationEnvironment', [$account, $application, $environment, ['revoke_keys' => true]], $prefix.'/'.$environment.'/archive', 200, ['revoke_keys' => true], 'POST'],
    ['issueTenantApplicationEnvironmentApiKey', [$account, $application, $environment, ['name' => 'Reader', 'abilities' => ['application:interactions:read'], 'expected_test_mode' => false, 'expected_key_count' => 0]], $prefix.'/'.$environment.'/api-keys', 201, ['name' => 'Reader', 'abilities' => ['application:interactions:read'], 'expected_test_mode' => false, 'expected_key_count' => 0], 'POST'],
    ['retireTenantApplicationEnvironmentApiKey', [$account, $application, $environment, $key], $prefix.'/'.$environment.'/api-keys/'.$key.'/retire', 200, [], 'POST'],
];
$secret = 'secret-response-body-or-redirect-target';
$validBody = json_encode(['data' => ['uuid' => $application], 'api_key' => $secret]);
$base = new FafflessHubClient;
$alpha = $base->withTenantApiKey('alpha-fixture');
$beta = $base->withTenantApiKey('beta-fixture');

foreach ($operations as [$method, $arguments, $path, $success, $payload, $verb]) {
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
        [404, $validBody, 'application/json', true],
        [409, $validBody, 'application/json', true],
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
        environmentTransport(function (RequestInterface $request, array $options) use (&$sent, $status, $body, $contentType, $secret) {
            $sent[] = [$request, $options];
            if ($status <= 0) {
                return Create::rejectionFor(new ConnectException(($status === -1 ? "cURL error 28: operation timed out " : "cURL error 7: connection failed ").$secret, $request));
            }

            return Create::promiseFor(new Response($status, ['Content-Type' => $contentType, 'Location' => 'https://redirect.example.test/'.$secret], $body));
        });
        try {
            $result = $alpha->{$method}(...$arguments);
            checkEnvironment(! $fails && $result === json_decode($validBody, true), 'Unconfirmed mutation accepted or confirmed response changed.');
        } catch (FafflessHubException $exception) {
            checkEnvironment($fails, 'Confirmed mutation rejected.');
            checkEnvironment($exception->getCode() === max(0, $status), 'Safe HTTP status was lost.');
            checkEnvironment($exception->getPrevious() === null && ! str_contains((string) $exception, $secret), 'Mutation exception leaked a body, redirect target or transport exception.');
        }
        checkEnvironment(count($sent) === 1, 'Mutation redirected or retried.');
        [$request, $options] = $sent[0];
        checkEnvironment($request->getMethod() === $verb && $request->getUri()->getPath() === $path, 'Environment path/method changed.');
        if ($verb === 'GET') {
            $query = []; parse_str($request->getUri()->getQuery(), $query);
            checkEnvironment($query === ($method === 'tenantApplicationEnvironments' ? ['search' => 'Staging QA', 'page' => '2'] : []), 'Environment pagination/search changed.');
        }
        checkEnvironment($request->getHeaderLine('Authorization') === 'Bearer alpha-fixture', 'Mutation used another credential.');
        checkEnvironment($verb === 'GET' ? (string) $request->getBody() === '' : json_decode((string) $request->getBody(), true) === $payload, 'Environment body lost null clears, false mode, count or archive confirmation.');
        checkEnvironment(($options['timeout'] ?? null) === 10 && ($options['connect_timeout'] ?? null) === 3, 'Mutation timeouts are not bounded.');
    }
}

$sent = [];
environmentTransport(function (RequestInterface $request, array $options) use (&$sent, $validBody) {
    $sent[] = [$request, $options];

    return Create::promiseFor(new Response(201, ['Content-Type' => 'application/json'], $validBody));
});
foreach ([$alpha, $beta, $base] as $client) {
    $client->createTenantApplicationEnvironment($account, $application, ['name' => 'Isolation']);
}
foreach (['alpha-fixture', 'beta-fixture', 'configured-tenant-fixture'] as $index => $credential) {
    checkEnvironment($sent[$index][0]->getHeaderLine('Authorization') === 'Bearer '.$credential, 'Tenant mutation credential isolation failed.');
}
checkEnvironment(config('faffless-hub.tenant_api_key') === 'configured-tenant-fixture', 'Mutation changed shared configuration.');
try {
    $base->withTenantApiKey('')->createTenantApplicationEnvironment($account, $application, ['name' => 'Forbidden']);
    throw new RuntimeException('Empty explicit Tenant credential did not fail closed.');
} catch (MissingCredentialsException) {
    checkEnvironment(count($sent) === 3, 'Unauthorized mutation reached transport.');
}

// Other Tenant calls retain their configurable timeout and permissive response contract.
$sent = [];
environmentTransport(function (RequestInterface $request, array $options) use (&$sent) {
    $sent[] = [$request, $options];

    return Create::promiseFor(new Response(200, ['Content-Type' => 'text/plain'], '[]'));
});
checkEnvironment($alpha->tenantAccountApplication($account, $application) === [], 'Tenant read behavior changed.');
checkEnvironment($alpha->createTenantAccountProviderOnboarding($account, ['providers' => []]) === [], 'Tenant onboarding behavior changed.');
foreach ($sent as [$request, $options]) {
    checkEnvironment($options['timeout'] === 99 && $options['connect_timeout'] === 77, 'Unrelated call timeouts changed.');
}

environmentTransport(function (RequestInterface $request, array $options) use ($application) {
    checkEnvironment(json_decode((string) $request->getBody(), true) === ['name' => 'Identity only', 'issue_initial_key' => false], 'Identity-only creation flag changed.');
    return Create::promiseFor(new Response(201, ['Content-Type' => 'application/json'], json_encode(['data' => ['uuid' => $application], 'api_key' => null, 'api_key_record' => null])));
});
$identity = $alpha->createTenantAccountApplication($account, ['name' => 'Identity only', 'issue_initial_key' => false]);
checkEnvironment($identity['api_key'] === null && $identity['api_key_record'] === null, 'Identity-only response changed.');

$before = count($sent);
foreach (['..', '../keys', '123', 'not-a-uuid'] as $invalid) {
    foreach ([[$invalid, $application, $environment], [$account, $invalid, $environment], [$account, $application, $invalid]] as $ids) {
        try { $alpha->tenantApplicationEnvironment(...$ids); throw new RuntimeException('Invalid environment identifier accepted.'); }
        catch (FafflessHubException) { checkEnvironment(count($sent) === $before, 'Invalid identifier reached transport.'); }
    }
    try { $alpha->retireTenantApplicationEnvironmentApiKey($account, $application, $environment, $invalid); throw new RuntimeException('Invalid key identifier accepted.'); }
    catch (FafflessHubException) { checkEnvironment(count($sent) === $before, 'Invalid key identifier reached transport.'); }
}
echo "Application environment transport checks passed.\n";
