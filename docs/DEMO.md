# Equip Me Maybe demo and verification guide

Use a local seeded database. Accounts and setup are in the project README. Use equipment from **Electronics Lab** for `manager1@equip.test` and use a new demo item for the main lifecycle so seeded reservations do not interfere. When testing dates, choose a future window. For a checkpoint demonstration, hand over an approved reservation that is already in its window, or use a seeded in-window checkout from a fresh demo database.

## Role-by-role checklist

| Role | Steps | Expected result |
|---|---|---|
| Student | Register a new user. Browse equipment, use filters, open an item, and submit an available time window. In Required Documents, download and acknowledge each required PDF. Open My Reservations and the reservation details. | Public registration creates a student. The request starts Pending. An acknowledgement requires a prior download. The student can cancel an eligible own request and receives 403 on the manager queue or another borrower's reservation. |
| Faculty | Create a regular request in another student's pending window, then try a faculty class-session request for that same item and window. Repeat on a separate window with an approved booking. | Regular requests leave the student's pending entry alone. A class-session request rejects overlapping pending student requests and stays Pending itself. An approved or checked-out overlap blocks the class-session request and keeps the other records unchanged. |
| Lab manager | Open the queue for your lab. Try approval before the borrower acknowledges a required document. After acknowledgement, approve, hand over and receive the item. Report two breakdowns during a checkout and resolve them one by one. | The compliance gate blocks approval/handover until acknowledgement. Checkout sets equipment to Reserved. An unresolved log keeps equipment Under repair. Resolving the last log during checkout restores Reserved. A return clears the checkout and frees the item once repairs are resolved. |
| Department head | Open Department and compare labs, repair totals, and recent maintenance. Open Maintenance and report an issue using an item in your department. Open the catalog and try a known equipment or breakdown URL for another department. | The dropdown and reports are department-scoped. Direct URLs outside the department return 403. User role changes are scoped to the department and unassigned users. |
| Security auditor | Import the Postman collection, log in, list equipment, verify the QR code for an in-window checkout, and list active checkouts. Run the four negative access cases from API.md. | Checkpoint requests require the Sanctum ability. The assignment scenarios return 200, 401, 403, and 404 as documented. Authenticated application pages, including dashboard, profile and checklist, are forbidden for this API-only role; logout remains available. |

## Additional manual checks

- Upload a small JPG or PNG to equipment, then a PDF and a file larger than 2 MB. Only the supported image should save. Check its public URL from the equipment page.
- Download a private document using its authorized route. Verify the same path under `/storage/documents/` cannot serve it.
- Submit two overlapping requests while both are pending, approve the first, then attempt the second. The last attempt should show the booked window and keep the second reservation pending. Use the dedicated PHPUnit integration test to verify the row lock with two simultaneous connections.
- Open equipment details as a borrower and verify the QR depicts the displayed equipment code. As a manager or head responsible for the item, open **Print QR label**. In print preview verify the item name, serial and QR code are visible and the sidebar and buttons are hidden. Scan with a phone before printing labels in bulk.
- Change catalog, queue, My Reservations, maintenance and document filters and verify select changes submit immediately. Type into a search field, wait about 400 ms, and verify focus/caret and valid selected values survive. Clear filter appears only when a valid filter is active. Sort and paginate without losing those filters.
- Inspect a checked-out reservation past its due time: it must show a red Overdue badge. The manager dashboard Overdue returns shortcut must open the overdue queue.
- In browser device mode, set width to 375px. Open the mobile menu, tab through it and close it with Escape. Check that tables scroll within their cards. Cancel an action in the confirmation dialog and verify nothing changes. Confirm an action and verify only one request is sent. Check the sign-in, registration, password reset and profile layouts.

## Demo script

1. **Department head.** Show the department report and one maintenance record. Explain that the department owns labs and each lab owns equipment. Show a forbidden breakdown link from another department.
2. **Lab manager.** Add or open an Electronics Lab item, show its QR code and print label, and upload a required safety PDF. Leave the item available.
3. **Student.** Request the item. Open the queue as the manager to show the missing-document state. Switch back to the student, download and acknowledge the PDF, then return to the queue and approve as manager. Open the reservation timeline to show the recorded approval actor and time. Show the names of missing required documents before acknowledgement, then demonstrate the enabled action.
4. **Faculty.** Use a different available item. Prepare a pending student request, then submit an overlapping class-session request as faculty. Open the student's history and the faculty's reservation to show the two outcomes. Keep the earlier approved student booking intact.
5. **Security auditor.** Use Postman to check a current checkout using the printed equipment code, then try an unknown code with the same token. After the lab manager records the return, check that authorization for the known item is false.

## Database relationships

| Records | Relationship and purpose |
|---|---|
| Department → Lab → Equipment | One department has many labs, and one lab has many equipment records. Department reports query equipment through labs. |
| User → Department and Lab | A head belongs to a department and a manager belongs to a lab. Borrowers can hold many reservations. |
| Equipment ↔ Tag | Many-to-many through `equipment_tag`. |
| Equipment → Document ↔ User | An item can have many private documents. The `document_user` pivot records the individual download and acknowledgement timestamps. |
| Reservation → Equipment and Users | Each reservation belongs to one item and one borrower, with an optional approver. It records the time window, status, faculty flag, handover and return timestamps. |
| MaintenanceLog → Equipment, User, Reservation | Every log has one item and reporter. The optional reservation records who had the item at breakdown time. |
| Lab → Equipment → Reservation | Has-many-through: `$lab->reservations` aggregates bookings across items in that lab. |
| User → Sanctum tokens | A user can own multiple tokens. Checkpoint routes check the required token ability. |

## Earlier delivery checkpoints

The following table records the pre-quality-of-life delivery checkpoints. For the complete eight mandatory requirements, original Phase 0–9 mapping and five-role action matrix, see [QUALITY_OF_LIFE.md](QUALITY_OF_LIFE.md).

| Task | Implementation and evidence |
|---|---|
| A1 | Equipment-row lock, reservation refresh and overlap recheck in `ReservationService`. `ReservationRulesTest` and `ConcurrentApprovalTest`. |
| A2–A4 | Maintenance transitions honor open logs and checkouts. Heads see their own department's equipment, and photo URLs come from the public disk. `SystemCompletionTest`. |
| A5 | Natural keys for users, departments, labs, tags and demo equipment, transactional seed data, pivot rows attached only when absent. `DemoSeederTest`. |
| A6 | A faculty member explicitly selects **Faculty class session**. Once repair and overlap checks pass, the transaction rejects only overlapping pending student requests for that item. The class request still needs approval and compliance. `SystemCompletionTest`. |
| B1–B4 | Inline SVG QR, policy-protected print label, checkpoint docs and Postman collection, and a stable equipment-binding 404. `CheckpointApiTest` and `SystemCompletionTest`. |
| C1–C7 | Responsive role sidebar and scoped dashboards, shared empty states and confirmations, styled auth/profile pages, scrollable tables, protected reservation details and timeline, labels, keyboard focus and status badges. |
| D1–D3 | Feature tests cover access, uploads, private files, priority and concurrency. This guide covers manual checks, the demo and database relationships. |

## Known limitations

- Displacement only changes existing pending student requests at the time of a valid class-session request. The software records the rejection reason for the student; email/in-app notifications belong to the follow-up scope.
- New lifecycle actions record the approver, handover/return managers and exact times. Historical records from before the additive migration can have missing actor/time data; the UI must not invent those values.
- Seeded checkouts are relative to the first seed. A rerun preserves existing data and does not move those dates forward.
- The QR is an equipment identifier. Checkpoint authorization comes from the authenticated API and the current reservation, so scanning a QR with a generic camera only reveals the item's code.
- The local example uses the log mailer. Configure a real mail transport before demonstrating password reset by inbox delivery.

## Additional quality-of-life demo

1. As student/faculty, search a QR code globally, open its week calendar, save it to the wishlist, download required PDFs and use Mark all as read. Show the progress bar, approved ICS download and Reserve again on a completed booking.
2. As manager, select a compliant and noncompliant pending request together. Bulk approve and show the per-row skipped reason. Find an active checkout at the counter by QR text, record damage at return, then show the linked maintenance entry and resolve it. The watcher receives an availability notice.
3. As manager, import the CSV template with new serials, clone an item with another unique serial and print the lab QR labels. Schedule a closure that does not overlap an existing approved booking; show that an intersecting request is rejected.
4. As head, select a report period, show utilization/charts/recorded repair costs and download/print the scoped reports.
5. Change dark mode, reopen a filtered list to show remembered choices, clear it, press `/` for search, and show the notification inbox and activity history. Run `php artisan schedule:work` in another terminal for scheduled notices.

Detailed behavior, evidence and target-device checks: [ADDITIONAL_FEATURES.md](ADDITIONAL_FEATURES.md).
