# Admissions, accountability, appointments, and transport

These operational modules are implemented in the authenticated portal and share the application's school context, permission checks, queued mail, and database-backed tests.

## Admissions pipeline

The public Admission page renders the local registration form when a configured school and entry structure are available; otherwise it falls back to the school's external application/contact route. Staff manage submitted registrations under `/admissions/registrations`.

The supported lifecycle is `pending`, `reviewed`, `interview_scheduled`, `interviewed`, `offered`, `accepted`, `declined`, and `enrolled`. Staff can upload/review documents, add internal notes, schedule and complete interviews, issue a time-limited tokenized offer, decline with a reason, confirm the admission fee, and convert an accepted application once into linked student and parent records.

Conversion is transactional, requires accepted status plus fee confirmation, validates the selected class/section against the same school, assigns the student role and academic-year record, links or creates the parent, assigns subjects, and requires the new student to change the generated initial password. Placeholder admission emails are flagged so they are not treated as deliverable notification addresses.

The admission fee confirmation is evidence captured by admissions staff; it is not a Paystack/Flutterwave transaction and does not replace the school-fee ledger.

## Audit and accountability

The school-scoped `/audit-log` records material events currently instrumented in these categories:

- marks and result publication;
- invoice changes, fee approval, payments, reversals, and reconciliation;
- role/permission changes;
- sensitive student-support access/actions;
- ClassPilot material approvals; and
- CBT management/integrity events wired through the audit service.

Events retain actor and subject snapshots, before/after values where supplied, metadata, IP/user-agent context, and occurrence time. The viewer supports the configured category choices plus actor, date, and text filters and requires `view audit log`. CBT events are recorded and appear in the unfiltered history, although `cbt` is not yet one of the viewer's category dropdown choices. This is an accountability history, not complete database change-data capture: a workflow is covered only when its code records an event through `AuditTrail` or an audited model hook.

## Parent-teacher appointments

Authorized staff/teachers publish availability slots. A parent can request an available future slot only for a child linked to that parent. The workflow supports requested, confirmed, declined, cancelled, and completed appointments; participant notifications; meeting/follow-up notes in the authorized workspace; and hourly reminders for upcoming confirmed appointments.

Slot booking and status changes are transactional to prevent two active bookings from consuming the same slot. The queue worker and scheduler must run for notifications and reminders.

## Transport and authorized pickup

Transport managers can create routes and ordered stops, assign a student to an active route/stop and direction, deactivate assignments, authorize/revoke pickup guardians, verify a guardian with a stored pickup code, and record boarded/arrived/dropped-off events. Arrival and drop-off events queue parent notifications when a deliverable linked-parent email exists.

Parents can see only transport assignments, guardians, and movement events for linked children. Pickup codes are sensitive operational credentials: disclose them only through an approved process, rotate after suspected exposure, and do not place them in general notices or logs.

## Regression checks

Run these only against a disposable test database:

```bash
php artisan test tests/Feature/Admissions
php artisan test tests/Feature/Administration
php artisan test tests/Feature/Appointments
php artisan test tests/Feature/Transport
```

Production acceptance must also verify actual mail delivery, scheduler execution, role-specific browser access, and school boundary behavior.
