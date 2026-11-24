# Changelog

## Unreleased

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
