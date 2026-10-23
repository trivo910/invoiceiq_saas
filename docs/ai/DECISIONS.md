# Architecture decision log

| Date | Decision | Reason | Alternatives rejected / status |
|---|---|---|---|
| 2026-10-23 | Keep Phase 0 documentation-only; implement Phase 1 only after user says to continue | User explicitly requires one phase per response and approval before Phase 1 | Starting schema/code changes in Phase 0 was out of scope; the user later approved by directing continuation |
| 2026-10-23 | Treat static migrations as current schema evidence, and label production state unknown | Repository scan cannot reveal deployed drift or live records | Claiming live schema/data based only on migrations would be unsafe |
| 2026-10-23 | Document shared-schema, single-database tenant architecture as target | Explicit SaaS requirement and shared-host constraint | Separate database per tenant is not the requested design |
| 2026-10-23 | Preserve current GST and invoice-entry logic as protected behavior | Existing GST logic and Odoo-style inline UX must not be rewritten during conversion | Reimplementing tax math in tenancy foundation risks regressions |
| 2026-10-23 | Treat subdomain tenant resolution as deferred and off by default | Explicitly requested optional later phase | Host-header-based tenant selection is not needed for foundation |
| 2026-10-23 | Use a `tenant_user` pivot for workspace membership and per-workspace role | Supports team members and future multiple workspaces per identity without changing users uniqueness | `users.tenant_id` would constrain each identity to one workspace |
| 2026-10-23 | Until workspace switching exists, resolve a user's lowest-ID tenant membership | Existing app has no workspace selector; deterministic fallback preserves access for migrated users | Host/subdomain selection remains deferred and off |
| 2026-10-23 | Generate new numbers as `PREFIX-YYYY-YY-NNNN` based on tenant FY start; retain old numbers | Makes the sequence tenant/FY scoped while keeping a recognizable invoice prefix | Calendar-year sequence is replaced for new invoices; existing numbers are never rewritten |
| 2026-10-23 | Set MySQL as default and configure Laravel database queue with DB tables | Matches shared-host/VPS constraints and removes silent SQLite default | Redis/Horizon/daemon-based queue processing is outside the target |
