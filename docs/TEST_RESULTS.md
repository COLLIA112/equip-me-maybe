# Executed release verification

Recorded on 16 November 2026 (Asia/Manila). The local branch is `feature/quality-of-life`; no GitHub push or main-branch modification was performed. These results concern the uploaded project plus the implemented original phases and additional improvements.

## Final command output

`php artisan test` from a clean ZIP extraction on **actual PHP 8.2.35**, using SQLite memory tests plus the dedicated MariaDB concurrent-approval test:

```text
Tests:    439 passed (5526 assertions)
Duration: 19.26s
```

Entire suite using an isolated MariaDB database:

```text
Time: 00:30.957, Memory: 78.50 MB
OK (439 tests, 5530 assertions)
```

`npm run build`:

```text
✓ built in 1.69s
```

Chromium browser runner:

```text
Lifecycle/API:       20 passed, 0 failed
UI:                  53 passed, 0 failed
Additional features: 39 passed, 0 failed
Total:              112 passed, 0 failed
```

No browser JavaScript exceptions were recorded. Light-mode axe checks cover login, catalog, queue and department. Dark-mode axe checks cover catalog, equipment details, notifications, wishlist, queue, counter, equipment tools, closures and department. The checked pages had zero WCAG A/AA violations after fixes. Desktop and 375px checks verify sidebar keyboard behavior and page overflow; they are browser emulation, not a physical phone camera test.

The seven original task commits each received a full test/build gate (293 PHP tests at that stage). Each of the seven additional implementation/test commits also received a full test/build gate (439 PHP tests). Concurrent approval was enabled, not skipped, for these gates.

## Evidence included in this ZIP

- [SQLite suite with concurrency](verification/phpunit-sqlite.txt)
- [Entire MariaDB suite](verification/phpunit-mariadb.txt)
- [Production build](verification/build.txt)
- [Browser output](verification/browser.txt)
- [Browser checks and results](verification/browser-summary.json)

The browser runner prints a fresh temporary artifacts folder when repeated. Source is in `tests/Browser/`; it uses a disposable database, never `migrate:fresh` against the user's configured application database.

## Reproduced defects and fixes

Failing tests or browser checks were recorded before fixes for malformed filters, department-scoped links, destructive equipment deletion, auditor web access, confirmation-dialog button context, pagination ARIA, contrast, seeder recovery/compliance/history, notification privacy/deduplication and availability cycles, deleted-item audit history, closure conflicts and timezone guidance. Existing tests were preserved. Two precise earlier assertions changed for intended behavior: approval flashes now include a reservation number; clearing remembered filters now uses the explicit reset action before verifying the unfiltered page.

## Setup/package verification

Final code/dependency archive: ZIP integrity, clean extraction, fresh SQLite setup and repeat setup passed on PHP 8.2.35. Both runs produced all eight proposal counts. The repeated setup preserved the application key/environment and an edited user record. The extracted copy also passed all 439 tests. Evidence: [clean setup](verification/clean-setup.json) and [PHP 8.2 platform requirements](verification/php82-platform.txt). The release lock now resolves for PHP 8.2.0 instead of inheriting dependencies that required PHP 8.4.1.

## Needs manual check

See [ADDITIONAL_FEATURES.md](ADDITIONAL_FEATURES.md#needs-manual-check-on-target-devices): Windows batch execution; physical phone/camera and printed QR label; importing ICS into the chosen calendar application; hosted scheduler and external password-reset mail transport. No outgoing email was sent. The app's reminder notifications use the in-app database channel.
