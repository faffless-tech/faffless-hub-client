# Changelog

## Unreleased

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
