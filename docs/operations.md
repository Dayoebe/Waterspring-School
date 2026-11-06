# WaterSpring operations

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

Workers retry queued mail three times with increasing backoff. Mail queue payloads are encrypted with `APP_KEY`. Inspect failures with `php artisan queue:failed`, retry a reviewed job with `queue:retry`, and use `queue:forget` only after recording why it is being discarded.

The scheduler records a heartbeat every minute. `GET /health` returns only a coarse status; `php artisan ops:health` provides operator detail and exits non-zero when the database, heartbeat, or queue thresholds fail.

## Encrypted backups and restore tests

Generate a dedicated backup encryption key (do not reuse `APP_KEY`):

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Store it as `BACKUP_ENCRYPTION_KEY` in the server secret store, set `BACKUPS_ENABLED=true`, and keep `storage/app/private/backups` outside the public web root. `ops:backup` writes an authenticated XChaCha20-Poly1305 encrypted SQL dump and SHA-256 manifest. The scheduler creates one daily and prunes files after the configured retention period.

`ops:backup-verify` verifies the checksum, decrypts the most recent backup, restores it into a randomly named `waterspring_restore_test_*` database, checks its table inventory, and drops only that disposable database. It runs weekly when backups are enabled. Copy encrypted backups and manifests to separate encrypted off-site storage; local retention is not disaster recovery.

## HTTPS and headers

Production defaults to HTTPS enforcement. Set `FORCE_HTTPS=true`, use an `https://` `APP_URL`, and terminate TLS at the web server or a trusted proxy. If TLS terminates upstream, set `TRUSTED_PROXIES` to the explicit proxy IP/CIDR list (or `REMOTE_ADDR` only when the application is reachable exclusively through that proxy); do not use a wildcard on an internet-reachable origin. Confirm Laravel sees forwarded HTTPS correctly before accepting traffic. Responses include CSP, clickjacking, MIME-sniffing, referrer, permissions and production HSTS protections.
