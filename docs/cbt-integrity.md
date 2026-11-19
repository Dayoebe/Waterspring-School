# CBT integrity and operations

WaterSpring's CBT module separates assessment management, student exam selection, the exam interface, result viewing, and printable result reports. Access is school-, role-, class-, subject-, publication-, and student-scoped.

## Student and result ownership

Every saved answer carries the authenticated user, assessment, attempt number, question, and attempt-session relationship. Database constraints prevent two answer rows for the same question in one attempt. Server-side checks reject questions that do not belong to the selected assessment and reject attempts belonging to another student, school, or paper.

Students can see only assessments published to their eligible class/subject and only their own attempts/results. Parents resolve CBT results through existing parent-child links. Staff result access is restricted by permissions and teacher assignments. Never accept a user, student, school, score, or attempt identifier from the browser without re-resolving it through these relationships.

## Attempt lifecycle and concurrency

Attempt sessions track status, attempt number, start/expiry/submission times, last activity, and a state revision. The application uses transactions, row locks, and revision checks around attempt state so that duplicate tabs or concurrent autosaves cannot silently overwrite a newer state. Only one in-progress attempt per student/paper is treated as active, and submitted or expired attempts cannot continue receiving normal answer updates.

Question order and answer state are tied to the attempt. Submission is server-authoritative and finalization is idempotent. Result publication is separate from exam publication, so completing a paper does not automatically make its result visible.

## Read-only integrity audit

Run the built-in audit before an examination window and after any incident:

```bash
php artisan cbt:integrity-check
php artisan cbt:integrity-check --assessment=123
```

It checks answer/question ownership, school ownership, attempt linkage, submitted-answer linkage, duplicate active attempts, expired in-progress attempts, closed attempts without submitted answers, and published papers without questions. It makes no changes and exits non-zero when violations exist.

## Preparing for a large sitting

For a planned 200-student sitting:

1. use production-like infrastructure and a restored copy of the schema with anonymized test users;
2. confirm the queue and scheduler are healthy, database connections are sized for PHP worker concurrency, and storage/log disks have headroom;
3. publish the exact paper only after verifying class, subject, duration, attempt limit, question count, and result-publication state;
4. run `cbt:integrity-check` and complete a controlled end-to-end CBT rehearsal;
5. load-test login, exam start, periodic saves, submission bursts, reconnects, duplicate tabs, expiry, and result viewing with representative latency;
6. monitor application errors, slow queries, database locks/connections, CPU, memory, network, and response latency during a rehearsal; and
7. keep an invigilation/incident procedure that records affected student, paper, attempt, timestamps, and evidence without editing raw answers directly.

The repository contains integrity and concurrency protections, but it does not contain a universal guarantee that one server can support 200 simultaneous candidates. Capacity depends on the deployed PHP workers, database, network, autosave frequency, question media, and monitoring. A measured rehearsal is the release gate.

## Production verification

Run the integrity check and complete a controlled rehearsal before release:

```bash
php artisan cbt:integrity-check
```

Do not use destructive migration commands against operational school data.
