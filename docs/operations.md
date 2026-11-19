# WaterSpring operations

This runbook describes the operations features implemented in the repository. Paths in the supplied service units target the local XAMPP checkout and must be reviewed for every other server.

## Queue and scheduler

Production uses the database queue. Run migrations before starting workers, then install the two example systemd units in `deploy/systemd`. Adjust the PHP path, service user and project directory for the server, then enable them:

```bash
sudo install -m 0644 deploy/systemd/waterspring-queue.service /etc/systemd/system/
sudo install -m 0644 deploy/systemd/waterspring-scheduler.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now waterspring-queue waterspring-scheduler
```

For this workstation, equivalent user-service units are provided in `deploy/systemd-user` and can be linked without root access:

```bash
systemctl --user link "$PWD/deploy/systemd-user/waterspring-queue.service"
systemctl --user link "$PWD/deploy/systemd-user/waterspring-scheduler.service"
systemctl --user enable --now waterspring-queue waterspring-scheduler
```

`deploy/systemd-user/waterspring-web.service` is a local development convenience for port 8000, not a production web-server configuration. Production should use Nginx or Apache with the document root set to `public/`, a supported PHP-FPM runtime, TLS, and appropriate proxy/header configuration.

Workers retry queued mail three times with increasing backoff. Mail queue payloads are encrypted with `APP_KEY`. Inspect failures with `php artisan queue:failed`, retry a reviewed job with `queue:retry`, and use `queue:forget` only after recording why it is being discarded.

The scheduler records a heartbeat every minute. `GET /health` is a dependency-free liveness check for the hosting platform. `GET /health/ready` checks the database, scheduler heartbeat, and queue thresholds while returning only coarse statuses. `php artisan ops:health` provides operator detail and exits non-zero when a readiness check fails. Configure load balancer routing with `/health`; use `/health/ready` for monitoring and alerting, not for deciding whether the PHP process is alive.

The current schedule also sends assignment reminders hourly, parent-teacher appointment reminders hourly, student-support review reminders daily, cleans invalid results weekly, prunes failed jobs daily, creates encrypted backups daily when enabled, and restore-tests backups weekly when enabled. Confirm the effective schedule with:

```bash
php artisan schedule:list
php artisan ops:health
php artisan queue:failed
```

The application timezone is currently `UTC` in `config/app.php`; scheduled clock times therefore use UTC. Treat a timezone change as an application change and regression-test all date, deadline, reminder, result, payment, and timetable behavior.

## Encrypted backups and restore tests

Generate a dedicated backup encryption key (do not reuse `APP_KEY`):

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Store it as `BACKUP_ENCRYPTION_KEY` in the server secret store, set `BACKUPS_ENABLED=true`, and keep `storage/app/private/backups` outside the public web root. `ops:backup` writes an authenticated XChaCha20-Poly1305 encrypted SQL dump and SHA-256 manifest. The scheduler creates one daily and prunes files after the configured retention period.

The backup service requires the Sodium PHP extension plus working `mysqldump` and `mysql` client binaries. `BACKUPS_DIRECTORY` may override the default only with a reviewed private path writable by the service account. Do not set it to a public directory.

`ops:backup-verify` verifies the checksum, decrypts the most recent backup, restores it into a randomly named `waterspring_restore_test_*` database, checks its table inventory, and drops only that disposable database. It runs weekly when backups are enabled. Copy encrypted backups and manifests to separate encrypted off-site storage; local retention is not disaster recovery.

## HTTPS and headers

Production defaults to HTTPS enforcement. Set `FORCE_HTTPS=true`, use an `https://` `APP_URL`, and terminate TLS at the web server or a trusted proxy. If TLS terminates upstream, set `TRUSTED_PROXIES` to the explicit proxy IP/CIDR list (or `REMOTE_ADDR` only when the application is reachable exclusively through that proxy); do not use a wildcard on an internet-reachable origin. Confirm Laravel sees forwarded HTTPS correctly before accepting traffic. Responses include CSP, clickjacking, MIME-sniffing, referrer, permissions and production HSTS protections.

EthioDeploy's nginx/PHP-FPM runtime is reachable through the platform proxy, and its Laravel guide requires trusting that immediate proxy so forwarded HTTPS is honoured. Set `TRUSTED_PROXIES=*` there and set the Health Check Path to `/health`. In Laravel's proxy middleware, `*` resolves to the immediate caller rather than every address in the forwarded chain; it is appropriate only while the origin remains inaccessible directly. A missing trusted-proxy setting can make Laravel answer the internal HTTP hop with a 308 even though the browser used HTTPS.

## Login sessions and 419 errors

Session cookies default to HTTPS-only when `APP_ENV=production`; set `SESSION_SECURE_COOKIE=true` explicitly in production if you prefer the setting to be visible in the environment. Keep `APP_KEY` unchanged across releases and identical on every application instance.

Use one canonical HTTPS hostname for the whole login flow. Redirect the alternate `www`/non-`www` hostname to it, and leave `SESSION_DOMAIN` unset unless the application must share sessions across subdomains. If TLS is terminated by a load balancer or reverse proxy, configure `TRUSTED_PROXIES` with its exact IP/CIDR entries so Laravel can correctly identify HTTPS.

The default `SESSION_DRIVER=file` is suitable only when requests share one persistent application filesystem and `storage/framework/sessions` is writable by PHP. For multiple application instances, configure a shared Redis or database session store; do not rely on local files or sticky sessions as the only session persistence. Ensure any CDN or page cache bypasses `/login` and does not cache requests that set cookies. After changing environment values, rebuild Laravel's configuration cache and verify in the browser's network panel that `GET /login` sets the session cookie and `POST /login` sends it back.

## Deployment sequence

Use a reviewed release process instead of the legacy `watersprings:init` or `watersprings:update` commands. Both still reference seeders that are not present; the update command also performs destructive Git operations.

For a normal release on a conventional server:

1. back up the database and verify that a recent backup restores;
2. put the application into maintenance mode when the migration requires it;
3. install locked PHP and Node dependencies for the target environment;
4. run `php artisan migrate --force`, `php artisan permissions:sync-matrix --no-interaction`, and `php artisan storage:link`;
5. verify `public/storage` points to the persistent `storage/app/public` directory, which contains uploaded public files;
6. build assets with `npm ci && npm run build`;
7. clear/rebuild deployment caches and restart long-running queue workers;
8. bring the application up, then check `/health`, `ops:health`, logs, queue failures, authentication, and critical role/payment workflows.

### EthioDeploy

Do not add a Dockerfile, Procfile, nginx configuration, or an `artisan serve` command. EthioDeploy detects `artisan`, installs production Composer dependencies, builds Vite assets, and provides nginx plus PHP-FPM. Leave the Build command blank so platform detection runs the locked Composer and npm builds.

For managed MySQL, set the project Start command to:

```bash
php artisan migrate --force && ethiodeploy-start
```

Before the first production run, take and verify a database backup and review `php artisan migrate:status`. The historical exam migration now renames `exam_records` to `legacy_exam_records` instead of deleting it; keep that archive until its records have been independently reconciled and retained under the school's data policy.

`ethiodeploy-start` must remain last because it warms Laravel caches and starts nginx/PHP-FPM. This app uses a database queue and scheduled work; only after confirming the plan can keep background processes alive and has enough memory, use:

```bash
php artisan migrate --force && (php artisan queue:work --tries=3 &) && (php artisan schedule:work &) && ethiodeploy-start
```

On a sleeping/free service, background workers and the scheduler stop while the app sleeps. `QUEUE_CONNECTION=sync` is an alternative only after reviewing mail latency and failure behaviour; it is not a transparent deployment toggle for this application.

Set the Health Check Path to `/health` and accept HTTP 200. Keep `/health/ready` for external monitoring after migrations and background processes are configured. The managed database URL is read from `DB_URL` with `DATABASE_URL` as a compatibility fallback.

Never run `migrate:fresh`, `RefreshDatabase`, or the feature suite against the operational database.

## Cloudinary image storage

Configure `CLOUDINARY_URL` and `CLOUDINARY_FOLDER` for gallery uploads. Dashboard image uploads use Cloudinary folders beneath `CLOUDINARY_ROOT_FOLDER` (default `waterspring`), with gallery images using `CLOUDINARY_FOLDER`. Non-image documents and media remain on the configured application storage.

Run `php artisan cloudinary:migrate-local-images` to preview local image references that can be migrated. Add `--execute` to upload those images and update their database references; local files are retained. Run this from an environment that can access both the database and the local files being migrated.

Images in attachment fields are uploaded as public Cloudinary assets. Do not upload confidential images there; use a private storage and delivery flow for sensitive files.

## Environment-dependent acceptance

Automated checks do not prove external delivery. Before production sign-off, verify real SMTP delivery, password reset, fee/receipt/admin notifications, provider callbacks/webhooks, payment settlement, Cloudinary uploads if enabled, backup off-site copying, restore permissions, proxy HTTPS detection, and monitoring alerts. Keep all provider and encryption secrets outside Git.
