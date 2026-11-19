# Additional quality-of-life features

These additions were implemented **after** the original Phases 0–9. Existing policies, compliance checks, overlap protection, row locks and private document storage remain authoritative. See [QUALITY_OF_LIFE.md](QUALITY_OF_LIFE.md) for the original requirements and relationship mapping, and [TEST_RESULTS.md](TEST_RESULTS.md) for executed release results.

## Borrowers

| Feature | Behavior | Evidence |
|---|---|---|
| My Reservations and Reserve again | Remembered status/sort filters; past returned/rejected/cancelled bookings owned by the borrower can open a new form with future dates and a suggested duration. A new submission must pass all booking rules. | `ReservationQualityOfLifeTest`, `ReservationConvenienceTest` |
| Availability on catalog cards | Green Free now, orange occupied/closed/overdue, red repair. Future bookings do not make an otherwise free item unavailable now. An unreturned overdue checkout is never labelled free. | `BorrowerCatalogExtrasTest`, `NotificationsWishlistActivityTest` |
| Weekly calendar | Previous/next week, seven day columns, full dates/times, accessible scroll region and retained booked-window list. No other borrower's name is exposed. | `BorrowerCatalogExtrasTest`; browser week navigation |
| Add to calendar | Authorized viewers can download an approved reservation as UTF-8 `.ics`, with UTC times, escaped fields and folded lines. | `ReservationConvenienceTest`; browser download |
| Return reminders | Live due-within-two-hours and overdue banners. The scheduler records deduplicated in-app notifications; reading one does not cause it to be resent. | `NotificationsWishlistActivityTest` |
| Mark all as read and progress | The equipment page marks all **required** documents as read only after that borrower downloaded every required PDF. It does not overwrite download times or another borrower's records. Progress is shown on equipment and the filtered checklist. | `BorrowerCatalogExtrasTest`; browser download/acknowledgement flow |
| Wishlist | Borrowers privately save/remove items. Availability notifications re-arm when an item becomes blocked and are sent once on the next free cycle. Repair, closures, active bookings and overdue checkouts are respected. | Five-role `NotificationsWishlistActivityTest` datasets; browser faculty wishlist/repair flow |

## Lab managers

| Feature | Behavior | Evidence |
|---|---|---|
| Bulk approve/reject | Select at most 50 pending requests in the own-lab queue. Every record is authorized before the batch starts. Compliance, repair, overlap and closures are checked per row; successful/skipped rows and reasons are reported. | `ReservationConvenienceTest`; browser mixed-compliance approval |
| Scan-to-handover/return | Counter accepts equipment QR text or reservation number. It shows only approved/checked-out own-lab bookings, with compliant handover and condition-based return actions. Keyboard scanners work in the text box; supported browsers can use a camera. | Five-role counter tests; browser text lookup and camera fallback |
| Return condition | Good, damaged, or missing parts. Problems require notes and atomically create a maintenance record linked to the returned reservation and reporting manager. The item stays under repair until resolved. | Five-role return tests; browser damaged return and repair resolution |
| Clone equipment | Own-lab manager supplies a new name/unique serial. Tags are copied; QR is new. Photos, documents, bookings and repair history are deliberately not copied. | `EquipmentManagerToolsTest`; browser clone and audit history |
| Bulk QR printing | Manager's lab or head's department, with optional authorized lab selection. Print layout hides navigation and controls. | `EquipmentManagerToolsTest`; browser print-media checks |
| CSV import/export | Scoped exports, spreadsheet formula escaping, UTF-8 imports up to 2 MB/500 rows. Headers are `name,serial_number` plus optional `tags` separated by `|`. Existing tag names only. All rows validate before the transaction writes anything; unknown columns and duplicate serials are rejected. | Five-role and invalid CSV datasets in `EquipmentManagerToolsTest`; browser import/export |
| Lab closures | Manager owns lab; head owns department. Requests and approvals recheck closures under the shared lab lock. Closures cannot silently displace already approved/checked-out booking windows. | `LabClosureManagementTest`, `QualityOfLifeIntegrationTest`; browser blocked request |

## Department heads

- Usage and breakdown CSV downloads and printable reports remain department-scoped.
- Week, month, January–June/July–December semester and custom dates (up to 366 days). Invalid ranges fall back to the current month.
- Most-reserved equipment and breakdown trend charts include readable numbers/tables.
- Utilization clips and merges approved, checked-out and returned booking windows per item. It divides booked hours by **24-hour calendar capacity**, not an assumed campus timetable; closures and opening-hour calendars are not deducted from the denominator. This basis is stated beside the figures.
- Optional nonnegative repair cost, up to two decimal places, is recorded on a maintenance report and included in scoped period totals and breakdown exports. No currency conversion is performed.

Evidence: five-role `DepartmentReportExtrasTest`, `QualityOfLifeIntegrationTest` and browser period/export/print checks. Request counts include all reservation statuses overlapping the period; breakdowns use the report date. Current repair counts remain a current snapshot.

## Shared features

- Header equipment search by name, serial or QR code; `/` focuses it outside editable controls.
- Breadcrumbs and Back to list preserve normalized local filter URLs. They never trust an external referrer URL.
- Per-user light/dark/device theme and remembered sort/filter choices. Clear filter also removes its remembered state; remembering can be disabled.
- Sidebar initials avatar, role links, mobile navigation and keyboard focus.
- Private notification inbox and unread bell; read/read-all operate only on the signed-in user's notifications.
- Activity history records actors, dates and equipment/reservation changes. Borrowers see their own actions/reservations; managers and heads see their lab/department. Deleted unused equipment retains scoped audit snapshots. Existing reservation or maintenance history prevents destructive equipment deletion.

Evidence: `WorkspacePreferencesTest`, `SharedUiTest`, `NotificationsWishlistActivityTest`, `QualityOfLifeIntegrationTest`, and Chromium light/dark/mobile checks. Audit history is application history, not a cryptographically immutable external ledger.

## Additional role matrix

**Own** means own borrower record, own manager lab or own head department. Authorization still applies after a QR scan, a crafted request, bulk selection or export parameter.

| Action | Student | Faculty | Lab manager | Head | Auditor | Test class |
|---|---|---|---|---|---|---|
| Reserve again / batch required acknowledgement | Own | Own | 403 | 403 | 403 | `ReservationConvenienceTest`, `BorrowerCatalogExtrasTest` |
| Approved ICS download | Own | Own | Own lab | Own department | 403 | `ReservationConvenienceTest` |
| Wishlist add/view/remove | Own | Own | 403 | 403 | 403 | `NotificationsWishlistActivityTest` |
| Bulk decisions / counter / return condition | 403 | 403 | Own lab | 403 | 403 | `ReservationConvenienceTest` |
| Clone / CSV import | 403 | 403 | Own lab | 403 | 403 | `EquipmentManagerToolsTest` |
| Equipment export / labels | 403 | 403 | Own lab | Own department | 403 | `EquipmentManagerToolsTest` |
| Closure CRUD | 403 | 403 | Own lab | Own department | 403 | `LabClosureManagementTest` |
| Reports and exports | 403 | 403 | 403 | Own department | 403 | `DepartmentReportExtrasTest` |
| Maintenance cost reporting | 403 | 403 | Own lab | Own department | 403 | `DepartmentReportExtrasTest` |
| Theme/preferences, inbox | Own | Own | Own | Own | 403 | `WorkspacePreferencesTest`, `NotificationsWishlistActivityTest` |
| Activity history | Own | Own | Own lab | Own department | 403 | `NotificationsWishlistActivityTest` |

## Scheduler and local demo

Reservation decisions create in-app notifications immediately. Return banners are computed when pages open. To also create scheduled reminder and wishlist notifications, run this in a second terminal beside the web server:

```sh
php artisan schedule:work
```

The registered `qol:notify` command runs every minute with overlap prevention. For a one-off demo/check:

```sh
php artisan qol:notify
```

A hosted installation should invoke `php artisan schedule:run` once a minute using its host's scheduler. This release sends **in-app notifications**, not outgoing reminder email. Breeze password-reset email still uses the configured mail transport; the example uses the log mailer.

## Repeating browser checks

Normal demo startup needs neither Node nor Composer when bundled dependencies/assets are intact. Development checks need Node/npm and a compatible Chromium:

```sh
npm ci
npx playwright install chromium
npm run build
npm run test:browser
```

The browser runner creates an isolated temporary SQLite database, applies migrations, seeds demo accounts, then tests the lifecycle, UI and additional features. It does not reset your configured application database. It prints the folder containing JSON results, axe scans, screenshots and server logs. Set `QA_PORT` if port 8012 is occupied; `PLAYWRIGHT_CHROMIUM_EXECUTABLE` selects an existing Chromium binary. Do not run a build while browser checks are navigating, because Vite replaces the manifest during a build.

## Needs manual check on target devices

1. **Windows setup:** extract the ZIP, enable the listed PHP extensions and run `setup-demo.bat`. Confirm the page opens with `php artisan serve`. Run setup again and confirm the key/database are preserved. Linux fresh-extraction setup and rerun are automated; Windows execution was not available here.
2. **Physical camera and labels:** sign in as `manager1@equip.test` on an HTTPS deployment (or a local secure context), open Handover counter, allow the camera and scan a printed Electronics Lab QR label. Confirm the displayed booking belongs to that lab. Text/keyboard and unsupported-camera fallback were automated; a real phone/printer was not.
3. **Calendar app:** as `student1@equip.test` or `faculty1@equip.test`, download an approved reservation and import it into the calendar application you intend to use. Check the local-time conversion. File structure, escaping, authorization and browser download were automated.
4. **Hosted scheduler/mail:** verify the host runs Laravel's scheduler while a checkout approaches its end. For password-reset inbox delivery, configure and check the intended mail transport. No external email was sent during verification.

Demo account passwords are `password`; generated demo data is for local demonstration.
