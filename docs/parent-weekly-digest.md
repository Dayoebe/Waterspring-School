# Parent weekly digest

The weekly digest is disabled by default. An administrator configures the school delivery day and time, and each verified guardian explicitly opts in per child through the family portal.

`ParentDigestBuilder` starts from active, verified `parent_records` rows with `receives_digest = true`. Attendance is included only when `can_view_attendance` is granted. Assignments and published, approved results are included only when `can_view_academics` is granted. Each child is aggregated independently so a guardian never receives another child's records through a shared email payload.

`digests:send-weekly` runs through the scheduler and queues encrypted `SendParentWeeklyDigest` jobs. The unique guardian/week delivery key prevents duplicate sends. Delivery rows record queued, sent, skipped, and failed states without storing recipient email addresses or the private digest body. Failed deliveries can be retried by an authorised administrator.

Parents can preview the same authority-filtered structure before delivery. Email output has responsive HTML and plain-text versions. Existing notices, school events, attendance, assignment submissions, published results, and approved recognitions remain the source records; the digest does not duplicate them.
