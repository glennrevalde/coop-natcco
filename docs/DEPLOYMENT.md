# Deployment guide

## Environments

| Environment | Database file | Keys | Purpose |
|---|---|---|---|
| Local (XAMPP) | `coop_natcco_sample.sql` | the keys in `config/config.php` | development, demonstrations, running the end-to-end tests |
| Staging (optional) | `coop_natcco_sample.sql` or a copy of production | the staging keys, or the production keys when testing a production copy | trying updates before go-live |
| Production | `coop_natcco_clean.sql` | new keys from `scripts/generate-keys.php` | live system |

Never load the sample SQL into production. Never copy production data to a laptop or to staging without
the Data Protection Officer's approval, and never without encryption.

## Go-live checklist

**Server**
- [ ] HTTPS works and HTTP redirects to HTTPS. `force_https` is `true`.
- [ ] `app_env` is `production` and `app_debug` is `false`.
- [ ] The configuration file is outside the web folder (`/var/www/coop-natcco-private/config.php`) with mode 640.
- [ ] `storage_path` is outside the web folder. Only the web server account can write to it.
- [ ] The bundled `config/config.php`, `database/coop_natcco_sample.sql` and `database/seeders/` are removed from the server.
- [ ] `scripts/check-requirements.php` shows only `[ OK ]` lines.

**Database**
- [ ] MySQL listens on 127.0.0.1 or a private address only. Port 3306 is closed in the firewall.
- [ ] The application uses `cnpl_app` (from `grants.sql`), not root.

**Keys and backups**
- [ ] New encryption, index and backup keys are generated and stored in two safe places.
- [ ] The cron jobs for `cron.php` and `backup.php --verify` are installed.
- [ ] The first backup was created, verified and copied off the server.
- [ ] A test restore was done into an empty database (see BACKUP-RECOVERY.md).

**Accounts and settings**
- [ ] The `superadmin` password was changed and MFA enrolled. A second Super Administrator exists.
- [ ] Mail is set to SMTP and a test email arrived. Contact details, DPO details and retention periods are filled in.
- [ ] The demo notice is empty. The official About, History and Leadership content was entered by authorized staff.

**Verification after go-live**
- [ ] Open these addresses and confirm they return 404:
  - https://cnpl.example.org/config/config.sample.php
  - https://cnpl.example.org/storage/
  - https://cnpl.example.org/database/schema/schema.sql
- [ ] Check the response headers (for example at securityheaders.com). Expect CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy and Permissions-Policy.

## Updating the application

1. Announce a short maintenance window. Take a backup with `php scripts/backup.php --verify` and copy it off the server.
2. Copy the new release to a new folder, for example `/var/www/coop-natcco-2026-12-01`, and apply the same ownership and permissions.
3. If the release has database changes, it includes a migration file `database/migrations/YYYYMMDD-*.sql`. Apply it with
   an administrator account: `mysql -u root -p coop_natcco < database/migrations/....sql`.
4. Run `php scripts/check-requirements.php` from the new folder.
5. Switch the site to the new folder. Either change `DocumentRoot` and reload Apache, or point a symlink
   `/var/www/coop-natcco` to the new folder.
6. Sign in and check the dashboard, one registry, the public home page and the help desk.

**Rollback.** Switch `DocumentRoot` (or the symlink) back to the previous folder. If a migration ran, restore the
backup taken in step 1 (BACKUP-RECOVERY.md).

## Moving away from MySQL 5.5

The schema avoids features that MySQL 5.5 lacks, so the same files work on MySQL 8.0. To migrate:

1. Install MySQL 8.0 on a private host. Create the database with `utf8mb4_unicode_ci`.
2. Run the grants file, changing `IDENTIFIED BY` to `IDENTIFIED WITH caching_sha2_password BY` if you prefer the newer authentication.
3. Restore the latest backup with `scripts/restore-backup.php` and import the resulting SQL.
4. Point `db.host` in the configuration at the new server. Run `check-requirements.php` and the smoke checks above.
5. Keep the MySQL 5.5 server read-only for one week, then decommission it and securely wipe its disks.

## Monitoring

| Where | What to watch |
|---|---|
| Admin → Security center | Lockouts, brute-force alerts, rejected uploads, CSRF failures, permission denials, export volume |
| Admin → Security center (overview) | The **Audit log integrity** panel recomputes the hash chain of the latest 5,000 entries on every visit and names the first altered entry. Check it weekly. |
| Admin → Backups | The last backup is less than 26 hours old and verified "OK". |
| `storage/logs/app-YYYY-MM-DD.log` | Server errors. Users see a reference such as `ref=5A1D6BE8`, which you can search for here. |
| `storage/logs/security-fallback.log` | Security events that could not be written to the database. This file should stay empty. |
| `storage/logs/cron.log`, `backup.log` | Output of the daily jobs. |
| Apache error log | PHP fatal errors, denied paths. |

## Performance notes

- Lists are paginated and filtered in SQL with indexes on the common filters: region, province, city, status, review status and dates.
- Charts are rendered as SVG on the server and need no JavaScript library.
- Static assets carry a version query string and are cached for 30 days. The service worker caches only static files, never pages or data.
- On busy servers, enable OPcache (`opcache.enable=1`, `opcache.validate_timestamps=0`) and restart PHP after each deployment.
