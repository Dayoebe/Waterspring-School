# Authentication and access

WaterSpring supports administrator-managed portal accounts and public account applications. Email ownership verification is not part of either flow.

## Signing in

Users sign in at `/login` with either their unique username or email address and password. Usernames are lowercased, must be 3–50 characters, and may contain letters, numbers, periods, underscores, and hyphens. Existing accounts without a username can continue using email until a username is assigned from the relevant student, parent, teacher, administrator, readiness, or profile screen.

Email addresses remain required, syntactically valid, and unique because password resets and queued notices use them. They do not need to be verified before an approved user can enter the portal. Placeholder addresses are excluded from workflows that require deliverable mail.

## Administrator-created accounts

Administrators can create students, parents, teachers, and administrators directly. These accounts do not pass through public application approval and do not require email verification. The creator should assign the correct school, role, related student/teacher/parent record, username, and initial password.

Accounts using the default password or marked `requires_password_change` are restricted until the password is changed. Locked accounts and graduated students are also stopped by the shared account-security middleware.

## Public registration and approval

The public `/register` form accepts applications for student, parent, or teacher access. Registration:

1. creates a normal user record with the submitted school, username, email, and password;
2. assigns the zero-permission `applicant` role;
3. records the requested role in `account_applications`; and
4. marks the application as under review.

Applicants can sign in, but `PreventPendingAccountAccess` immediately logs them out of protected browser routes and returns HTTP 403 for protected API access. They have no dashboard, school data, or role permissions while approval is pending.

Authorized staff review applications under **Account Applications**. Approval replaces the applicant role with the requested role and creates the required student, parent, or teacher record. Student approval also captures class, optional section, admission date, and admission number. Rejection retains the application history for staff review.

## Roles and authorization

The current role catalogue is:

- `super-admin` and legacy-compatible `super_admin`;
- `principal`;
- `admin`;
- `teacher`;
- `student`;
- `parent`;
- `applicant`; and
- basic `user`.

Permissions are defined in `app/Support/PermissionMatrix.php` and synchronized with:

```bash
php artisan permissions:sync-matrix
php artisan permissions:audit-access
```

A role name alone does not grant access. Protected routes combine authentication, pending/locked/default-password/graduation checks, school context, teacher restrictions, permissions, and model policies. School-scoped queries must continue to filter by the authenticated school; super-administrator access must still resolve an explicit school context.

## Password recovery and mail

Password recovery remains email-based at `/forgot-password`. It requires a working mail transport and a queue worker only where the selected mail path queues delivery. If an account has an undeliverable or placeholder email, an administrator must first replace it with a usable unique address or reset the account through an approved administrative process.

## Production security checks

After changing authentication or roles, run at minimum:

```bash
php artisan permissions:audit-access
php artisan route:list
```

`permissions:audit-access` creates and normally deletes temporary role users while it checks the menu. Run it only in a controlled pre-production environment, never against operational school records.
