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

---

# WEEK 2 – Bug Fixes, UI/UX Enhancements & System Integration

**Development Focus:** Address issues identified during Week 1 auditing, improve the existing system, and conduct further testing.

## 1. Del Rosario, Reimon Anton – Backend Fixes & Business Logic

- [ ] Fix and verify reservation cancellation handling, including cancellation reason persistence.
- [ ] Verify reservation approval concurrency and overlapping booking prevention.
- [ ] Ensure maintenance resolution does not incorrectly mark checked-out equipment as available.
- [ ] Fix Department Head maintenance log filtering.
- [ ] Verify equipment photo URL handling.
- [ ] Ensure database seeding is safe to rerun.
- [ ] Verify faculty priority reservation rules.
- [ ] Add or update regression tests for backend fixes.
- [ ] Address additional backend bugs discovered during QA.

**Expected Output:** Verified backend fixes, updated tests, and a reviewed pull request.

## 2. Ibay, Jonathan Luke – API & QR Code Implementation

- [ ] Implement or improve QR code generation for equipment.
- [ ] Provide a printable QR code page.
- [ ] Verify POST /api/login.
- [ ] Verify GET /api/equipment.
- [ ] Verify GET /api/equipment/{code}/authorization.
- [ ] Verify GET /api/reservations/active.
- [ ] Test invalid, expired, and unauthorized API tokens.
- [ ] Test invalid equipment codes and proper 404 JSON responses.
- [ ] Prepare API documentation and Postman collection.
- [ ] Coordinate with QA for endpoint and QR scanning verification.

**Expected Output:** Verified API endpoints, working QR functionality, Postman collection, and API documentation.

## 3. Ramos, Zachary Brent – UI/UX & Accessibility Enhancements

- [ ] Continue improving the overall visual consistency of the application.
- [ ] Improve dashboard layouts and role-specific navigation.
- [ ] Improve mobile responsiveness across all pages.
- [ ] Fix equipment and reservation table overflow on smaller screens.
- [ ] Improve search and filter layouts.
- [ ] Improve equipment details and reservation page layouts.
- [ ] Improve mobile navigation and toolbar spacing.
- [ ] Improve reservation date and timezone presentation.
- [ ] Improve empty states, loading indicators, and error messages.
- [ ] Implement or improve keyboard navigation and visible focus indicators.
- [ ] Improve color contrast, form labels, and screen reader accessibility.
- [ ] Verify confirmation dialogs and interactive components.
- [ ] Conduct a responsive layout check at 375px width.

**Expected Output:** A more consistent, responsive, accessible, and user-friendly interface without breaking existing functionality.

## 4. Jose, Colleen Margarette – System Integration & Quality Assurance

- [ ] Continue role-by-role testing for all five user roles.
- [ ] Test login, logout, and role-based access restrictions.
- [ ] Test reservation approval, rejection, cancellation, handover, and return.
- [ ] Verify required document download and acknowledgement workflows.
- [ ] Investigate PDF download and document content issues.
- [ ] Test maintenance reporting and repair restrictions.
- [ ] Verify API and QR functionality in coordination with Ibay.
- [ ] Conduct mobile responsiveness and accessibility testing.
- [ ] Document bugs with reproduction steps and screenshots.
- [ ] Track and verify fixes submitted by team members.
- [ ] Review pull requests and coordinate integration.
- [ ] Run PHP unit and feature tests.
- [ ] Run the frontend production build.
- [ ] Conduct regression testing after integrating enhancements.
- [ ] Update QA documentation and known limitations.

**Expected Output:** Updated QA records, verified bug fixes, integration reports, and a stable application ready for further development.

---

## WEEK 2 COMPLETION CRITERIA

- [ ] High-priority backend issues addressed.
- [ ] API and QR functionality verified.
- [ ] Major UI/UX improvements implemented.
- [ ] Accessibility improvements implemented and tested.
- [ ] Critical bugs discovered during QA resolved.
- [ ] Pull requests reviewed and integrated.
- [ ] Existing features remain functional after enhancements.
- [ ] Regression tests and production build completed.

**Reminder:** Tasks should only be marked complete after implementation, review, and verification. All members must work on their assigned branches and submit pull requests for integration into `main`.
