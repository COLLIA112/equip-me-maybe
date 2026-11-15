# Equip Me Maybe

A Laravel 12 application for university lab equipment. Students and faculty request equipment and acknowledge safety documents. Lab managers approve requests and record checkout and return. Department heads supervise their own departments, and campus security uses the checkpoint API.

## Downloaded ZIP: local demo setup

Extract the entire ZIP into a normal writable folder, then open the folder containing `artisan`. The release includes PHP dependencies and compiled frontend assets, so Composer and Node are unnecessary for running the packaged demo when those bundled files are intact. **PHP 8.2 or newer is still required**; the ZIP does not install PHP or XAMPP for you.

Enable the PHP CLI extensions used by Laravel, PHPUnit, file uploads and QR codes: Ctype, DOM/XML/XMLWriter, Fileinfo, GD, Mbstring, OpenSSL, PDO and PDO SQLite. Use `php --ini` to locate the CLI configuration. The setup script reports missing extensions. MySQL/MariaDB additionally needs PDO MySQL.

For a fresh local SQLite demo on Windows, double-click `setup-demo.bat`, or run this in PowerShell:

```powershell
.\setup-demo.bat
php artisan serve
```

On macOS or Linux:

```bash
sh setup-demo.sh
php artisan serve
```

Open http://127.0.0.1:8000 once the server starts. Alternatively, run `setup-demo.bat --serve` or `sh setup-demo.sh --serve` to start the server immediately after setup. The script creates `.env` only if missing, generates a key only if empty, applies new migrations without resetting tables, and seeds **only a newly created SQLite database**. The default demo uses SQLite; XAMPP MySQL does not need to be running for that default.

The package deliberately excludes `.env`, personal credentials, API tokens and an existing database. Demo accounts are generated locally. If your package lacks `vendor/`, setup falls back to `composer install --no-interaction`. If compiled assets are absent, it falls back to `npm ci` and `npm run build`; those fallbacks require Composer or Node 20.19+ / 22.12+ respectively and an internet connection.

## Updating an existing project or database

Back up the existing database and uploaded files before applying the release. Keep your existing `.env` and application key. Do not overwrite them with the example, and do not run `migrate:fresh` or delete the database.

```powershell
php artisan config:clear
php artisan migrate
php artisan storage:link
php artisan serve
```

The release adds migrations; existing migration files are preserved. The setup launchers can also be rerun: they retain environment settings, retain the key, and skip demo seeding for an existing database. Use `--seed-demo` only when you explicitly want the idempotent demo records added to a local existing database. Demo accounts use a known password; do not seed them in production.

For MySQL/XAMPP, create the database first, retain or edit `.env`, and set your local values:

```dotenv
APP_NAME="Equip Me Maybe"
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=equip_me_maybe
DB_USERNAME=root
DB_PASSWORD=
```

Run `php artisan config:clear` and `php artisan migrate`. For a dedicated local demo database, explicitly run `php artisan db:seed`. If Windows refuses the photo-storage link, enable Windows Developer Mode and retry `php artisan storage:link`.

## Developer setup from source

The following is for a fresh source checkout without bundled dependencies. Do not copy the environment file or regenerate a key when updating an existing installation.

```bash
composer install --no-interaction
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm ci
npm run build
php artisan serve
```

On PowerShell, replace the copy command with `Copy-Item .env.example .env`. Run `npm run dev` in a second terminal when editing the frontend. The application prevents lazy loading outside production to catch missing eager loads.

## Demo accounts

| Role | Demo email |
|---|---|
| Department head | `head1@equip.test` |
| Lab manager | `manager1@equip.test` |
| Student | `student1@equip.test` |
| Faculty | `faculty1@equip.test` |
| Security auditor | `auditor1@equip.test` |

Password: `password`. `manager1` manages Electronics Lab; `head1` heads Engineering. The security auditor uses the API and receives 403 on authenticated application pages. Seed dates are relative to the first seed, so use a fresh local demo database for current-time checkout demonstrations.

## Verification

```bash
php artisan test
npm run build
```

The standard suite uses an in-memory SQLite database. Its concurrent-approval test skips unless a dedicated MySQL/MariaDB database is configured. The database must be empty or disposable and its name must end in `_test`; the test applies migrations and uses two simultaneous connections.

PowerShell example:

```powershell
$env:EMM_CONCURRENCY_DATABASE = 'equip_me_maybe_test'
$env:EMM_CONCURRENCY_HOST = '127.0.0.1'
$env:EMM_CONCURRENCY_PORT = '3306'
$env:EMM_CONCURRENCY_USERNAME = 'root'
$env:EMM_CONCURRENCY_PASSWORD = ''
php artisan test
Remove-Item Env:EMM_CONCURRENCY_DATABASE, Env:EMM_CONCURRENCY_HOST, Env:EMM_CONCURRENCY_PORT, Env:EMM_CONCURRENCY_USERNAME, Env:EMM_CONCURRENCY_PASSWORD
```

Bash equivalent:

```bash
EMM_CONCURRENCY_DATABASE=equip_me_maybe_test \
EMM_CONCURRENCY_HOST=127.0.0.1 EMM_CONCURRENCY_PORT=3306 \
EMM_CONCURRENCY_USERNAME=root EMM_CONCURRENCY_PASSWORD='' php artisan test
```

Run the full suite and production build after each logical task. A passing build alone does not prove authorization, browser interactions, concurrent locking or external email delivery. Record actual command output, including skips and failures.

- [Quality-of-life phase coverage, role matrix, requirement mapping and limitations](docs/QUALITY_OF_LIFE.md)
- [Checkpoint API and exact Postman requests](docs/API.md)
- [Postman collection](docs/Equip-Me-Maybe.postman_collection.json)
- [Manual checks and demo script](docs/DEMO.md)

## Additional improvements and verification

See [ADDITIONAL_FEATURES.md](docs/ADDITIONAL_FEATURES.md) for the borrower, manager, head and shared additions, role restrictions, scheduler setup, browser runner and target-device checks. Executed release summaries are recorded in [TEST_RESULTS.md](docs/TEST_RESULTS.md).
