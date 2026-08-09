<?php

$root = dirname(__DIR__);

$client = file_get_contents($root.'/src/FafflessHubClient.php');
$config = file_get_contents($root.'/config/faffless-hub.php');
$interactionResponse = file_get_contents($root.'/src/Responses/InteractionResponse.php');
$verificationResponse = file_get_contents($root.'/src/Responses/VerificationResponse.php');
$verificationChannelsResponse = file_get_contents($root.'/src/Responses/VerificationChannelsResponse.php');

$failures = [];

$assert = function (bool $condition, string $message) use (&$failures): void {
    if (! $condition) {
        $failures[] = $message;
    }
};

$assert(str_contains($client, "protected const APPLICATION_API_PREFIX = '/api/v1/application';"), 'Application API prefix is missing.');
$assert(str_contains($client, "protected const TENANT_API_PREFIX = '/api/v1/tenant';"), 'Tenant API prefix is missing.');
$assert(str_contains($client, "protected const INTERNAL_API_PREFIX = '/api/v1/internal';"), 'Internal API prefix is missing.');

foreach ([
    "'/api/v1/email'",
    "'/api/v1/sms'",
    "'/api/v1/whatsapp'",
    "'/api/v1/tracker/command'",
    "'/api/v1/verification'",
    "'/api/v1/applications'",
    "'/api/v1/provider-accounts'",
] as $legacyPath) {
    $assert(! str_contains($client, $legacyPath), "Legacy top-level path remains: {$legacyPath}");
}

foreach ([
    'app_id',
    'FAFFLESS_HUB_APP_ID',
    'FAFFLESS_HUB_APP_SECRET',
    'FAFFLESS_HUB_API_SECRET',
    'X-Faffless-Hub-Api-Secret',
    'X-Faffless-Hub-App-Id',
    'rotateApplicationSecret',
    'rotate-secret',
    'public readonly ?int',
    'int|string',
] as $legacyToken) {
    $assert(! str_contains($client.$config.$interactionResponse.$verificationResponse.$verificationChannelsResponse, $legacyToken), "Legacy token remains: {$legacyToken}");
}

$assert(str_contains($client, 'withToken((string) $apiKey)'), 'Client must authenticate with a single bearer API key.');
$assert(str_contains($client, 'withApplicationApiKey('), 'Client must support per-request Application API keys for platform bridges.');
$assert(str_contains($client, '$client = clone $this;'), 'Application API key override must clone the client singleton.');
$assert(str_contains($client, 'applicationPost('), 'Application POST helper is missing.');
$assert(str_contains($client, 'verificationChannels('), 'Verification channel discovery helper is missing.');
$assert(str_contains($config, 'tenant_api_key'), 'Tenant API key config is missing.');
$assert(str_contains($client, "credential: 'tenant_api_key'"), 'Tenant helpers must use tenant API key credential mode.');
$assert(str_contains($client, 'issueApplicationApiKey('), 'Internal API key issue helper is missing.');
$assert(str_contains($client, 'retireApplicationApiKey('), 'Internal API key retire helper is missing.');
$assert(str_contains($client, 'issueTenantAccountApplicationApiKey('), 'Tenant API key issue helper is missing.');
$assert(str_contains($client, 'retireTenantAccountApplicationApiKey('), 'Tenant API key retire helper is missing.');
$assert(str_contains($client, 'createTenantAccountProviderOnboarding('), 'Tenant provider onboarding helper is missing.');
$assert(str_contains($client, 'tenantAccountProviderOnboardings('), 'Tenant provider onboarding list helper is missing.');
$assert(str_contains($client, 'tenantAccountReadiness('), 'Tenant Account readiness helper is missing.');
$assert(str_contains($interactionResponse, 'interaction_uuid'), 'Interaction response does not read interaction_uuid.');
$assert(str_contains($verificationResponse, 'interaction_uuid'), 'Verification response does not read interaction_uuid.');
$assert(str_contains($interactionResponse, "\$data['data']"), 'Interaction response does not unwrap JSON Resource data.');
$assert(str_contains($verificationResponse, "\$data['data']"), 'Verification response does not unwrap JSON Resource data.');
$assert(str_contains($verificationChannelsResponse, "\$data['data']"), 'Verification channels response does not unwrap JSON Resource data.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);
    exit(1);
}

echo 'Faffless Hub client contract checks passed.'.PHP_EOL;
