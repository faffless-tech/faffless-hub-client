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
function checkConsole(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
function consoleTransport(Closure $handler): void
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
$interaction = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
$operations = [
    ['fafflessTenant', [$account], '/api/v1/faffless/tenants/'.$account, 'operator-fixture', []],
    ['tenantAccountInteractions', [$account, ['page' => 2, 'state' => 'completed']], '/api/v1/tenant/accounts/'.$account.'/interactions', 'tenant-fixture', ['page' => '2', 'state' => 'completed']],
    ['tenantAccountInteraction', [$account, $interaction], '/api/v1/tenant/accounts/'.$account.'/interactions/'.$interaction, 'tenant-fixture', []],
];
$secret = 'private-body-or-transport-secret';
foreach ($operations as [$method, $arguments, $path, $credential, $expectedQuery]) {
    foreach ([[200,'{"data":[]}',false],[302,$secret,true],[404,$secret,true],[200,'[]',true],[200,'{}',true],[200,'invalid',true],[0,'',true]] as [$status,$body,$failure]) {
        $sent=[];
        consoleTransport(function (RequestInterface $request, array $options) use (&$sent,$status,$body,$secret) {
            $sent[]=[$request,$options];
            return $status === 0 ? Create::rejectionFor(new ConnectException($secret,$request))
                : Create::promiseFor(new Response($status,['Content-Type'=>'application/json','Location'=>'https://redirect.example.test/'.$secret],$body));
        });
        $client=(new FafflessHubClient)->withFafflessApiKey('operator-fixture')->withTenantApiKey('tenant-fixture');
        try {
            $result=$client->{$method}(...$arguments);
            checkConsole(!$failure && $result===['data'=>[]],'Invalid console response accepted.');
        } catch (FafflessHubException $exception) {
            checkConsole($failure && $exception->getCode()===$status && $exception->getPrevious()===null && !str_contains((string)$exception,$secret),'Unsafe console failure.');
        }
        checkConsole(count($sent)===1,'Read redirected or retried.');
        [$request,$options]=$sent[0];
        $query=[];parse_str($request->getUri()->getQuery(),$query);
        checkConsole($request->getMethod()==='GET' && $request->getUri()->getPath()===$path && $query===$expectedQuery,'Incorrect path/filter.');
        checkConsole($request->getHeaderLine('Authorization')==='Bearer '.$credential,'Wrong credential.');
        checkConsole($options['timeout']===10 && $options['connect_timeout']===3,'Incorrect timeouts.');
    }
}
foreach (['fafflessTenant','tenantAccountInteractions','tenantAccountInteraction'] as $method) {
    consoleTransport(fn()=>throw new RuntimeException('Invalid identifier reached transport.'));
    try { (new FafflessHubClient)->{$method}(...($method==='tenantAccountInteraction'?[$account,'123']:['123'])); throw new RuntimeException('Invalid UUID accepted.'); }
    catch(FafflessHubException) {}
}
echo "Console read transport and isolation checks passed.\n";
