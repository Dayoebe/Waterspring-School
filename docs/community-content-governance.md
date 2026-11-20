# Community content governance

Community features must use the shared review and media boundaries before content is exposed beyond its author or intended recipient.

## Review workflow

`ContentApprovalService` records versioned submissions in `content_reviews`. A pending submission is idempotent for the same subject and workflow. Approval, rejection, and change requests require the `moderate community content` permission, remain school-scoped, and write immutable audit events. Rejected or approved submissions are never rewritten; edited content is submitted as a new version.

Module records remain the source of truth for their content and publication state. A module may publish only after its current review is approved and any module-specific consent checks pass.

## Visibility levels

- `private`: uploader and authorised moderators only.
- `recipient`: uploader, moderators, and the named subject.
- `guardians`: the named subject and verified, active guardians. Portfolio media additionally requires `can_view_portfolio` on the guardian link.
- `school`: authenticated users in the same school.
- `public`: available without authentication only after explicit publication approval.

These levels are not interchangeable. In particular, school-wide or public publication must never be inferred from a guardian relationship or ordinary authentication.

## Managed media

Sensitive community uploads use `ManagedMediaService`. Files are MIME-allowlisted, size-limited, checksummed, assigned an unguessable filename, and stored on the non-public `local` disk. `MediaAssetController` performs the audience check before streaming a file. Executable and unrestricted file types are rejected.

The existing public media library continues to use `media_assets`. Its legacy records are explicitly backfilled as approved public assets, preserving existing website behaviour without making new community uploads public by default.
