# InvoiceIQ SaaS conversion documentation

This folder is the working source of truth for the phased SaaS conversion. It describes the inspected application as it exists at Phase 0 and records intended future behavior separately. Do not treat a target design as already implemented.

## Reading order

1. `PROJECT_CONTEXT.md`, `EXISTING_FEATURES.md`, `GST_RULES.md`
2. `ARCHITECTURE.md`, `DATABASE_SCHEMA.md`, `TENANCY_RULES.md`
3. `PERMISSIONS_MATRIX.md`, `BILLING_AND_PLANS.md`, `SUPERADMIN_SPEC.md`
4. `SECURITY_CHECKLIST.md`, `CODING_STANDARDS.md`, `TESTING.md`
5. `DEPLOYMENT.md`, `ROADMAP.md`, `PROGRESS.md`, `DECISIONS.md`, `CHANGELOG.md`
6. `PHASE_PROMPTS.md` when handing work to a fresh session

Before any phase, read this index, `PROGRESS.md`, `TENANCY_RULES.md`, and `CODING_STANDARDS.md`, plus docs relevant to that phase. At phase end, update progress, roadmap, changelog, schema, and every affected document. Review and approve Phase 0 docs before starting Phase 1.

## State labels

- **Observed**: verified in the current repository scan.
- **Target**: user-requested future SaaS behavior; not yet implemented.
- **Decision**: architectural choice already made and recorded in `DECISIONS.md`.
- **Open**: requires implementation-phase verification or a deliberate design choice.

## Phase 0 scope

Phase 0 changes documentation only. No application code, database, configuration, setup, or runtime behavior is changed. Existing feature and GST documents are based on the checked-in migrations, models, controllers, Blade views, helpers, routes, and seeder. This scan is static; database contents and production deployment configuration have not been inspected.

## Current handoff

Phase 7 launch-hardening work is in progress; see PROGRESS.md, LAUNCH_CHECKLIST.md, LEGAL_DRAFT_NOTES.md and DEPLOYMENT.md for implemented behavior and open release gates. Public legal pages contain fictional reference drafts, remain unapproved, and use reserved non-deliverable contact details. Local development is current and the future VPS is unverified. Phase 4 provider-backed QA/commercial decisions remain pending. Historical phase prompts are templates, not statements that earlier phases are still unimplemented.
