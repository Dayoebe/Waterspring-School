# Watersprings School

Public website, teaching and school-operations platform for **Watersprings International School Akure**, built with Laravel, Livewire and Blade.

The repository combines a verified public school website with a multi-school, role-scoped management portal. It includes academic administration, teaching and curriculum tools, results and CBT, admissions, finance and online payments, family services, communications, audit history, operational monitoring, ClassPilot AI, and public Open Learning. Each installation still requires its own school records, academic structure, users, mail, payment credentials, and production infrastructure.

## Public website

| Page | URL | Content |
| --- | --- | --- |
| Home | `/` | School introduction, learning stages, values and family FAQs |
| About | `/about` | History, mission, vision, Christian ethos and leadership |
| Team | `/team` | Public profiles for staff marked visible by the school |
| Academics | `/academics` | Early Years, primary Key Stages, College Years 7–9, practical learning and clubs |
| Open Learning | `/open-learning` | Public lessons and assessments grounded in published school curriculum |
| Why Watersprings | `/why-watersprings` | Learning environment, individual care and school values |
| Admission | `/admission` | Application guidance, fee categories and prospectus access |
| Prospectus | `/prospectus` | School prospectus: curriculum, facilities, uniforms, attendance, transport and clubs |
| Gallery | `/gallery` | Photographs from the school's existing website |
| Contact | `/contact` | Akure address, telephone numbers, email addresses, map and visit guidance |

Login, registration and password recovery pages use the public school identity. Approved users can sign in with email or username. Public registration creates a restricted applicant account which receives no portal access until an authorized administrator approves the requested student, parent, or teacher role. Email ownership verification is not required for approved or administrator-created accounts.

Public pages include canonical URLs, social metadata and structured data. Discovery endpoints include `/sitemap.xml`, `/sitemap-pages.xml`, `/sitemap-images.xml`, `/robots.txt`, `/llms.txt`, `/llms-full.txt` and `/ai.txt`. A web app manifest, service worker and offline page are included.

### Editing public content

- [config/public-school.php](config/public-school.php): school identity, contact details, programmes, values, FAQs and gallery list.
- [resources/views/livewire/site](resources/views/livewire/site): public page templates, rendered through `PageController`.
- [resources/views/partials](resources/views/partials): shared navigation, footer and content sections.
- [public/images/watersprings](public/images/watersprings): locally stored school photographs and branding.

`SiteSettings::forPublicSite()` reads the public configuration independently of dashboard school settings. Dashboard settings do not currently control the Watersprings public content or gallery.

See [public-site migration notes](docs/watersprings-public-site.md) for content sources, implementation details and validation. The college flyer is an archived **2025/2026** announcement; current places, fees and admission dates must be confirmed with the school.

The public Prospectus page (`/prospectus`) presents the school’s PDF content as accessible web sections, including curriculum, uniforms, the daily timetable, attendance, transport and clubs. The original PDF remains available from the page.

## Management platform

The following modules are present in the codebase. Their availability depends on school setup, assigned roles and permissions.

| Area | Implemented modules |
| --- | --- |
| Identity and access | Email/username login, administrator-managed accounts, public applications, role/permission assignment, account locks, forced password change and school context |
| School administration | Schools, school/site settings, academic years, terms/semesters, class groups, classes, sections, subjects and staff directory |
| People and progression | Students, parents, teachers, administrators, parent-child links, admissions, promotions and graduation |
| Admissions | Public registration, documents, interviews, internal notes, status history, offers, acceptance, fee confirmation and conversion to student/parent records |
| Teaching and curriculum | Assignments, subject allocation, syllabi, schemes of work, timetables, calendar, clubs, resources and teacher responsibilities |
| Assessment and results | Exams, exam slots/papers, CA1/CA2/exam scoring, grade systems, spreadsheet entry/import, publication, reports, exports and history |
| Computer-based testing | Assessment authoring, publication, timed student attempts, autosave/concurrency controls, scoring, result publication, printing and integrity auditing |
| Finance and payments | Fee categories/catalogue approval, class or individual invoicing, waivers/fines, collections, manual payments, ledger, receipts, reversals, reconciliation, Paystack and Flutterwave |
| Student and family services | Attendance, discipline, behaviour reports, student support, parent-teacher appointments, transport, authorized pickup and parent welfare views |
| Communication | Notices, broadcasts, assignment reminders, fee/payment messages, contact enquiries, support reminders and transport/appointment alerts |
| Accountability and reporting | School-scoped audit log, dashboard analytics, executive/enrollment/performance reports, media library and gallery management |
| AI and public learning | Role-scoped ClassPilot workspaces and public curriculum-grounded Open Learning with explicit saved progress |
| Operations | Queue/scheduler health, security headers, HTTPS enforcement, encrypted backups, restore verification and maintenance commands |

The permission matrix defines super-admin, principal, admin, teacher, student, parent, applicant and basic user roles. Access is enforced through route middleware, permissions and policies; a role does not automatically receive every action in a module. See [app/Support/PermissionMatrix.php](app/Support/PermissionMatrix.php).

See the [documentation index](docs/README.md) for workflow and deployment guides, including [authentication and access](docs/authentication-and-access.md), [fees and payments](docs/fees-and-payments.md), [CBT integrity](docs/cbt-integrity.md), and [school workflows](docs/school-workflows.md).

### Fee payment ledger

Fee collections are recorded as append-only payment transactions with receipt numbers, cashier and payer snapshots, payment methods, external references and reconciliation history. Corrections create linked reversal transactions; payment and reconciliation rows cannot be edited or deleted. The historical `fee_invoice_records.paid` value is retained only as a derived compatibility projection, and existing non-zero balances are imported into the ledger during migration.

The `/fees` workspace combines the catalogue, student invoices, and collections. Approved catalogue items can carry fixed or percentage waivers and fines, then be billed to a whole class/section or an individual student. Staff can filter paid/partial/unpaid balances by class and record manual payments; parents see linked-child invoices and outstanding balances.

Paystack and Flutterwave hosted checkout are integrated with server-side initialization and verification, signed webhook handling, idempotent fulfillment, amount/currency checks, immutable receipts, and tenant-scoped access. Each gateway is enabled independently only after a deployment supplies its own secrets and public HTTPS URLs. See [Fees and payments](docs/fees-and-payments.md), [Paystack fee payments](docs/paystack-fee-payments.md), and [Flutterwave fee payments](docs/flutterwave-fee-payments.md).

Dashboard layouts, forms, tables, and navigation share a responsive light/dark design system. See [Dashboard design](docs/dashboard-design.md) for conventions and validation coverage.

## Requirements

| Dependency | Project requirement |
| --- | --- |
| PHP | **PHP 8.2 or newer**; dependencies are resolved against PHP **8.2.12**, matching local XAMPP |
| Composer | Composer 2 |
| Database | MySQL or MariaDB with PHP's `pdo_mysql` extension |
| Node.js | Node 20 or 22 is compatible with the locked Vite version |
| Frontend packages | Install with `npm ci` using `package-lock.json` |

The application currently uses Laravel 12, Livewire 3, Tailwind CSS 3, Vite 8 and Font Awesome. Livewire provides Alpine at runtime; the application JavaScript intentionally does not start a second Alpine instance. Consult `composer.lock` and `package-lock.json` for the exact installed versions.

The initial migration imports [database/schema/baseline-schema.sql](database/schema/baseline-schema.sql), which uses MySQL/MariaDB syntax. SQLite is not a supported fresh-install path for this baseline. Use `composer check-platform-reqs` to check PHP and extension requirements against the installed packages.

Composer’s `config.platform.php` is pinned to `8.2.12` so installing or updating from a newer CLI PHP does not select packages incompatible with the web server. On local XAMPP, validate with `/opt/lampp/bin/php /usr/local/bin/composer check-platform-reqs`. Keep platform checks enabled.

## Local setup

Clone the repository and install its dependencies:

```bash
git clone https://github.com/Dayoebe/Waterspring-School.git
cd Waterspring-School
cp .env.example .env
composer install
npm ci
php artisan key:generate
```

Create a dedicated, empty MySQL/MariaDB database. Set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` in `.env`, and set `APP_URL` to your local application URL.

Initialize the schema and role matrix:

```bash
php artisan migrate
php artisan permissions:sync-matrix
php artisan storage:link
```

Create the first super-admin interactively:

```bash
php artisan watersprings:create-super-admin
```

Log in at `/login`, then configure the school and its academic structure. The schema does not populate a Watersprings school, classes or student records automatically. Existing accounts may log in by email; usernames can be assigned later. New public registrations remain applicants without dashboard access until approved under Account Applications.

Build the frontend and start the application:

```bash
npm run build
php artisan serve
```

Open `http://localhost:8000`. For frontend development, run `npm run dev` in a separate terminal alongside `php artisan serve`.

### Setup limitations

- `database/seeders` is not included. The legacy `watersprings:init` and `watersprings:update` commands call missing seeders, and the update command also performs destructive Git operations. Do not use either command for a current installation. Follow the manual, reviewed deployment procedure instead of `migrate --seed`.
- When no school records exist, Admission links to Watersprings' existing online application form and Contact provides direct contact channels. Existing local Livewire forms render when school records become available. Configure the correct school, entry classes, sections and message recipients before using those forms.
- Dashboard branding and command names use Watersprings. Academic setup, school data and operational configuration still need to be completed.
- `.env.example` uses `MAIL_MAILER=log` and the database queue. Local mail is written to logs; it is not delivered to recipients. A queue worker and scheduler must be running for queued mail and reminders.

## Optional services and deployment

Cloudinary settings (`CLOUDINARY_URL`, `CLOUDINARY_FOLDER`, and `CLOUDINARY_ROOT_FOLDER`) are available for dashboard image uploads. Gallery images use `CLOUDINARY_FOLDER`; other images are organized beneath `CLOUDINARY_ROOT_FOLDER`. Image attachments use public Cloudinary URLs, while non-image files remain on application storage. Mail, SMS placeholders, payment providers, and other service settings are defined in [config/services.php](config/services.php). Configure only integrations the deployment actually uses. ClassPilot and Open Learning provider settings are documented in [ClassPilot](docs/class-pilot.md) and [Open Learning](docs/open-learning.md).

For deployment:

- Point the web server's document root to `public/` and use a PHP runtime compatible with the lockfile.
- Set `APP_ENV=production`, `APP_DEBUG=false` and the correct `APP_URL`. Canonical links and sitemaps use the application URL.
- Configure database access and real mail delivery, and make `storage/` and `bootstrap/cache/` writable by the application.
- Keep uploaded files in persistent `storage/app/public`, run `php artisan storage:link`, and verify `public/storage` points to that directory. The link is not tracked by Git.
- Install dependencies and run `npm run build`; dependency directories, compiled frontend assets and `public/hot` are excluded from Git.
- Run the database queue worker and scheduler under supervision; do not serve production traffic with `QUEUE_CONNECTION=sync`.
- Configure encrypted backups, off-site retention, restore tests, health monitoring and HTTPS using the [operations runbook](docs/operations.md).

Keep `.env` local. Generate a fresh application key for a new installation: an earlier repository commit contained an `.env` file, so removing it from the current tree does not remove that historical key.

## Project layout

```text
app/
  Console/Commands/       Setup and maintenance commands
  Http/Controllers/       Public pages, authentication and management endpoints
  Livewire/               Interactive management and form components
  Models/                 School and operational records
  Policies/               Authorization rules
  Services/               Reusable queries, actions, integrations, and data formatters
  Support/                Public content, SEO and permission helpers
config/                   Application and public school configuration
database/
  migrations/             Schema and data migrations
  schema/                 MySQL/MariaDB baseline
public/                   Web entry point, images and PWA files
resources/
  css/                    Tailwind and application styles
  js/                     Vite JavaScript entry point
  views/                  Blade layouts, public pages and component views
routes/                   Web, API and console routes
docs/                     Implementation and migration notes
```

## Validation

GitHub Actions installs production PHP dependencies, builds production assets, compiles Blade views, validates the Composer manifest, and audits Composer and NPM dependencies on every pull request and push to `main`.

Useful verification and build commands:

```bash
composer check-platform-reqs
php artisan route:list
php artisan migrate:status
php artisan view:cache
node --check public/service-worker.js
npm run build
```

Before release, verify authenticated role flows, live form submission, mail delivery, queues, scheduler execution, payment-provider acceptance, PWA installation, and production health endpoints in a controlled pre-production environment.

## License and attribution

Distributed under the [MIT License](LICENSE). Preserve the existing license and upstream attribution. School content and photographs were sourced from the Watersprings website; source references are recorded in [the migration notes](docs/watersprings-public-site.md).

### XAMPP browser access

Use `http://localhost/WaterSpring/public/` for this checkout, or point a virtual host directly at `public/`. The public `.htaccess` forwards application routes to Laravel. The Apache user must be able to write to `storage/` and `bootstrap/cache/`; the local setup grants this with filesystem ACLs rather than world-writable permissions.

Livewire scripts and update requests use the application’s base path, so both a virtual host and XAMPP subdirectory access work.
