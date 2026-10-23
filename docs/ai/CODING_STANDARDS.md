# Coding standards

## Framework and layout

- Stay with Laravel 11, Blade, MySQL, and vanilla JavaScript; do not add React/Vue or SQLite as a production dependency.
- Follow Laravel naming: singular StudlyCase models, plural snake_case tables, resource controllers, named routes, migrations and seeders.
- Keep the existing custom dark-sidebar UI and inline invoice workflow intact.

## Application boundaries

- Controllers coordinate HTTP; use Form Requests for non-trivial validation, policies for authorization, and services for multi-step business workflows.
- Keep tenant ID server-derived. Never accept it as an authoritative request field.
- Scope every tenant query; include tenant ID in raw SQL and cache keys.
- Keep GST math changes out of tenancy refactors; if approved, centralize and regression-test carefully.
- Use DB transactions for invoice + lines + payment/sequence operations as appropriate.

## Views and assets

- Blade views escape user data by default and use CSRF tokens on forms.
- Keep browser code framework-free; separate or clearly scope JS/CSS when changing current inline workflow.
- Ensure accessible labels, validation messages, and responsive behavior.

## Database and hosting

- Use reversible migrations where feasible, explicit indexes/FKs, and tenant-scoped unique constraints.
- Keep `.env.example`, `config/database.php`, setup scripts, and deployment docs consistent with MySQL support.
- Production queue strategy uses database driver plus cron; do not require Redis/Horizon/long-running daemons.

## Change hygiene

- Update affected docs in the same phase; schema doc changes with each migration.
- Add isolation and behavior tests for new security-sensitive behavior when test work is in scope.
- Commit message format: `type(scope): concise imperative summary`, e.g. `feat(tenancy): scope customer records`.
