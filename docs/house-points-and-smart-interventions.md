# House Points, Challenges, and Smart Intervention Alerts

## House points and challenges

House membership is recorded per student and academic year. Point requests retain the student, house, rule, reason, date, academic period, requester, and review outcome. Approved points are copied into `house_point_transactions`, an append-only ledger whose model rejects updates and deletion.

Rules define normal point values, duplicate-prevention windows, optional per-term limits, and whether approval is always required. Deductions require the dedicated `deduct house points` permission. Deductions, rule-sensitive awards, and awards worth 25 points or more remain pending until a different user with `approve house point awards` reviews them.

House totals are visible without exposing individual students. Individual rankings are staff-only; students and verified guardians see only records belonging to the authorised student accounts. Guardian visibility reuses the conservative growth-timeline relationship authority.

Challenges have start dates, deadlines, participation records, and staff-verified results. Result verification does not bypass the point award rules. Badge definitions may include a minimum point threshold, and every student badge retains the awarding user, reason, academic year, and timestamp.

## Smart intervention alerts

Each rule visibly defines its indicator, comparison operator, threshold, lookback window, minimum required source records, severity, and explanation. Supported indicators are:

- absence count;
- lateness count;
- missing assignment count; and
- approved assessment average.

The generator skips a student-rule evaluation when the minimum source data is unavailable. When a threshold is met, the alert stores a frozen evidence snapshot and a weekly fingerprint prevents duplicate alerts. The system does not create discipline incidents, apply sanctions, or label students.

Teachers see only students within their assigned classes. Principals and administrators may configure rules and run generation. Human reviewers can review or dismiss an alert with notes, assign dated follow-up tasks, record task completion, escalate outstanding work, and resolve the alert only after pending or escalated follow-ups are complete. The update stream is append-only.

The scheduler runs `php artisan interventions:generate` daily at 06:30. It can also be run manually, optionally for one school with `--school=ID`.

## Portfolio prerequisite

The existing Student Portfolio remains the source for student and teacher submissions, protected media, categories and academic context, teacher review, guardian authority, version history, archival, quotas, previews, downloads, and controlled PDF export. This implementation does not duplicate that module.
