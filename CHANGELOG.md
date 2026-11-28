# Changelog

## Unreleased

### v0.1.13 (upcoming)

- Add isolated operator-only Manager domain reads and audited mutations for
  Accounts, Applications, environments, keys, provider records and first-party
  operators. Resource paths are finite and UUID-only; each mutation requires a
  request UUID. Preserve false, null and empty-list PATCH values.
- Require exact JSON success with 3-second connect / 10-second total limits,
  no redirects or retries, and safe failures that retain no response body.
  Callers must confirm identity/state and return generated secrets only once.


### v0.1.12 (upcoming)

- Add isolated operator Tenant metadata and Tenant Account interaction list/detail reads for console approval and Logs. UUIDs are validated before transport; reads require exact JSON success, bounded timeouts and no redirects/retries, with safe failures.

### v0.1.11 (upcoming)

- Add UUID-scoped Tenant Application environment list/show/create/update/archive
  and environment-key list/issue/retire methods for configurable deployments.
- New calls use isolated Tenant credentials, 3-second connection / 10-second
  overall timeouts, no redirects or retries, exact success statuses and strict
  JSON object confirmation. Failures never retain secret response bodies.
- Environment writes preserve explicit null destination clears, false values
  and empty URL lists. Existing Application methods and protected mutation
  signatures retain their previous behavior.
- Existing Application creation can pass the additive issue_initial_key=false
  payload through unchanged for an identity-only response. Consumers validate
  ownership, environment identity and resulting state before showing results.

### v0.1.10 (upcoming)

- Harden Tenant Application creation, key issuance/retirement and disable calls:
  require the expected success status and a nonempty JSON object, reject redirects,
  use 3-second connect / 10-second overall timeouts, and never retry automatically.
- Mutation failures expose only a generic reconciliation message and the HTTP
  status as the exception code (zero for connection failures), without response
  bodies, redirect targets or nested transport exceptions. Consumers must still
  validate resource ownership and resulting state.


- Add clone-based `withTenantApiKey()` for isolated per-Tenant requests without
  changing shared configuration or the original client.
- Allow optional pagination filters on `tenantAccountUsage()`.
- Add explicit `withFafflessApiKey()` and Faffless operator methods to list,
  issue and retire Tenant API keys. Operator requests never fall back to an
  Application or Tenant credential, and issuance is not retried.
- Require confirmed JSON mutation responses for operator key issuance/retirement,
  reject redirects and wrong ownership, and cap operator requests at 3 seconds
  connect / 10 seconds overall with no redirects or retries.
- Bound HTTP connection establishment to three seconds by default; the existing
  overall timeout remains configurable.
