# School events, RSVP, and permission slips

The existing `school_events` table remains the event source of truth. Events now have draft, published, cancelled, and completed states; categories, audience and class targeting; optional capacity; response deadlines; RSVP, consent, and emergency-contact requirements; and retained cancellation details.

Publishing an RSVP or consent event prepares one response row per targeted active student. Guardians see only students connected through an active, verified `parent_records` relationship. Consent additionally requires `can_consent_events`. The recorded response retains the acting guardian, student, consent decision and timestamp, while capacity is checked inside a locked transaction. Emergency contacts are required only when configured for an attending student.

Administrators can review pending and completed responses, mark event attendance, cancel or reschedule events, and mark them complete. Sensitive mutations use the shared audit trail.

`events:send-reminders` runs hourly and queues encrypted, replay-safe reminder jobs. Delivery records prevent duplicates for the same event time, guardian, and student response; a rescheduled date receives a new reminder key. Failed reminders are eligible for automatic requeue while the event remains inside the reminder window.
