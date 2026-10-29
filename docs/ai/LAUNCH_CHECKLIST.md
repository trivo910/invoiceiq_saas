# Launch checklist (Phase 7)

This is a release gate list, not a production-readiness claim. Current target is local development followed later by a VPS/nginx deployment. No VPS has been provisioned or verified.

## Before exposing a public beta

- [ ] Replace the fictional Terms and Privacy sample with approved text; see `LEGAL_DRAFT_NOTES.md`. Set `LAUNCH_LEGAL_ENTITY`, `LAUNCH_CONTACT_EMAIL`, and `LAUNCH_LEGAL_APPROVED=true` only after review and replacement.
- [ ] Approve plan prices/limits and set `LAUNCH_PRICING_APPROVED=true`; remove or clearly identify all sample catalog values.
- [ ] Complete Razorpay support investigation and account-backed Test Mode subscription QA. Demo mode must be off before provider QA; verify checkout, renewal/failure/recovery, webhook signature, and tax invoice behavior.
- [ ] Configure actual SMTP identity and test verification, invitation, invoice email, and account export delivery using the queue.
- [ ] Review tenant activity, invitations/membership events, deleted-workspace list/restore and late-webhook handling in a browser.
- [ ] Decide workspace retention and permanent purge policy. No automated hard purge currently exists.
- [ ] Decide MFA/recovery needs and run a security review of production access and monitoring.

## Before VPS release

- [ ] Provision supported PHP-FPM, MySQL, nginx/TLS, DNS and an unprivileged application/service account.
- [ ] Keep app source, `.env`, logs and private tenant files outside the web root; point document root only to `public/`.
- [ ] Configure production APP_KEY once, `APP_DEBUG=false`, HTTPS URL, secure session cookie, DB, SMTP, database queue and approved billing config. Never rotate APP_KEY casually; it protects encrypted SMTP and invoice-delivery data.
- [ ] Back up DB plus private tenant files and APP_KEY; restore to isolated staging and verify counts, login and PDF/export behavior. Rehearse rollback/forward-repair with representative writes.
- [ ] Review `php artisan migrate --pretend`; migrate and warm caches, then run `php artisan release:check --production` and resolve every CHECK.
- [ ] Run `php artisan settings:encrypt-smtp-passwords` after a backup, then verify SMTP delivery without logging credentials. `release:check --production` confirms nonempty stored passwords are encrypted and decryptable by the configured APP_KEY, without displaying them.
- [ ] Configure `schedule:run` every minute and confirm bounded queue processing, pruning, failed-job alerts, storage capacity, permissions and logs.
- [ ] Verify HTTPS, security headers, private-file denial, public share expiry, login/roles, billing gates and error pages on the deployed host.
- [ ] Confirm provider webhook delivery from Razorpay to the deployed HTTPS URL and check idempotent replay behavior.
- [ ] Record an owner, rollback decision-maker and incident contact.

## Current release report behavior

`php artisan release:check` reports local checks and marks production-only gates SKIP without printing secrets. `--production` checks HTTPS/session cookie settings, disabled demo mode, paid billing configuration, legal/pricing approvals, database queue, SMTP mailer, PHP version/extensions and writable private storage, and returns a failing status for failed checks. The command does not establish SMTP/provider connectivity, legal correctness, backups, recovery, DNS/TLS, VPS security, browser correctness or launch approval.
