# Equip Me Maybe: foundation PHP

These files are an overlay for a fresh Laravel 11/12 project. Copy them over a new install.

## Setup

```bash
composer create-project laravel/laravel equip-me-maybe
cd equip-me-maybe

composer require laravel/breeze --dev
php artisan breeze:install blade      # Tailwind + Blade auth pages
php artisan install:api               # Sanctum + routes/api.php + personal_access_tokens migration

# Now copy this folder's contents over the project (say yes to overwrite):
#   app/  bootstrap/app.php  database/  routes/  tests/

php artisan migrate:fresh --seed
php artisan storage:link
php artisan test
```

Demo logins (password `password`): head1@equip.test, manager1@equip.test, student1@equip.test,
faculty1@equip.test, auditor1@equip.test.

## What is included

| Area | Files |
| --- | --- |
| Roles, statuses | `app/Enums/*` |
| Overlap rule (pure, unit testable) | `app/Support/ReservationWindow.php` |
| Business rules (overlap, repair gate, compliance gate, lifecycle) | `app/Services/ReservationService.php` |
| Gates | `app/Providers/AppServiceProvider.php` |
| Policies | `app/Policies/*` (auto discovered) |
| Models and relationships | `app/Models/*` |
| Web controllers | `app/Http/Controllers/Web/*` |
| API controllers and Resources | `app/Http/Controllers/Api/*`, `app/Http/Resources/*` |
| Form Requests | `app/Http/Requests/*` |
| Migrations, factories, seeder | `database/*` |
| Routes | `routes/web.php`, `routes/api.php`, `bootstrap/app.php` |
| PHPUnit tests | `tests/Unit/*`, `tests/Feature/*` |

## Not included yet (next step)

Blade views. Controllers return these view names, which still need to be created in `resources/views`:
`equipment.{index,show,create,edit}`, `reservations.{index,create,queue}`,
`documents.{index,checklist}`, `maintenance.index`, `department.{index,breakdowns,users}`.
`dashboard` and the auth pages come from Breeze.

Feature tests avoid rendering views, so they pass before the views exist.

## Notes

- `role`, `lab_id` and `department_id` are not mass assignable, so registration can only ever create a student.
- Seeded documents all share `storage/app/private/documents/sample.pdf` so downloads work in demos.
- `qr_code` stores the code string. To render an actual QR image later, `composer require simplesoftwareio/simple-qrcode`.
