# Equip Me Maybe

A Laravel 12 application for university lab equipment. Students and faculty request equipment and acknowledge safety documents. Lab managers approve requests and record checkout and return. Department heads supervise their own departments, and campus security uses the checkpoint API.

## Run on Windows with XAMPP

Open the project folder containing `artisan` in VS Code. Start **MySQL** in the XAMPP Control Panel. In phpMyAdmin, create a database named `equip_me_maybe`, or use your existing project database. The PHP CLI should be PHP 8.2 or newer. The frontend uses Node 20.19+ or 22.12+. PHP should have GD, PDO MySQL, PDO SQLite, and the standard Laravel/PHPUnit extensions enabled. Use `where.exe php` and `php --ini` to check which PHP and configuration the VS Code terminal uses.

From the VS Code PowerShell terminal:

```powershell
composer install
npm ci
Copy-Item .env.example .env
php artisan key:generate
```

Run the copy and key commands only when setting up a new `.env`. In an existing checkout, retain the current `.env` and application key. Open `.env` and set these values, using your MySQL port and credentials:

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

Save the file. Run:

```powershell
php artisan config:clear
php artisan migrate
php artisan storage:link
npm run build
php artisan serve
```

If you use the packaged build as delivered, the compiled frontend is already included. Run the build again when changing frontend files. For live stylesheet and script editing, run `npm run dev` in a second terminal. Open http://127.0.0.1:8000. If Windows rejects the storage link, enable Windows Developer Mode or use an elevated terminal for `php artisan storage:link`.

For a **local demo database**, optionally run `php artisan db:seed` in another terminal. Seeding can be rerun. Existing legacy demo records are preserved when detected. Use an empty database to see the new demo fixtures and current-time checkouts.

| Role | Demo email |
|---|---|
| Department head | `head1@equip.test` |
| Lab manager | `manager1@equip.test` |
| Student | `student1@equip.test` |
| Faculty | `faculty1@equip.test` |
| Security auditor | `auditor1@equip.test` |

The demo password is `password`. Do not seed a production database with demo credentials.

## Tests

```powershell
php artisan test
npm run build
```

The standard suite uses an in-memory SQLite database. To also test concurrent approvals, create a **dedicated empty** MySQL or MariaDB database named `equip_me_maybe_test`. The test runs migrations there and uses two independent connections.

```powershell
$env:EMM_CONCURRENCY_DATABASE = 'equip_me_maybe_test'
$env:EMM_CONCURRENCY_HOST = '127.0.0.1'
$env:EMM_CONCURRENCY_PORT = '3306'
$env:EMM_CONCURRENCY_USERNAME = 'root'
$env:EMM_CONCURRENCY_PASSWORD = ''
php artisan test --filter=ConcurrentApprovalTest
Remove-Item Env:EMM_CONCURRENCY_DATABASE, Env:EMM_CONCURRENCY_HOST, Env:EMM_CONCURRENCY_PORT, Env:EMM_CONCURRENCY_USERNAME, Env:EMM_CONCURRENCY_PASSWORD
```

- [Checkpoint API and exact Postman requests](docs/API.md)
- [Postman collection](docs/Equip-Me-Maybe.postman_collection.json)
- [Manual checklist, demo script, database relationships, and requirement mapping](docs/DEMO.md)
