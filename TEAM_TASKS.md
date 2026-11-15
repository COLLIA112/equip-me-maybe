# Equip Me Maybe: team task assignment (WEBSYS2, Group 5)

Members: Del Rosario, Reimon Anton | Ibay, Jonathan Luke | Ramos, Zachary Brent | Jose, Colleen Margarette
(Suggested split. Swap freely if someone prefers another area.)

## Already pushed: minimal working frontend (Colleen)
Copy `resources/views/` over the project (overwrites Breeze layouts, navigation, dashboard), then:

    npm run build
    php artisan test
    php artisan serve

Demo logins (password: password): head1@, manager1@, student1@, faculty1@, auditor1@equip.test
Covers: role based nav, dashboard, equipment catalog with filters, equipment details, create and edit,
reserve form, My Reservations, reservation queue (approve, reject, hand over, return),
required documents checklist, document manager, maintenance log, department dashboard,
breakdown history, user and role management, 403 page.

WARNING: do not run `php artisan db:seed` again until the seeder is idempotent (Reimon's first task).

---

## Del Rosario, Reimon Anton: Backend fixes (Phase 2)
- [ ] Approval concurrency: lock the equipment row inside `ReservationService::approve` before re-checking overlap (same pattern `request()` already uses).
- [ ] Maintenance resolve must not set an item to available while it is still checked out.
- [ ] Department Head maintenance dropdown: `MaintenanceLogController@index` queries by `lab_id`; query by the head's department instead (the report form is empty for heads right now).
- [ ] `photo_url` accessor: use `Storage::disk('public')->url(...)`.
- [ ] Seeder idempotency: `firstOrCreate` for departments, labs, tags, users; skip pivot rows that already exist.
- [ ] Faculty priority rule: decide and document it (suggestion: a faculty class session request may displace a pending student request, never an approved or checked out one). Add tests.

## Ibay, Jonathan Luke: QR codes and Sanctum API (Phase 7)
- [ ] `composer require simplesoftwareio/simple-qrcode`; show a QR on the equipment details page; add a printable QR page.
- [ ] Verify all 4 API endpoints in Postman (POST /api/login, GET /api/equipment, GET /api/equipment/{code}/authorization, GET /api/reservations/active).
- [ ] Write the API documentation and the Postman collection.
- [ ] Invalid equipment code returns a clean 404 JSON; add the test.

## Ramos, Zachary Brent: UI improvements
- [ ] Replace the top nav with a responsive sidebar (keep the role based links in `layouts/navigation.blade.php`).
- [ ] Dashboard polish per role: stat cards, recent activity, quick actions.
- [ ] Consistent empty states, loading and disabled button states, confirmation dialogs (replace `confirm()` with an Alpine modal component).
- [ ] Style the Breeze auth pages (login, register, forgot and reset password) and the profile page to match.
- [ ] Mobile pass: tables scroll or collapse to cards on small screens; check every page at 375px width.
- [ ] Reservation details page and a clearer reservation timeline (pending, approved, checked out, returned).
- [ ] Accessibility basics: focus states, labels, color contrast on status badges.

## Jose, Colleen Margarette: Integration, tests and demo prep (Phases 8 and 9)
- [ ] Review and merge teammates' branches; keep `php artisan test` and `npm run build` green on main.
- [ ] Add the missing tests from the master prompt: department head cannot see another department, photo upload, concurrent approval, faculty priority, private document files not reachable by URL.
- [ ] Role by role manual test pass (all five roles) against the business rules.
- [ ] Demo script per role, database relationship explanation, feature to requirement mapping, known limitations list.
