# Installation guide

This guide covers two setups:

- **A. Local XAMPP for Windows**, for development and testing.
- **B. Production on a Linux server**, with Apache 2.4, PHP 8.1 or later and MySQL 5.5.3 or later.

## Requirements

| Item | Minimum | Notes |
|---|---|---|
| PHP | 8.1 | Tested on PHP 8.3.6. The code avoids features newer than 8.1. |
| PHP extensions | pdo_mysql, openssl, mbstring, fileinfo, dom, session, zlib, gd, zip | gd re-encodes uploaded images; zip reads .xlsx imports. |
| Web server | Apache 2.4 | mod_rewrite and mod_headers, with `AllowOverride All` (or the rules copied into the virtual host). |
| Database | MySQL 5.5.3 (utf8mb4) | Also runs on MySQL 5.6–8.0 and MariaDB 10.x. Read the MySQL 5.5 risk exception in SECURITY-PRIVACY.md. |
| Disk | 1 GB + uploads + backups | Backups are compressed and encrypted. |

Run `php scripts/check-requirements.php` at any time. It checks the PHP version, the extensions, the database
connection, writable folders, the encryption keys and production settings.

---

## A. Local XAMPP for Windows

XAMPP 8.1.x already has Apache with mod_rewrite and mod_headers, `AllowOverride All` for `htdocs`, and every
required PHP extension except **gd** and **zip**, which may be commented out.

1. **Enable gd and zip.** Open `C:\xampp\php\php.ini`, remove the `;` in front of these two lines, and restart Apache:
   ```
   extension=gd
   extension=zip
   ```
2. **Copy the application** to `C:\xampp\htdocs\coop-natcco` (the folder that contains `index.php`).
3. **Start Apache and MySQL** in the XAMPP Control Panel.
4. **Import the database.** Each SQL file creates the `coop_natcco` database. Use a command prompt:
   ```
   C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\coop-natcco\database\coop_natcco_sample.sql
   ```
   Use `coop_natcco_clean.sql` instead for an empty system. To reload, first drop the database
   (`DROP DATABASE coop_natcco;`), because the files create tables and do not replace them.
   phpMyAdmin also works: Import → choose the file → Go. The sample file is about 1 MB.
5. **Configuration.** `config\config.php` is ready for XAMPP:
   - base URL `/coop-natcco`;
   - database user `root` with an empty password;
   - the encryption keys that the sample data was encrypted with.

   If your MySQL root account has a password, set it in `'db' => ['pass' => '...']`.
6. **Check the installation:**
   ```
   C:\xampp\php\php.exe C:\xampp\htdocs\coop-natcco\scripts\check-requirements.php
   ```
   On a local copy, "Production readiness" warnings are expected.
7. **Open** http://localhost/coop-natcco. Staff sign in at http://localhost/coop-natcco/account/login.php
   (see ACCOUNTS.md).

**Email on XAMPP.** The mail driver is set to `log`, so emails such as password resets and ticket updates are
written to `storage\logs\mail.log` instead of being sent. To send real email, switch to SMTP in Admin → Settings → Notifications.

**Scheduled tasks on Windows (optional locally).** In Task Scheduler, create two daily tasks:

| Task | Program | Arguments |
|---|---|---|
| CNPL maintenance | `C:\xampp\php\php.exe` | `C:\xampp\htdocs\coop-natcco\scripts\cron.php` |
| CNPL backup | `C:\xampp\php\php.exe` | `C:\xampp\htdocs\coop-natcco\scripts\backup.php --verify` |

**Installing the system as an app.** Browsers offer installation only on `https://` addresses or on `http://localhost`.
On the XAMPP computer, open http://localhost/coop-natcco and click **Install app**. Phones and other computers that
reach XAMPP by its IP address (for example `http://192.168.1.10/coop-natcco`) see the install steps but cannot install
until the site has HTTPS. Production (part B) uses HTTPS, so installation works there on every device.

**If you install under a different folder name**, change `base_url` and `app_url` in `config/config.php`, and the
two `ErrorDocument` lines in `.htaccess`.

---

## B. Production (Linux, Apache, HTTPS)

The steps use Ubuntu/Debian paths. Replace `cnpl.example.org` with the real domain.

### 1. Packages

```
sudo apt install apache2 libapache2-mod-php php php-mysql php-mbstring php-xml php-gd php-zip php-intl
sudo a2enmod rewrite headers ssl
```

The database may run on the same server or on a private network host. Never expose it to the internet (step 4).

### 2. Folder layout and permissions

Keep the configuration and storage outside the web folder:

```
/var/www/coop-natcco/                 application (DocumentRoot)
/var/www/coop-natcco-private/config.php   configuration (found automatically in this location)
/var/lib/coop-natcco/storage/         uploads, logs, sessions, imports, backups
```

```
sudo mkdir -p /var/www/coop-natcco-private /var/lib/coop-natcco
sudo cp -r coop-natcco /var/www/
sudo cp -r /var/www/coop-natcco/storage /var/lib/coop-natcco/
sudo cp /var/www/coop-natcco/config/config.sample.php /var/www/coop-natcco-private/config.php

# Application code: read-only for the web server
sudo chown -R root:www-data /var/www/coop-natcco
sudo find /var/www/coop-natcco -type d -exec chmod 750 {} \;
sudo find /var/www/coop-natcco -type f -exec chmod 640 {} \;

# Configuration: readable by the web server only
sudo chown root:www-data /var/www/coop-natcco-private/config.php
sudo chmod 640 /var/www/coop-natcco-private/config.php

# Storage: writable by the web server, nobody else
sudo chown -R www-data:www-data /var/lib/coop-natcco/storage
sudo chmod -R 750 /var/lib/coop-natcco/storage
```

Delete the bundled local configuration `config/config.php`, because it holds the sample keys. Remove
`database/coop_natcco_sample.sql` and `database/seeders/` from production servers.

### 3. PHP settings (`/etc/php/8.x/apache2/php.ini`)

```
expose_php = Off
display_errors = Off
log_errors = On
upload_max_filesize = 12M
post_max_size = 26M
memory_limit = 256M
max_execution_time = 120
session.use_strict_mode = 1
```

The application sets its own session cookie flags. It adds Secure over HTTPS, and always sets HttpOnly and SameSite=Lax.

### 4. Database

1. Bind MySQL to the local or private interface only, in `my.cnf`:
   ```
   [mysqld]
   bind-address = 127.0.0.1
   local-infile = 0
   ```
   Block port 3306 in the firewall (`sudo ufw deny 3306`).
2. Import the clean database as a MySQL administrator:
   ```
   mysql -u root -p < /var/www/coop-natcco/database/coop_natcco_clean.sql
   ```
3. Create the least-privilege application account. Edit `database/schema/grants.sql`, replace
   `CHANGE-THIS-PASSWORD` with a long random password, then run:
   ```
   mysql -u root -p < /var/www/coop-natcco/database/schema/grants.sql
   ```
   This account can read and write data but cannot change the schema, and it can only add to `audit_logs` (no
   UPDATE or DELETE). The application refuses to connect as `root` when `app_env` is `production`.

### 5. Configuration (`/var/www/coop-natcco-private/config.php`)

1. Generate new keys and paste the output into the file:
   ```
   php /var/www/coop-natcco/scripts/generate-keys.php
   ```
   Keep a copy of the keys in a password manager or sealed envelope held by two officers. Without them,
   encrypted data and backups cannot be read.
2. Set:
   ```php
   'app_env' => 'production',
   'app_debug' => false,
   'base_url' => '',                       // '' when the site is at the domain root
   'app_url' => 'https://cnpl.example.org',
   'force_https' => true,
   'db' => ['host' => '127.0.0.1', 'port' => 3306, 'name' => 'coop_natcco', 'user' => 'cnpl_app', 'pass' => '...'],
   'storage_path' => '/var/lib/coop-natcco/storage',
   'trusted_proxies' => [],                // add the proxy IP only if a reverse proxy sits in front
   ```
3. When the site is at the domain root, change the two `ErrorDocument` lines in `.htaccess` to `/404.php`.

### 6. Apache virtual host with HTTPS

`/etc/apache2/sites-available/coop-natcco.conf`:

```apache
<VirtualHost *:80>
    ServerName cnpl.example.org
    Redirect permanent / https://cnpl.example.org/
</VirtualHost>

<VirtualHost *:443>
    ServerName cnpl.example.org
    DocumentRoot /var/www/coop-natcco
    <Directory /var/www/coop-natcco>
        Options -Indexes -MultiViews +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/cnpl.example.org/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/cnpl.example.org/privkey.pem
    # The application sends HSTS, CSP and the other security headers itself over HTTPS.
    ServerSignature Off
    ErrorLog ${APACHE_LOG_DIR}/coop-natcco-error.log
    CustomLog ${APACHE_LOG_DIR}/coop-natcco-access.log combined
</VirtualHost>
```

In `/etc/apache2/conf-available/security.conf` set `ServerTokens Prod` and `ServerSignature Off`.

```
sudo a2ensite coop-natcco
sudo certbot --apache -d cnpl.example.org      # free Let's Encrypt certificate with automatic renewal
sudo systemctl reload apache2
```

### 7. Scheduled tasks

`sudo crontab -u www-data -e`:

```
15 1 * * *  php /var/www/coop-natcco/scripts/cron.php          >> /var/lib/coop-natcco/storage/logs/cron.log 2>&1
45 1 * * *  php /var/www/coop-natcco/scripts/backup.php --verify >> /var/lib/coop-natcco/storage/logs/backup.log 2>&1
```

Then copy `/var/lib/coop-natcco/storage/backups/` to off-server storage every day (see BACKUP-RECOVERY.md).

### 8. First sign-in

1. Run `php /var/www/coop-natcco/scripts/check-requirements.php`. Every line should be OK.
2. Open `https://cnpl.example.org/account/login.php`.
3. Sign in as `superadmin` with `ChangeMe!2026Now`.
4. Set a new password, then scan the QR code with an authenticator app. Multifactor authentication is required for privileged roles.
5. In **Admin → Settings**, fill in:
   - site identity, logo and contact details;
   - the Data Protection Officer;
   - SMTP email (`mail_driver` = smtp);
   - the retention periods.
6. Remove the demo banner text (`demo_notice`) if it is set.
7. In **Admin → Users**, create named accounts for the administrators, then a second Super Administrator, so
   that role grants can be approved by a second person.
8. Enter the official content: About, History, leadership and legislation. Election results come from official
   sources (Admin → Official sources) and must pass verification before they are reported as verified.

### 9. Server security controls

- Keep the OS, Apache and PHP updated (`unattended-upgrades`).
- Allow only ports 80 and 443 from the internet. Restrict SSH to keys and to administrator IP addresses.
- Use fail2ban or the hosting firewall for repeated failed requests (the application also locks sign-ins).
- Watch `storage/logs/app-*.log` (errors, with a reference number shown to users) and Admin → Security center.
- Back up the configuration file and keys separately from the database backups.
