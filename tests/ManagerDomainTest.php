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

function config(string $key, mixed $default = null): mixed
{
    return ['faffless-hub.url' => 'https://hub.example.test', 'faffless-hub.api_key' => 'application-secret',
        'faffless-hub.tenant_api_key' => 'tenant-secret', 'faffless-hub.connect_timeout' => 98, 'faffless-hub.timeout' => 99][$key] ?? $default;
}
function verifyManager(bool $ok, string $message): void
{
    if (! $ok) { throw new RuntimeException($message); }
}
function managerTransport(Closure $handler): void
{
    Http::swap(new class($handler) extends Factory {
        public function __construct(private Closure $handler) { parent::__construct(); }
        protected function newPendingRequest()
        {
            return parent::newPendingRequest()->setClient(new Client(['handler' => HandlerStack::create($this->handler)]));
        }
    });
}
$uuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$parent = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
$requestUuid = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
$payload = ['request_uuid' => $requestUuid, 'test_email' => null, 'test_mode' => false, 'urls' => []];
$operations = [
    ['managerResources', ['providers'], 'GET', 'providers', 200, null],
    ['managerResource', ['applications', $uuid], 'GET', 'applications/'.$uuid, 200, null],
    ['managerResources', ['application-keys', $parent], 'GET', 'applications/'.$parent.'/api-keys', 200, null],
    ['managerResources', ['operator-keys', $parent], 'GET', 'operator-applications/'.$parent.'/api-keys', 200, null],
    ['createManagerResource', ['providers', $payload], 'POST', 'providers', 201, $payload],
    ['updateManagerResource', ['application-environments', $uuid, $payload, $parent], 'PATCH', 'applications/'.$parent.'/environments/'.$uuid, 200, $payload],
    ['deleteManagerResource', ['provider-services', $uuid, $requestUuid], 'DELETE', 'provider-services/'.$uuid, 200, ['request_uuid' => $requestUuid, 'confirmation' => true]],
    ['managerResourceAction', ['accounts', $uuid, 'activate', $payload], 'POST', 'accounts/'.$uuid.'/activate', 200, $payload],
    ['managerResourceAction', ['tenant-keys', $uuid, 'retire', $payload, $parent], 'POST', 'tenants/'.$parent.'/api-keys/'.$uuid.'/retire', 200, $payload],
    ['managerResourceAction', ['provider-accounts', $uuid, 'secrets', ['request_uuid' => $requestUuid, 'generate' => true]], 'POST', 'provider-accounts/'.$uuid.'/secrets', 201, ['request_uuid' => $requestUuid, 'generate' => true]],
];
$operator = (new FafflessHubClient)->withFafflessApiKey('operator-secret');
$secret = 'private-body-and-transport-data';
foreach ($operations as [$method, $args, $verb, $path, $success, $expectedPayload]) {
    foreach ([[$success, '{"data":{"uuid":"'.$uuid.'"}}', false], [302, $secret, true], [409, $secret, true], [200, '[]', true], [200, '{}', true], [200, 'invalid', true], [0, '', true]] as [$status, $body, $failure]) {
        $sent = [];
        managerTransport(function (RequestInterface $request, array $options) use (&$sent, $status, $body, $secret) {
            $sent[] = [$request, $options];
            return $status === 0 ? Create::rejectionFor(new ConnectException($secret, $request))
                : Create::promiseFor(new Response($status, ['Content-Type' => 'application/json'], $body));
        });
        try {
            $operator->$method(...$args);
            verifyManager(! $failure, 'An unconfirmed Manager request was accepted.');
        } catch (FafflessHubException $error) {
            verifyManager($failure, 'A valid Manager request failed.');
            verifyManager(! str_contains((string) $error, $secret) && $error->getPrevious() === null, 'A failure retained private response data.');
        }
        verifyManager(count($sent) === 1, 'Manager request retried.');
        [$request, $options] = $sent[0];
        verifyManager($request->getMethod() === $verb && $request->getUri()->getPath() === '/api/v1/faffless/manager/'.$path, 'Wrong Manager route or method.');
        verifyManager($request->getHeaderLine('Authorization') === 'Bearer operator-secret', 'Credential boundary lost.');
        verifyManager(($options['connect_timeout'] ?? null) === 3 && ($options['timeout'] ?? null) === 10 && ($options['allow_redirects'] ?? null) === false, 'Transport safeguards lost.');
        if ($expectedPayload !== null) {
            verifyManager(json_decode((string) $request->getBody(), true) === $expectedPayload, 'Explicit null/false/empty mutation values were lost.');
        }
    }
}
$bad = [
    ['managerResources', ['../../billing']], ['managerResource', ['accounts', '123']],
    ['managerResources', ['application-keys']], ['managerResources', ['application-keys', '../bad']],
    ['managerResources', ['accounts', $parent]], ['deleteManagerResource', ['accounts', $uuid, $requestUuid]],
    ['managerResourceAction', ['applications', $uuid, 'charge', $payload]],
    ['createManagerResource', ['providers', []]],
];
foreach ($bad as [$method, $args]) {
    $sent = 0;
    managerTransport(function () use (&$sent) { $sent++; return Create::promiseFor(new Response(200)); });
    try {
        $operator->$method(...$args);
        throw new RuntimeException('Invalid Manager identifiers were accepted.');
    } catch (FafflessHubException) {}
    verifyManager($sent === 0, 'Invalid Manager input reached transport.');
}
foreach ([new FafflessHubClient, (new FafflessHubClient)->withTenantApiKey('tenant-only'), (new FafflessHubClient)->withApplicationApiKey('app-only')] as $client) {
    try {
        $client->managerResources('providers');
        throw new RuntimeException('An operator call fell back to another credential.');
    } catch (MissingCredentialsException) {}
}
echo "Manager domain contract checks passed.\n";
