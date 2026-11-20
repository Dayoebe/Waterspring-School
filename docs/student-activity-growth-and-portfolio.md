# Shared student activity record, growth timeline, and portfolio

## Canonical activity record

`student_activity_records` is the shared source for achievements, observations, and student-submitted milestones. A record keeps its student, date, academic year, term, subject, club, sport, and school-event context; approval history; private evidence; and explicit eligibility for timeline, portfolio, celebration, digest, display, future house points, and future memory-book workflows.

Classifications have different trust levels:

- `verified` can be created only by a user with approval authority and is approved immediately.
- `observed` is a teacher or administrator contribution and remains pending until reviewed.
- `student_submitted` is always tied to the signed-in student and remains pending. Students cannot set downstream eligibility flags.

Existing and future Face of the Week recognitions link through the unique `student_recognition_id`. Linked records resolve their displayed title and description from the recognition, so recognition copy is not duplicated. The migration linked the existing recognition history, and the recognition workflow synchronises the link after later saves or publication changes.

## Private growth timeline

The timeline is chronological and keeps approved verified records distinct from observations and student submissions. It supports category, classification, and academic-year filters. Teachers are limited to students in classes assigned to them; administrators can contribute and approve school-wide. Students see their own approved history and their own pending submissions.

Guardian access requires an active, verified relationship with `can_view_growth_timeline`. This authority is disabled by default and can be managed independently on the guardian relationship screen. Evidence is stored through protected `media_assets`; pending evidence is recipient-only, and approved evidence becomes accessible only to specifically authorised guardians. Controlled PDF development-report exports contain approved timeline-eligible records and create an audit event.

Approved records marked `eligible_digest` enter a guardian's weekly digest only when that guardian also has growth-timeline authority.

## Student portfolio

Students and authorised teachers can submit images, PDFs, Word documents, PowerPoint presentations, text documents, and supported video. Each submission creates version 1; later uploads append a numbered version and return the item to pending review. No version is overwritten or deleted when an item is archived.

Portfolio items retain category, subject, academic year, term, submission source, teacher comment, reviewer identity, review time, visibility, and archival history. Files remain recipient-only while pending or rejected. Approval changes all item versions to guardian visibility, still enforced by an active verified relationship with `can_view_portfolio`. Archival removes guardian visibility without deleting files or versions.

School administrators configure per-student and per-file limits. Quota checks count the protected file sizes already stored for the student, including archived versions because they still occupy storage. Preview and download both pass through `MediaAccessService`; downloads cannot bypass relationship authority by knowing a file path. Controlled portfolio PDF exports contain approved, non-archived metadata and are audit logged.

## Permissions

- Staff: `view student growth timeline`, `contribute student records`, `approve student records`, `manage student portfolio`.
- Students: `view own growth timeline`, `view own portfolio`, `submit own portfolio`.
- Guardians: `view child growth timeline`, `view child portfolio`; relationship flags still decide access child by child.

The user-facing routes are `/students/growth-timeline` and `/students/portfolio`, both under the complete authenticated school-context middleware chain.
