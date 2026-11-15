# Original quality-of-life phases: implementation and verification guide

This document covers the original Phase 0–9 request. The later wishlist, calendar-grid, bulk actions, notifications, CSV, dark-mode and similar additions are a separate follow-up scope; they must be verified separately when implemented. Existing reservation, compliance, repair and department/lab authorization rules remain the foundation.

## Verification record

The uploaded snapshot baseline was executed before feature changes. With a local PHP runtime and dependencies installed, the initial full suite with MariaDB concurrency enabled returned `70 passed (277 assertions)`. A separate run of that original suite against MariaDB returned `OK (70 tests, 277 assertions)`. Those baseline counts are **not** the final release count.

<!-- FINAL_RELEASE_VERIFICATION: replace this paragraph with the actual final full-suite, MySQL/MariaDB, build and browser outputs before packaging. -->
Final integration verification is recorded by the packaging run. Do not infer a pass from the presence of tests or this mapping. Report actual summaries and any skipped checks.

## Phase coverage and source ownership

| Phase | Resulting behavior | Principal implementation | Automated evidence |
|---|---|---|---|
| 0 | Proposal/code audit, baseline, scope and seed count comparison | Project proposal and master instructions; baseline commands | Existing full suite and `DemoSeederTest` |
| 1 | Instant GET select filters, 400 ms text debounce and focus/caret restoration; selected values survive reload; Clear filter appears only for active valid filters; malformed values ignored | Catalog/document/reservation/maintenance filter Form Requests; `resources/js/qol.js`; relevant index/checklist views | `CatalogDocumentFiltersTest`, `ReservationQualityOfLifeTest`, `MaintenanceDashboardQualityTest`; browser interaction checks |
| 2 | Whitelisted sort fields/direction; filter-aware pagination; explicit record counts; empty states distinguish inventory/account state from active filters; overdue badges and shortcut | List summary/sort components; list controllers/views; dashboard | Sorting/pagination/empty-state methods in the three feature classes above; manager-dashboard count test |
| 3 | Accessible dismissible success flash; named missing-document reasons; optional manager rejection reason; recorded faculty-priority rejection reason; lifecycle actors and timestamps | `ReservationService`; reservation Form Requests/model; new lifecycle migration; queue/detail views; flash component | `ReservationQualityOfLifeTest`, `SharedUiTest`, existing `DocumentComplianceTest` and priority tests |
| 4 | Past-date limits, one-hour suggested end, preserved old/manual dates, live conflict guidance; server-side rules remain authoritative; full booked start/end dates; borrower document progress | `reservations/create.blade.php`, `reservation-dates.js`, equipment details and borrower dashboard | `ReservationDateUxTest`; dashboard document-progress test; existing overlap and compliance tests |
| 5 | Scoped statistics, recent activity and quick actions; manager due-today/overdue; borrower upcoming/active/pending-doc information; department lab, usage and repair figures | Dashboard and department report controllers/views using eager loads and aggregate counts | `MaintenanceDashboardQualityTest` |
| 6 | Existing role sidebar retained; mobile menu supports Escape/focus; auth uses guest layout; tables scroll inside their containers | Shared layouts/navigation; `.responsive-table` CSS | `SharedUiTest`; real Chromium desktop/375 px checks |
| 7 | Focus rings, control names, live status/error semantics, non-disappearing validation errors, submit busy state and consistent 403/404 pages | Shared CSS/layout/components; POST-only confirmation/submitting implementation, including its dialog-target regression fix, with added module imports | `SharedUiTest`; real-browser confirmation, duplicate-request and accessibility checks |
| 8 | API-only auditor restriction; department-scoped laboratory management; equipment tag management; deletion guards; exact demo scale with recovery and compliant lifecycle fixtures | Web-role middleware; administration controllers/requests/views; seeder; safe delete guard | `AuditorWebAccessTest`, `AdministrationManagementTest`, `CatalogDocumentFiltersTest`, `DemoSeederTest`, `SeederRecoveryTest` |
| 9 | Full verification, role matrix, requirement mapping, relationships, known limitations and current demo/API documentation | This guide, README, `DEMO.md`, `API.md` | Final release command/browser logs; named tests below |

All GET filters use the normal browser navigation/request flow. The global confirmation handler continues to intercept POST forms only. SQL sort fields are selected from explicit whitelists; query parameter text is never used as an arbitrary column name.

## Five-role action matrix

**Own** means the borrower's own reservation, the manager's own lab, or the head's own department. “Allowed” does not bypass validation, reservation status, documents, overlap or repair rules. The auditor column concerns web actions; its checkpoint API permissions are separate.

| Main action | Student | Faculty | Lab manager | Department head | Security auditor | Named test evidence |
|---|---|---|---|---|---|---|
| Open profile | Allowed | Allowed | Allowed | Allowed | Denied 403 | `RoleRegressionTest::test_all_five_roles_have_expected_page_access` |
| Open role dashboard | Allowed | Allowed | Own lab | Own department | Denied 403 | `RoleRegressionTest::test_all_five_roles_have_expected_page_access`; `MaintenanceDashboardQualityTest::test_role_dashboard_access_and_actions`; `AuditorWebAccessTest::test_api_only_security_auditor_cannot_open_web_pages` |
| Browse/filter equipment | Allowed | Allowed | Allowed | Own department | Denied 403 | `CatalogDocumentFiltersTest::test_catalog_filters_and_management_visibility_for_each_role` |
| Create/edit equipment | Denied | Denied | Own lab | Own department | Denied | Catalog role test above; `AccessControlTest::test_lab_manager_can_edit_own_labs_equipment`; `test_lab_manager_cannot_edit_another_labs_equipment` |
| Delete equipment | Denied | Denied | Own, unused only | Own, unused only | Denied | `CatalogDocumentFiltersTest::test_delete_preserves_history_and_enforces_role_permissions`; `test_equipment_with_history_cannot_be_deleted` |
| Open reservation request | Allowed | Allowed | Denied 403 | Denied 403 | Denied 403 | `ReservationDateUxTest::test_only_borrowers_can_open_the_date_picker` |
| Submit invalid/past reservation dates | Rejected validation | Rejected validation | Denied 403 | Denied 403 | Denied 403 | `ReservationDateUxTest::test_invalid_dates_cannot_bypass_server_validation_for_any_role` |
| My Reservations filters | Own | Own | Denied 403 | Denied 403 | Denied 403 | `ReservationQualityOfLifeTest::test_reservation_filter_pages_enforce_each_roles_access`; `test_my_reservations_filters_status_keeps_selection_and_never_lists_another_borrower` |
| Queue filters and overdue returns | Denied 403 | Denied 403 | Own lab | Denied 403 | Denied 403 | `ReservationQualityOfLifeTest::test_reservation_filter_pages_enforce_each_roles_access`; `test_queue_filters_borrower_status_and_overdue_within_the_managers_lab` |
| Approve/hand over/return | Denied | Denied | Own lab, correct lifecycle and compliance | Denied | Denied | `RoleRegressionTest::test_only_own_lab_manager_can_approve_valid_request`; `test_all_roles_have_expected_lifecycle_action_permissions`; `DocumentComplianceTest`; timeline actor/time test |
| Reject with optional reason | Denied 403 | Denied 403 | Own lab | Denied 403 | Denied 403 | `ReservationQualityOfLifeTest::test_rejection_reason_can_only_be_saved_by_the_items_manager` |
| View required-document checklist | Own open requests | Own open requests | Denied | Denied | Denied | `CatalogDocumentFiltersTest::test_document_manager_and_checklist_filters_enforce_each_role` |
| Upload/manage equipment documents | Denied | Denied | Own lab | Denied | Denied | Same catalog/document role test; `DocumentUploadTest::test_manager_of_another_lab_cannot_upload` |
| Download private equipment documents | Allowed | Allowed | Own lab | Own department | Denied | `RoleRegressionTest::test_document_download_permissions_for_all_roles`; `SystemCompletionTest::test_private_documents_have_no_public_file_and_require_an_authorized_download` |
| Maintenance list/report/resolve | Denied | Denied | Own lab | Own department | Denied | `MaintenanceDashboardQualityTest::test_maintenance_filters_keep_role_boundaries`; `SystemCompletionTest::test_head_can_see_equipment_and_maintenance_only_in_their_department` |
| Department reports/users | Denied | Denied | Denied | Own department | Denied | `RoleRegressionTest::test_all_five_roles_have_expected_page_access`; `test_head_cannot_reassign_another_departments_user_or_another_head`; `MaintenanceDashboardQualityTest::test_department_report_is_scoped_for_all_roles` |
| Laboratory administration | Denied | Denied | Denied | Own department | Denied | `AdministrationManagementTest::test_laboratory_administration_is_department_head_only`; `test_head_cannot_list_edit_or_delete_laboratories_from_another_department` |
| Tag administration | Denied | Denied | Allowed | Allowed | Denied | `AdministrationManagementTest::test_tag_administration_requires_a_manager_or_department_head` |
| Obtain checkpoint token through API login | Denied credentials | Denied credentials | Denied credentials | Denied credentials | Allowed | `CheckpointApiTest::test_only_auditors_can_log_in_for_a_token` |
| Checkpoint with no token / wrong ability | 401 / 403 | 401 / 403 | 401 / 403 | 401 / 403 | 401 / 403 | `CheckpointApiTest::test_request_without_a_token_gets_401`; `test_valid_token_without_the_right_ability_gets_403` |

Tests with role providers execute one case for each of the five roles. `RoleRegressionTest` additionally enumerates page access, mutation/lifecycle actions and private downloads across all five roles. The matrix also references focused tests for record scope and lifecycle rules; a role label alone is not proof of every possible record/action combination. Additional explicit role/action regression cases belong in the suite when a new action is introduced.

## Feature-to-requirement mapping

| Mandatory proposal requirement | Implementation | Representative executed-test targets |
|---|---|---|
| 1. Multi-role authorization | Five-value Role enum, Gates, equipment/reservation/document Policies, web role middleware and scoped queries | `AccessControlTest`, `AuditorWebAccessTest`, role providers in catalog/reservation/maintenance/shared UI/admin tests |
| 2. Authentication | Breeze Blade register/login/logout/reset/profile; public registration stays student; session regeneration and invalidation | `tests/Feature/Auth/*Test.php`, `ProfileTest` |
| 3. Advanced relationships | Equipment↔Tag and User↔Document pivots; Department→Lab→Equipment and Lab→Equipment→Reservation has-many-through | Department dashboard queries, model relationships, compliance and seeder tests |
| 4. Seeding scale | Idempotent factories/seeder for 3 departments, 12 labs, 120 users, 15 tags, 250 equipment, 400 documents, 600 reservations, 80 maintenance logs | `DemoSeederTest::test_rerunning_the_demo_seeder_does_not_duplicate_or_reset_data`; `SeederRecoveryTest` |
| 5. File upload/download | Validated JPG/PNG public photos, validated private PDFs; authorized download and separate acknowledgement timestamps | `DocumentUploadTest`; `SystemCompletionTest::test_equipment_uses_the_public_photo_disk_and_validates_uploads`; private-file test; compliance tests |
| 6. Token API | Sanctum `checkpoint:verify`; token login/logout, equipment listing, code authorization, active checkout JSON Resources | `CheckpointApiTest`; Postman collection and `API.md` |
| 7. PHPUnit suite | Unit overlap/repair/status-accessor checks plus feature and two-connection concurrency coverage | `tests/Unit/*`, `tests/Feature/*`, `ConcurrentApprovalTest` |
| 8. MVC foundation | Web/API routing split, controllers, Form Requests, Policies/Gates, ReservationService and Breeze Blade/Tailwind/Alpine | All HTTP feature tests plus production Vite build; view and source maps above |

## Database relationships

- **Equipment ↔ Tag: many-to-many.** `equipment_tag` associates one item with several tags and one tag with several items. `Equipment::tags()` and `Tag::equipment()` expose it.
- **User ↔ Document: many-to-many with pivot attributes.** `document_user` stores each borrower's `downloaded_at` and `acknowledged_at` separately, plus timestamps. A download never automatically acknowledges a PDF. Compliance checks the correct borrower against all required PDFs for the reserved item.
- **Department → Lab → Equipment: has-many-through.** `Department::equipment()` queries all equipment owned by labs belonging to that department. This supports head-scoped reports without duplicating department ownership on equipment.
- **Lab → Equipment → Reservation: has-many-through.** `Lab::reservations()` aggregates reservations for items in the lab, supporting lab statistics and manager scopes.
- **Reservation belongs to equipment and borrower.** Lifecycle foreign keys identify the approver, handover/return managers and rejection/cancellation actors where recorded; corresponding timestamps retain the action history. Records created before the new migration can legitimately have unknown historical timestamps or actors.
- **MaintenanceLog belongs to equipment, reporter and optional reservation.** The reservation link records the borrower of record when a breakdown is reported. Resolving one log leaves equipment under repair while another unresolved log exists.
- **User owns Sanctum tokens.** API tokens carry abilities independently of a web session. The API checks the checkpoint ability before returning checkout information.

## Data and schema notes

New lifecycle columns are introduced by an additive migration. No old migration is edited, and existing historical timestamps are not fabricated. Re-running seeds retains edited user names/item state and must not duplicate demo records. Laboratory/tag deletion is restricted when related equipment/users/assignments exist; equipment with reservation or maintenance history cannot be deleted. Back up an existing database and upload storage before updating.

Shared-file changes require team coordination before merge: web routes, shared layouts/components, the new migration, seeder, application JS module imports, and middleware/bootstrap registration. The confirmation/submitting handler remains POST-only. Browser testing exposed a dialog-target bug in Confirm; its fix is covered by real dialog interactions. Affected reservation forms are Approve, Reject, Hand over and Receive return in `reservations/queue.blade.php`, and Cancel in `reservations/index.blade.php`. They retain POST with PATCH method spoofing. The shared handler also applies submit-state protection to other POST forms; document/equipment deletion and maintenance resolution retain their confirmations. GET filter submissions bypass that handler.

## Known limits and external checks

- A ZIP is runnable only with a supported local PHP installation and enabled extensions. Windows/XAMPP compatibility and storage-link creation should be checked on the target computer; the Linux test environment cannot establish Windows behavior.
- Email uses the configured mail transport. The default log mailer does not deliver password-reset links to an inbox. A real SMTP/mail service and actual inbox delivery need a separate configured check.
- QR code rendering can be tested in HTML/SVG and print styles. Physical scanning with the security team's phone/scanner and printer margins still require those devices.
- `datetime-local` fields use the browser's local wall-clock values while server-side parsing follows the configured application timezone. Keep local browser/app timezones aligned for demos; cross-timezone deployment needs an explicit timezone policy and tests.
- Browser screenshots/accessibility automation provide bounded evidence; they do not replace screen-reader assessment or all possible device/font combinations.
- Relative seed checkout dates are preserved on rerun. Use a newly created local demo database for in-window API demonstrations; do not reset an existing team database to move dates.
- Real concurrent approval is only verified when the dedicated MySQL/MariaDB integration test runs without skipping. SQLite in-memory tests cannot prove row-lock serialization.
- Faculty priority affects overlapping pending student requests only. Approved/checked-out bookings remain blocking, and the class-session request still requires approval and document compliance.
- Extra features from the follow-up request must not be advertised as complete based solely on the original phase tests. Add their own authorization/validation/browser evidence and update this document when integrated.

## Final local/manual checklist

Use password `password` with the local seeded accounts; never publish those accounts as production users.

1. `student1@equip.test`: catalog select auto-submits; type search and confirm focus/caret after debounce; clear filters and verify the link disappears; request a future item; download then acknowledge each required PDF; inspect document progress and reservation timeline.
2. `faculty1@equip.test`: make a class-session request over a student's pending window. Verify the student sees a rejection reason; verify an approved booking still blocks the faculty request.
3. `manager1@equip.test`: use Electronics Lab items. Verify named missing documents disable approval/handover. After acknowledgement, approve, hand over, receive return, reject with a reason, and test Go back/Confirm in the action dialog. Confirm one mutation per action, including rapid double-clicks.
4. `head1@equip.test`: inspect Engineering-only reports, labs and users; create/edit an empty lab; attempt another department's equipment, lab and breakdown URLs; verify 403 and unchanged data.
5. `auditor1@equip.test`: authenticated web dashboard/profile/checklist return 403; API login yields the checkpoint ability. Run 200, 401, 403, 404 and token-revocation requests from `API.md`.
6. At 375 px, inspect all roles' permitted dashboard/catalog/queue/maintenance/department pages; open menu, tab through it and close with Escape; verify tables scroll inside cards without document-wide horizontal overflow.
7. Print an equipment QR label; scan its displayed code with the actual checkpoint device. Download a private PDF through its authorized route and verify `/storage/documents/...` cannot serve it.
8. Run `php artisan test` with concurrency variables configured and `npm run build`; record the complete final summary, skips, browser results and any remaining failures. Do not remove or weaken a test to make a release appear green.
