# Security and privacy

This document describes:

- the controls built into the system, and the test cases that verify each one (see TEST-RESULTS.md);
- the MySQL 5.5 risk exception;
- the measures taken under the Data Privacy Act of 2012 (RA 10173);
- the risks that remain.

It does not claim certification. No external penetration test has been performed.

## 1. Security controls

### Authentication and sessions
| Control | Implementation | Verified by |
|---|---|---|
| Strong passwords | At least 12 characters (configurable, never below 10) and at most 128. Must mix three character types. May not contain the username or email name, or common words. | UTC-16 to UTC-20, FT-27 |
| Password storage | Argon2id where PHP supports it, otherwise bcrypt (cost 12). Hashes are upgraded automatically at sign-in. | UTC-21 |
| No account enumeration | One message for every failed sign-in. Password reset gives the same answer for unknown emails. Equal-time hashing for unknown users. | FT-12, FT-13, ST-15 |
| Brute-force protection | Lockout after 5 failures per account (15 minutes, configurable). A per-address limit, and a security alert at 20 failures in 15 minutes. | ST-14 |
| Multifactor authentication | TOTP (RFC 6238) with ±30 s tolerance. Each code works once (replay refused). There are 8 single-use recovery codes stored as hashes. The TOTP secret is encrypted. | UTC-22 to UTC-26, FT-29 |
| MFA policy | Required for privileged roles when enabled (default in the clean database). Users are sent to MFA setup before any other page. | ST-35 |
| Sessions | Stored server-side and checked against `user_sessions` on every request. 30-minute idle and 12-hour absolute limits (configurable). New session id at sign-in. HttpOnly, SameSite=Lax, and Secure over HTTPS. | ST-02, ST-20 |
| Session revocation | Sessions end on sign-out (POST only), password change, suspension, role removal, or when an administrator ends them. | FT-14, ST-21, ST-23 |
| Password reset | Single-use token stored as a SHA-256 hash, valid 30 minutes (configurable). Rate-limited per address and per email. | FT-28, ST-25 |

### Authorization and data scope
| Control | Implementation | Verified by |
|---|---|---|
| Role-based access | 114 permissions in 14 roles. Every page and action calls `require_perm()` or a module permission check on the server. | ST-05, ST-06, crawl of 100 pages × 14 roles |
| Geographic and organizational scope | Every list, view, edit, export, report, lookup and dashboard figure adds a scope condition built from the user's assignments. If nothing matches, access is denied (`0=1`). | UTC-28 to UTC-32, ST-07, ST-08, ST-09, ST-28 |
| Direct object references | Out-of-scope ids answer 404 for both reading and writing. | ST-07, ST-08 |
| Privilege escalation | Users can grant only roles of lower rank, whose permissions are a subset of their own. Grants by anyone except a Super Administrator wait for a second administrator, and the requester cannot approve their own request. | ST-10 |
| Separation of duties | The uploader cannot approve their own import. Election results need verification by a different user. Records from users without approval rights wait in the approval inbox. | FT-16, FT-20 |
| Mass assignment | Only fields defined for the form are read. Review, verification and ownership fields are set by the server. | ST-32 |

### Input, output and request integrity
| Control | Implementation | Verified by |
|---|---|---|
| SQL injection | PDO with native prepared statements everywhere. Sort columns and identifiers come from allowlists. Id lists are cast to integers. | UTC-33, ST-13 |
| Cross-site scripting | Contextual output escaping. Rich text passes a DOM allowlist sanitizer that removes scripts, event handlers, unsafe URLs, iframes and styles. CSP blocks inline scripts. | UTC-04 to UTC-07, UTC-10, ST-11, ST-12 |
| CSRF | A per-session token on every POST, checked centrally before any page code runs. Cross-origin POSTs are refused by the Origin check. | ST-03, ST-04 |
| Open redirects | Return addresses must stay under the application path. | UTC-08, ST-22 |
| Security headers | `Content-Security-Policy` (`script-src 'self'`, no inline code, `frame-ancestors 'none'`), HSTS over HTTPS, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, COOP/CORP. `X-Powered-By` is removed. | ST-01 |
| Spreadsheet formula injection | Exported cells that start with `=`, `@`, `+` or `-` (unless numeric) get an apostrophe. Imported cells lose formula prefixes. | UTC-01 to UTC-03, ST-33 |
| Spam and abuse | Rate limits on every public form. A honeypot field and a 2-second minimum fill time. Ticket tracking is rate-limited and locked after repeated wrong keys. | ST-19, ST-24, ST-34 |

### Files
| Control | Implementation | Verified by |
|---|---|---|
| Upload validation | Extension allowlist per purpose. Double extensions such as `.php.jpg` are refused. The real MIME type (finfo) and file signature must match the extension. Size limit. | UTC-34 to UTC-39, ST-18 |
| Image re-encoding | Images are decoded and saved again with GD, which removes metadata and embedded payloads. Thumbnails are generated. | UTC-34 |
| Storage | Files get random names under `storage/uploads`. The folder is outside the web root in production and denied by `.htaccess`, with PHP execution turned off. | ST-16 |
| Serving | Through `file.php` only, after an access check: public files to everyone, private files to authorized users. A restrictive CSP sandbox and `nosniff` are sent. Only images and PDFs open in the browser; Office files are always downloaded as attachments. | ST-17 |

### Data protection
| Control | Implementation | Verified by |
|---|---|---|
| Encryption at rest | AES-256-GCM on personal contact fields, MFA secrets and the SMTP password. Keys are kept in the configuration file, outside the database. | UTC-11, UTC-12, UTC-14, ST-26 |
| Searchable encryption | HMAC-SHA256 blind indexes allow exact-match lookups and duplicate checks without decrypting. | UTC-13 |
| Key rotation | Values carry a key id. `scripts/rotate-keys.php` re-encrypts all values to the new key. | UTC-15 |
| Masking | Users without the contact-details permission see masked email and phone. | UTC-47 |
| Encrypted backups | Compressed SQL encrypted with AES-256-GCM in chunks, with a SHA-256 checksum. Verification decrypts and checks each one. | FT-30 |
| Transport | HTTPS is forced in production (`force_https`), with HSTS. | INSTALLATION.md |

### Logging and monitoring
| Control | Implementation | Verified by |
|---|---|---|
| Audit trail | Records sign-ins, sensitive views, every change (with field-level differences, protected values redacted), exports, imports, approvals, setting changes and backups. Each entry stores the hash of the previous one. | FT-17, FT-31, UTC-49 |
| Tamper resistance | The application database account has INSERT and SELECT only on `audit_logs`. The security center re-verifies the chain. | ST-27 |
| Security events | Lockouts, brute-force alerts, CSRF failures, rejected uploads, permission denials and backup failures. Each can be reviewed or escalated to an incident. If the database write fails, the event is written to `security-fallback.log`. | ST-14 |
| Error handling | Users see a generic page with a reference number. Details go to `storage/logs/app-*.log`. Debug output is never shown in production. | ST-30 |

## 2. MySQL 5.5 risk exception

MySQL 5.5 reached end of life in December 2018 and no longer receives security fixes. The specification requires
compatibility with it, so the system runs on it. **Running production on MySQL 5.5 is a security exception.** A
responsible officer must formally accept the risk, and it must be retired by a set date.

**Risks that remain while MySQL 5.5 is used**
- Known and future vulnerabilities in the database server will not be patched.
- Older TLS and authentication support (for example, no `caching_sha2_password`) if the database connection
  crosses a network.
- Fewer built-in protections, such as password validation plugins, the newer audit plugins and data-at-rest
  encryption in the server itself.

**Compensating controls (all required)**
1. MySQL listens only on `127.0.0.1` or a private network segment. Port 3306 is blocked from the internet by the firewall.
2. The application uses the least-privilege account from `grants.sql`: no schema changes, and audit logs append-only. Root is used only from the server console.
3. Personal contact data, MFA secrets and credentials are encrypted in the application, so a copy of the database files alone does not reveal them.
4. Backups are encrypted, verified daily and stored off the server.
5. Only administrators can reach the server, using SSH keys. The OS and web stack are patched.
6. Database administrator access is reviewed every quarter.

**Classification rule.** If MySQL 5.5 cannot be isolated as described above (for example, on shared hosting where
the database is reachable from other customers), the system must **not** be classified as production-secure.

**Migration plan.** The schema runs unchanged on MySQL 8.0. The steps are in DEPLOYMENT.md under "Moving away from MySQL 5.5".

**Risk acceptance** (to be completed by the organization)

| | |
|---|---|
| Accepted by (name and position) | |
| Date | |
| Compensating controls confirmed by | |
| Target date for migration to MySQL 8.0 | |
| Next review date | |

## 3. Data privacy (RA 10173)

### Principles applied
- **Membership is not political affiliation.**
  - Cooperative membership records exist only for organizational coordination with participating cooperatives.
  - The system stores no voter registration numbers, precinct data, political preferences or support indicators.
  - It creates no voter profiles.
  - Election data is aggregate only (votes per area), from official sources.
- **Data minimization.** Public forms ask only for what is needed to respond. Phone numbers are optional. Member
  records hold contact details only when the cooperative provides them with a lawful basis.
- **Members are never public.** No public page, search or API returns member data. Dashboards and reports show
  member counts only. (Verified by ST-31.)
- **Transparency.** An editable Privacy Notice is linked from every form, and every public form asks for consent.
  The site uses a single session cookie and no tracking or advertising cookies.

### Data inventory

The record of processing activities in the system holds the details and is editable by the DPO.

| Data | Purpose | Lawful basis | Who can access | Retention |
|---|---|---|---|---|
| Staff accounts (name, email, username) | Access to the system | Legitimate interest / contract | Administrators within scope | While the account is active, then deactivated |
| Cooperative officers, staff, point persons, ambassadors (name, position, encrypted contact) | Organizational coordination | Legitimate interest | Users whose scope covers the cooperative or area | While the role is current, then archived |
| Regular members (name, membership, encrypted contact) | Coordination with participating cooperatives | As recorded per member (consent or legitimate interest), with consent reference | Cooperative and higher administrators with the members permission | Configurable years after membership ends; flagged for DPO review by `cron.php` |
| Help desk requesters (name, encrypted email and phone, concern) | Responding to concerns | Consent and legitimate interest | Help desk officers; confidential tickets only with that permission | Configurable years after closure, then anonymized |
| Event registrants (name, encrypted email and phone, organization) | Event management | Consent | Event and content administrators | Configurable months after the event, then anonymized |
| Contact messages, privacy requests | Reply, and fulfilling data subject rights | Consent / legal obligation | Assigned administrators, DPO | As configured |

### Data subject rights
The public **Data Privacy Request** form covers access, correction, deletion or blocking, objection and
portability. Requests are tracked in Admin → Privacy requests, with status, a response due date (30 days by
default), the related member record when one is identified, and the resolution and actions taken. Verify the
requester's identity before releasing or changing any data, and note how it was verified in the resolution.

### Breach management
Record suspected breaches in **Admin → Incidents**. Each incident holds:
- severity, detection time, status and the related security event;
- what happened and the affected systems;
- whether it is a personal data breach, the personal data involved and the number of data subjects;
- containment and remediation actions;
- the dates the National Privacy Commission (NPC) and the data subjects were notified.

Under NPC Circular 16-03, notification is due within 72 hours of knowledge of a breach. Security events can be
escalated to an incident in one step.

## 4. Remaining risks and recommendations

| Risk | Status / recommendation |
|---|---|
| MySQL 5.5 end of life | Accepted exception with the compensating controls above; migrate to MySQL 8.0. |
| Sample configuration keys | `config/config.php` holds keys shared in this package. Never use it in production. Generate new keys (INSTALLATION.md, step B5). |
| Uploaded Office and PDF files are not virus-scanned | They are validated, renamed, stored outside the web root and always downloaded as attachments. Add ClamAV scanning on the server if staff open files from unknown senders. |
| No external penetration test | Commission one before handling real member data at scale. The automated security tests are in TEST-RESULTS.md. |
| Denial of service | Rate limits protect forms and sign-in, but not the network. Use the hosting provider's DDoS protection or a CDN or WAF. |
| Insider misuse by authorized users | Limited by scope and least privilege, recorded in the audit log, and reviewed through the security center and the quarterly access review. |
| Backups on the same server until copied | Copy `storage/backups` off the server daily (BACKUP-RECOVERY.md). |
| Email in local mode | The `log` driver writes emails to `storage/logs/mail.log` (development only). Use SMTP with TLS in production. |
