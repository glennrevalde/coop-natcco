# Backup and recovery

## What must be backed up

| Item | Location | How |
|---|---|---|
| Database | MySQL `coop_natcco` | `scripts/backup.php`, which writes an encrypted, compressed SQL file to `storage/backups` |
| Uploaded files | `storage/uploads` | File copy (rsync, robocopy or the hosting backup), daily |
| Configuration and keys | `coop-natcco-private/config.php` | Offline copy kept in two safe places, updated whenever keys change |

The database backup does not contain uploaded files. Without the keys in the configuration file, encrypted
backups and encrypted fields cannot be read. Never store the keys next to the backups.

## Database backups

**Format.**
1. The file starts as a plain SQL dump of every table, with `DROP TABLE IF EXISTS`, `CREATE TABLE` and `INSERT`.
2. The dump is gzip-compressed.
3. It is then encrypted with AES-256-GCM in 1 MB chunks. Each chunk has its own authentication tag, so any change
   or wrong key is detected.

The resulting file is named `cnpl-backup-YYYYMMDD-HHMMSS-<random>.sql.gz.enc`. Its SHA-256 checksum is recorded in
`backup_records`.

**Creating a backup**
- Manually: Admin → Backups → **Create a backup now** (Super Administrator, password required). Or
  `php scripts/backup.php`.
- Scheduled: run `php scripts/backup.php --verify` daily from cron or Windows Task Scheduler (INSTALLATION.md).
  The `--verify` option decrypts the new file, checks every chunk and the checksum, and records the result.

**Retention.** Backups older than *backup retention days* (30 by default) are deleted, but the newest *minimum
backups kept* (7 by default) are always kept. Change both in Admin → Settings → Backups.

**Off-server copies (required).** After the nightly backup, copy the new files to storage that is not on the web
server. Keep at least 30 daily and 12 monthly copies off the server. Examples:

```
# Linux: copy to a backup host with rsync over SSH
rsync -a /var/lib/coop-natcco/storage/backups/ backup@backuphost:/srv/cnpl/db/
rsync -a /var/lib/coop-natcco/storage/uploads/ backup@backuphost:/srv/cnpl/uploads/
```

```
:: Windows: copy to an external or network drive
robocopy C:\xampp\htdocs\coop-natcco\storage\backups E:\CNPL-Backups\db /E
robocopy C:\xampp\htdocs\coop-natcco\storage\uploads E:\CNPL-Backups\uploads /E
```

**Monitoring.** Admin → Backups shows the status, size and verification result of each backup. A failed
verification creates a high-severity security event.

## Restoring

Restore into an **empty** database with a MySQL administrator account. The application account cannot drop or
create tables.

1. Put the site in maintenance. For example, temporarily replace `index.php` with a notice, or block access in
   the virtual host.
2. Decrypt and check the backup. The configuration must contain the same `backup_key` that created the backup:
   ```
   php scripts/restore-backup.php storage/backups/cnpl-backup-20261201-014500-a1b2c3.sql.gz.enc /root/restore.sql
   ```
   The script stops with "Integrity check failed" if the file is damaged or the key is wrong.
3. Create an empty database and import:
   ```
   mysql -u root -p -e "CREATE DATABASE coop_natcco_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   mysql -u root -p coop_natcco_restore < /root/restore.sql
   ```
4. Check the restored data, for example the counts of users, cooperatives and members, and the latest audit entries.
5. Switch over by either:
   - renaming the databases;
   - or pointing `db.name` in the configuration at `coop_natcco_restore`, and running `grants.sql` for the
     new database name.
6. Restore `storage/uploads` from the file copy of the same date.
7. Securely delete `/root/restore.sql` (`shred -u /root/restore.sql`).
8. Sign in and check the dashboard, a cooperative with attachments, and Security center → Audit log integrity.
9. Record the restoration in the IT log, with its reason and the backup used. If it followed a security incident,
   also note it in that incident's actions in Admin → Incidents.

## Restore test

At least once every quarter, restore the latest off-server backup into a separate test database using the steps
above, then sign in to a test copy of the site pointed at it. Record the date and result in the IT log.

## Recovery targets

| Measure | Target with the default schedule |
|---|---|
| Maximum data loss (RPO) | 24 hours. Run `backup.php` more often (for example every 6 hours) to reduce it. |
| Time to restore (RTO) | About 1 hour for the database at the sample data size, plus the time to copy uploads |

## Key loss and key rotation

- If the `backup_key` is lost, encrypted backups cannot be restored. Keep copies of the configuration file
  offline (step 1 of INSTALLATION.md B5).
- To change the field encryption key:
  1. Add the new key id to the configuration.
  2. Make it active.
  3. Take a backup.
  4. Run `php scripts/rotate-keys.php`.

  Keep the old key until the script reports that no rows still use it.
- After changing the `backup_key`, keep the old key as long as you keep backups made with it, so they can still
  be restored.
