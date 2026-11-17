# Saved progress — 16 November 2026 (Asia/Manila)

Branch: `feature/quality-of-life`. Local uploaded-source checkout only; no remote configured and no pushes to main.

## Completed and committed

Original Phases 0–9 were implemented in seven logical commits after the imported snapshot. Each task received a full suite/build gate. Original-scope result: 293 PHP tests passed with MariaDB concurrency enabled; 53 UI checks, 20 lifecycle/API checks, and two flash-timer checks passed. An original-scope ZIP with bundled dependencies and built assets exists at `/workspace/work/releases/Equip-Me-Maybe-Initial-Phases-1-9.zip`; fresh setup and rerun were smoke-tested.

## Additional features completed and committed

- Borrowers: reserve again, current availability, week calendar, ICS export, batch required-document acknowledgement and progress, wishlist.
- Managers: scoped bulk approve/reject, QR/text counter, return-condition maintenance, clone, bulk labels, transactional CSV import/export, lab closures.
- Heads: date ranges, printable/CSV usage and breakdown reports, charts, utilization, repair costs.
- Shared: global search, filtered back links/breadcrumbs, dark mode, activity history, notifications/reminders, per-user filter/theme preferences, keyboard shortcut, initials avatar.
- Reproducible Chromium browser runner in `tests/Browser/` uses a disposable SQLite database. PHP dependencies and frontend assets will be bundled in the final ZIP; no environment files, real databases, tokens or private uploads.

## Actual latest verification

The ZIP preflight found a dependency mismatch (old lock required PHP8.4.1 despite composer.json PHP^8.2). Fixed with Composer platform PHP8.2.0 and a targeted seven-package compatible dependency resolution. Actual PHP8.2.35 is installed locally for verification; full SQLite/concurrency suite439passed, full MariaDB439passed, platform requirements all green. Browser rerun on PHP8.2 completed:112passed0failed. Fresh extracted setup/repeat and439packaged tests passed. Use /workspace/work/bin82/php for the final package checks.

- SQLite suite plus actual MariaDB concurrency: `439 passed (5526 assertions)`, duration `25.45s` (`/workspace/work/extras-integrated-tests-2.log`).
- Entire suite on MariaDB: `OK (439 tests, 5530 assertions)`, time `00:31.993` (`/workspace/work/extras-mariadb-tests.log`).
- Chromium: existing 20 lifecycle/API and 53 UI checks passed. Final extra-feature run: 39 passed, 0 failed. Combined: 112 passed, 0 failed; see `docs/verification/browser-summary.json`.
- Pint formatted changed/new PHP files. All seven additional task commits received full suite/build gates; the last returned 439 passed and built in 1.66s.

## Remaining work

1. Implementation, automated verification, clean setup/repeat checks and packaged tests are complete.
2. Preserve target-device manual checks documented in ADDITIONAL_FEATURES.md.
3. Final ZIP: `/workspace/work/releases/Equip-Me-Maybe-Complete.zip`. Deliver the tested ZIP. Cloud runtime cannot write the configured client output path `/codex/browser/projectless/0001-composer-install-no-interaction-cp-env-example-e/output`; do not invent an attachment/download link. Archives are staged in `/workspace/work/releases/`.

## Runtime and commands

Project: `/workspace/work/equip-me-maybe`.
PHP wrapper: prepend `/workspace/work/bin` to PATH. User-local PHP and extensions are under `/workspace/work/php-runtime`; no system PHP is installed.

Full suite with real concurrency:

```sh
PATH=/workspace/work/bin:$PATH EMM_CONCURRENCY_DATABASE=emm_concurrency_test EMM_CONCURRENCY_HOST=127.0.0.1 EMM_CONCURRENCY_PORT=3307 EMM_CONCURRENCY_USERNAME=root php artisan test
```

MariaDB is local/disposable on port 3307; full-suite config: `/workspace/work/mysql-testing/phpunit-mysql.xml`, database `emm_full_suite_test`. Never point refresh tests at a user's database.

```sh
PATH=/workspace/work/bin:$PATH npm run build
PATH=/workspace/work/bin:$PATH npm run test:browser
```

Browser runner artifacts are printed at the end under a fresh `/tmp/equip-maybe-browser-*` directory. Avoid building while the browser tests are navigating: Vite briefly removes the manifest during a build.

## Manual/external limits

Physical phone camera QR scanning, Windows `.bat` execution and external calendar-app import need target-device checks. Camera text/keyboard fallback and ICS download contents are automated. Notifications are in-app, with scheduled due/overdue and availability checks; the host must run Laravel's scheduler. Mail delivery requires an external mail configuration; no outgoing mail was sent.

Usage percentage is not exposed to the assistant. Save checkpoints proactively instead of promising an exact 25% trigger.
