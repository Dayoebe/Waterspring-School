# Watersprings School

Public website and school management application for **Watersprings International School Akure**, built with Laravel, Livewire and Blade.

The public website has been adapted using the school's published information and photographs. The repository also contains an existing multi-school administration platform. Watersprings-specific dashboard configuration and data setup are the next stage of work.

## Public website

| Page | URL | Content |
| --- | --- | --- |
| Home | `/` | School introduction, learning stages, values and family FAQs |
| About | `/about` | History, mission, vision, Christian ethos and leadership |
| Academics | `/academics` | Early Years & Foundation Stage, Key Stages 1 and 2, clubs and the archived college announcement |
| Why Watersprings | `/why-watersprings` | Learning environment, individual care and school values |
| Admission | `/admission` | Application guidance, fee categories and prospectus access |
| Gallery | `/gallery` | Photographs from the school's existing website |
| Contact | `/contact` | Akure address, telephone numbers, email addresses, map and visit guidance |

Login, registration and password recovery pages use the public school identity.

Public pages include canonical URLs, social metadata and structured data. Discovery endpoints include `/sitemap.xml`, `/sitemap-pages.xml`, `/sitemap-images.xml`, `/robots.txt`, `/llms.txt`, `/llms-full.txt` and `/ai.txt`. A web app manifest, service worker and offline page are included.

### Editing public content

- [config/public-school.php](config/public-school.php): school identity, contact details, programmes, values, FAQs and gallery list.
- [resources/views/livewire/site](resources/views/livewire/site): public page templates, rendered through `PageController`.
- [resources/views/partials](resources/views/partials): shared navigation, footer and content sections.
- [public/images/watersprings](public/images/watersprings): locally stored school photographs and branding.

`SiteSettings::forPublicSite()` reads the public configuration independently of dashboard school settings. Dashboard settings do not currently control the Watersprings public content or gallery.

See [public-site migration notes](docs/watersprings-public-site.md) for content sources, implementation details and validation. The college flyer is an archived **2025/2026** announcement; current places, fees and admission dates must be confirmed with the school.

## Management platform

The following modules are present in the codebase. Their availability depends on school setup, assigned roles and permissions.

| Area | Implemented modules |
| --- | --- |
| School administration | Schools, school settings, users, role assignments and account applications |
| Academic structure | Academic years, terms/semesters, class groups, classes, sections and subjects |
| People | Students, teachers, parents, admissions, promotions and graduation |
| Teaching | Subject assignments, syllabi, timetables and teacher responsibilities |
| Assessment | Exams, exam papers, exam records, grading, result publication, reports and computer-based testing |
| Finance | Fee categories, fees, invoices and invoice details |
| Student welfare | Attendance, discipline and parent welfare views |
| Communication | Notices, broadcasts, contact messages and admission review |
| Reporting and media | Dashboard analytics, executive reports, enrollment/performance reports, media library and gallery management |
| Maintenance | Database backup/download and commands for migration, permission and student-record maintenance |

The permission matrix defines super-admin, principal, admin, teacher, student, parent, applicant and basic user roles. Access is enforced through route middleware, permissions and policies; a role does not automatically receive every action in a module. See [app/Support/PermissionMatrix.php](app/Support/PermissionMatrix.php).

Dashboard layouts, forms, tables, and navigation share a responsive light/dark design system. See [Dashboard design](docs/dashboard-design.md) for conventions and validation coverage.

## Requirements

| Dependency | Project requirement |
| --- | --- |
| PHP | **8.4.1 or newer** for the current locked dependencies; `composer.json` still declares the older `^8.1` constraint |
| Composer | Composer 2 |
| Database | MySQL or MariaDB with PHP's `pdo_mysql` extension |
| Node.js | Node 20 or 22 is compatible with the locked Vite version |
| Frontend packages | Install with `npm ci` using `package-lock.json` |

The application uses Laravel 10, Livewire 3, Tailwind CSS 3, Vite 6 and Font Awesome. Livewire provides Alpine at runtime; the application JavaScript intentionally does not start a second Alpine instance.

The initial migration imports [database/schema/baseline-schema.sql](database/schema/baseline-schema.sql), which uses MySQL/MariaDB syntax. SQLite is not a supported fresh-install path for this baseline. Use `composer check-platform-reqs` to check PHP and extension requirements against the installed packages.

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

Log in at `/login`, then configure the school and its academic structure. The schema does not populate a Watersprings school, classes or student records automatically.

Build the frontend and start the application:

```bash
npm run build
php artisan serve
```

Open `http://localhost:8000`. For frontend development, run `npm run dev` in a separate terminal alongside `php artisan serve`.

### Setup limitations

- `database/seeders` is not included. The legacy `watersprings:init` command calls missing seeders, so use the manual setup above instead of `watersprings:init` or `migrate --seed`.
- When no school records exist, Admission links to Watersprings' existing online application form and Contact provides direct contact channels. Existing local Livewire forms render when school records become available. Configure the correct school, entry classes, sections and message recipients before using those forms.
- Dashboard branding and command names use Watersprings. Academic setup, school data and operational configuration still need to be completed.
- `.env.example` uses `MAIL_MAILER=log` and synchronous queue processing. Local mail is written to logs; it is not delivered to recipients.

## Optional services and deployment

Cloudinary settings (`CLOUDINARY_URL` and `CLOUDINARY_FOLDER`) are available for media features. Mail providers and other service settings are defined in [config/services.php](config/services.php). Configure only the integrations you use.

For deployment:

- Point the web server's document root to `public/` and use a PHP runtime compatible with the lockfile.
- Set `APP_ENV=production`, `APP_DEBUG=false` and the correct `APP_URL`. Canonical links and sitemaps use the application URL.
- Configure database access and real mail delivery, and make `storage/` and `bootstrap/cache/` writable by the application.
- Install dependencies and run `npm run build`; dependency directories, compiled frontend assets and `public/hot` are excluded from Git.
- Configure a queue worker if you switch to an asynchronous queue connection.
- Review the scheduled weekly `results:cleanup` command in [app/Console/Kernel.php](app/Console/Kernel.php) before enabling Laravel's scheduler.

Keep `.env` local. Generate a fresh application key for a new installation: an earlier repository commit contained an `.env` file, so removing it from the current tree does not remove that historical key.

## Project layout

```text
app/
  Console/Commands/       Setup and maintenance commands
  Http/Controllers/       Public pages, authentication and management endpoints
  Livewire/               Interactive management and form components
  Models/                 School and operational records
  Policies/               Authorization rules
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

Useful verification and build commands:

```bash
composer check-platform-reqs
php artisan route:list
php artisan migrate:status
php artisan view:cache
node --check public/service-worker.js
npm run build
```

The public-site migration was checked with HTTP requests and Chromium at 390px and 1440px widths, including image loading, mobile navigation, FAQ controls and structured data. Those checks do not establish end-to-end coverage for the management platform. Live form submission and PWA installation were not tested.

A `phpunit.xml` configuration exists, but this repository currently has no `tests/` directory. There is no committed automated test suite to run yet. The complete fresh-install procedure has not been validated on a separate empty database.

## License and attribution

Distributed under the [MIT License](LICENSE). Preserve the existing license and upstream attribution. School content and photographs were sourced from the Watersprings website; source references are recorded in [the migration notes](docs/watersprings-public-site.md).
