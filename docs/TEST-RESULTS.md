# Test results

Every result below comes from an actual run, not a plan. The tests ship with the system, so they can be run again
at any time (commands in section 6).

## 1. Summary

| Suite | Cases | Passed | Failed | Skipped |
|---|---|---|---|---|
| Unit tests (`tests/unit`, UTC-01 to UTC-53) | 53 | 53 | 0 | 0 |
| Functional end-to-end tests (FT-01 to FT-31) | 31 | 31 | 0 | 0 |
| Security end-to-end tests (ST-01 to ST-35) | 35 | 35 | 0 | 0 |
| Role-by-page access crawl (14 roles × 100 admin pages and actions) | 1,400 requests | 1,400 without errors | 0 | 0 |
| PHP syntax check (`php -l`) | 211 files | 211 | 0 | 0 |
| MySQL 5.5 compatibility (schema scan, query scan, strict-mode import of both SQL files) | 4 checks | 4 | 0 | 0 |

**Test date:** 16 November 2026.

**Test environment**

| Component | Version or setting |
|---|---|
| OS | Ubuntu 24.04 |
| PHP | 8.3.6 (CLI and Apache module) |
| Web server | Apache 2.4.58 with the shipped `.htaccess` (AllowOverride All) |
| Database | MariaDB 10.11.14, `innodb_default_row_format=compact`, strict SQL mode (`STRICT_ALL_TABLES`, `NO_ZERO_DATE`, `NO_ZERO_IN_DATE`, `ERROR_FOR_DIVISION_BY_ZERO`) |
| Application database account | `cnpl_app` with the grants from `database/schema/grants.sql` |
| Data | `database/coop_natcco_sample.sql`, freshly imported |
| Visual review | Headless Chrome 153, at 390 px (phone) and 1280–1366 px (desktop) |

**Not covered by this run**
- **The MySQL 5.5 server binary and PHP 8.1.** Neither could be installed here. Compatibility was checked by
  loading the SQL in MySQL-5.5-like strict and compact mode, and by scanning the code for newer features (no
  matches). Run `scripts/check-requirements.php`, `tests/run-unit.php` and the end-to-end suite once on the target
  XAMPP (PHP 8.1.12) and MySQL 5.5 server.
- **External penetration testing and automated accessibility auditing** (for example WAVE or axe) were not
  performed.

## 2. Unit tests

These are PHPUnit-style standalone test files, one per test case. Each file has a header block giving the Test
Case ID, Module, Unit, Role, Description and Expected result. The tests run without a database, using a test
configuration and test keys.

| ID | Module | Unit | Description | Expected result | Actual result | Result |
|---|---|---|---|---|---|---|
| UTC-01 | Security | csv_safe() | Exported cells that start with a spreadsheet formula character are neutralised. | Values starting with =, @, a tab, or a non-numeric + or - are prefixed with an apostrophe. | Behaved as expected: 2 test method(s), 8 assertion(s) held. | Pass |
| UTC-02 | Security | csv_safe() | Signed numbers in exports stay numeric. | Numeric values such as -12.50 and +63 stay exactly as they are; only non-numeric text with a leading sign is prefixed. | Behaved as expected: 2 test method(s), 5 assertion(s) held. | Pass |
| UTC-03 | Data import | import_clean() | Imported cells are stripped of formula prefixes and control characters. | Leading =, +, @ and non-numeric - are removed, control characters are removed, and negative numbers are kept. | Behaved as expected: 3 test method(s), 8 assertion(s) held. | Pass |
| UTC-04 | Security | sanitize_html() | Script and style blocks are removed from rich text together with their content. | The output keeps the paragraph and contains no <script>, <style> or the script body. | Behaved as expected: 2 test method(s), 5 assertion(s) held. | Pass |
| UTC-05 | Security | sanitize_html() | Event-handler attributes are removed from every element. | onerror, onclick and onmouseover attributes are gone; the link text and its https address remain. | Behaved as expected: 2 test method(s), 6 assertion(s) held. | Pass |
| UTC-06 | Security | sanitize_html() / safe_href() | Links using javascript:, data: or protocol-relative addresses are neutralised. | The href attribute is removed for javascript:, data:, vbscript: and //host links, including mixed case and embedded whitespace; https, mailto, tel and relative links are kept. | Behaved as expected: 2 test method(s), 11 assertion(s) held. | Pass |
| UTC-07 | Security | sanitize_html() | Only allow-listed tags and attributes survive; unknown tags are unwrapped. | iframes, forms and style attributes are removed, unknown tags keep only their text, and allowed formatting stays. | Behaved as expected: 2 test method(s), 9 assertion(s) held. | Pass |
| UTC-08 | Security | safe_return() | Return addresses after login or form submission must stay inside the application. | External, protocol-relative and back-slash addresses fall back to the given page; paths under /coop-natcco/ are allowed. | Behaved as expected: 2 test method(s), 7 assertion(s) held. | Pass |
| UTC-09 | Website content | video_embed_url() | Only YouTube and Vimeo links become embeddable videos. | YouTube links become youtube-nocookie embed URLs, Vimeo links become player URLs, and any other address returns null. | Behaved as expected: 2 test method(s), 7 assertion(s) held. | Pass |
| UTC-10 | Security | e() / nl2p() | Text printed into pages is HTML-escaped, including quotes. | Angle brackets, ampersands and both quote types are escaped (&quot; and &apos;); nl2p() escapes before adding paragraphs. | Behaved as expected: 2 test method(s), 3 assertion(s) held. | Pass |
| UTC-11 | Data protection | encrypt_value() / decrypt_value() | Sensitive fields are encrypted with AES-256-GCM and decrypt back to the original. | The stored value starts with the key id, differs on every call (random IV) and decrypts to the original text; empty values stay null. | Behaved as expected: 2 test method(s), 8 assertion(s) held. | Pass |
| UTC-12 | Data protection | decrypt_value() | Altered or malformed ciphertext is rejected instead of returning garbage. | Flipping one byte, truncating, using an unknown key id or a value without a key id returns null. | Behaved as expected: 1 test method(s), 4 assertion(s) held. | Pass |
| UTC-13 | Data protection | blind_index() | Blind indexes allow exact-match search on encrypted values without storing the value. | The same value gives the same 64-character HMAC regardless of case and surrounding spaces; different values differ; empty gives null. | Behaved as expected: 1 test method(s), 5 assertion(s) held. | Pass |
| UTC-14 | Data protection | decode_key() | Encryption keys must be exactly 32 bytes. | A 32-byte base64 key decodes; a short key throws a RuntimeException that points to scripts/generate-keys.php. | Behaved as expected: 2 test method(s), 2 assertion(s) held. | Pass |
| UTC-15 | Data protection | encrypt_value() with encryption_active_key | After switching to a new key, values encrypted with the old key still decrypt. | New values carry the k2 prefix, old k1 values still decrypt, and both decrypt to their original text. | Behaved as expected: 1 test method(s), 4 assertion(s) held. | Pass |
| UTC-16 | Authentication | password_policy_errors() | A long password mixing character types is accepted. | No policy errors are returned for "Harbor-Lantern-2026!". | Behaved as expected: 1 test method(s), 2 assertion(s) held. | Pass |
| UTC-17 | Authentication | password_policy_errors() | Passwords shorter than the minimum length or longer than 128 characters are rejected. | An 8-character password returns the "at least 12 characters" error; a 129-character password returns the maximum-length error. | Behaved as expected: 2 test method(s), 2 assertion(s) held. | Pass |
| UTC-18 | Authentication | password_policy_errors() | Passwords must mix at least three character types. | An all-lowercase password and a lowercase-plus-digits password each return the character-mix error. | Behaved as expected: 1 test method(s), 3 assertion(s) held. | Pass |
| UTC-19 | Authentication | password_policy_errors() | Passwords may not contain the username or the email name. | A password containing the username, or the part of the email before @, returns the personal-information error. | Behaved as expected: 1 test method(s), 3 assertion(s) held. | Pass |
| UTC-20 | Authentication | password_policy_errors() | Passwords built on common words or the organisation name are rejected. | Passwords containing "password", "P@ssw0rd" or "CoopNatcco" return a common-words error. | Behaved as expected: 1 test method(s), 3 assertion(s) held. | Pass |
| UTC-21 | Authentication | password_make() | Passwords are stored as salted one-way hashes. | The hash is Argon2id when available (bcrypt otherwise), never contains the password, verifies with password_verify and differs each time. | Behaved as expected: 1 test method(s), 5 assertion(s) held. | Pass |
| UTC-22 | Authentication (MFA) | totp_code() | TOTP codes match the RFC 6238 reference values. | For the RFC secret, steps for T=59, 1111111109 and 1234567890 give 287082, 081804 and 005924. | Behaved as expected: 1 test method(s), 4 assertion(s) held. | Pass |
| UTC-23 | Authentication (MFA) | totp_verify() | A current code is accepted once; replaying the same code is refused. | The current code returns its time step; verifying it again with that step as last-used returns null; a wrong code returns null. | Behaved as expected: 2 test method(s), 4 assertion(s) held. | Pass |
| UTC-24 | Authentication (MFA) | totp_verify() | Codes that are not six digits are refused without checking the secret. | Empty, five-digit, seven-digit and alphabetic codes return null; spaces inside a valid code are ignored. | Behaved as expected: 1 test method(s), 5 assertion(s) held. | Pass |
| UTC-25 | Authentication (MFA) | base32_encode() / base32_decode() / totp_new_secret() | MFA secrets are 160-bit base32 strings that decode back to the same bytes. | New secrets are 32 base32 characters; random bytes survive an encode/decode round trip. | Behaved as expected: 1 test method(s), 22 assertion(s) held. | Pass |
| UTC-26 | Authentication (MFA) | mfa_recovery_codes() | Recovery codes are unique, readable, and stored only as hashes. | Eight unique codes in the form xxxxx-xxxxx are returned with eight matching SHA-256 hashes that do not contain the codes. | Behaved as expected: 1 test method(s), 27 assertion(s) held. | Pass |
| UTC-27 | Authentication | token_hash() / random_code() | Reset tokens are stored as SHA-256 hashes and random codes use an unambiguous alphabet. | token_hash() equals sha256; random_code() returns the requested length using only the allowed characters and varies between calls. | Behaved as expected: 2 test method(s), 52 assertion(s) held. | Pass |
| UTC-28 | Access control | scope_sql_for() | A national-scope user gets an unrestricted condition. | The condition is 1=1 and no parameters are added. | Behaved as expected: 1 test method(s), 2 assertion(s) held. | Pass |
| UTC-29 | Access control | scope_sql_for() | A regional user is limited to rows in the assigned region, using bound parameters. | The condition is (c.region_id IN (?)) with the region id as the only parameter. | Behaved as expected: 2 test method(s), 4 assertion(s) held. | Pass |
| UTC-30 | Access control | scope_sql_for() | A user with no scope, or a scope the table cannot match, sees nothing. | The condition is 0=1 for an empty scope and for a city scope on a table that only has a region column. | Behaved as expected: 1 test method(s), 4 assertion(s) held. | Pass |
| UTC-31 | Access control | scope_allows_for() | A city administrator may only touch records in that city. | A record in the assigned city is allowed; another city in the same province and region is refused. | Behaved as expected: 1 test method(s), 3 assertion(s) held. | Pass |
| UTC-32 | Access control | scope_allows_for() | A cooperative-level user may only touch records of that cooperative. | The assigned cooperative is allowed; another cooperative in the same city is refused; a national scope allows everything. | Behaved as expected: 1 test method(s), 3 assertion(s) held. | Pass |
| UTC-33 | Security | sql_in() | Lists of ids are bound as integers, so injected text cannot change the query. | Values are cast to integers, duplicates are removed, placeholders match the parameter count, and an empty list gives 0=1. | Behaved as expected: 2 test method(s), 4 assertion(s) held. | Pass |
| UTC-34 | File uploads | media_validate_upload() | A genuine PNG with a safe name is accepted. | The result reports extension png, MIME image/png, and a cleaned original name. | Behaved as expected: 1 test method(s), 3 assertion(s) held. | Pass |
| UTC-35 | File uploads | media_validate_upload() | Files with a hidden executable extension are refused even if the content is a real image. | shell.php.png, report.phtml.pdf and x.svg.png raise ValidationError "This file type is not allowed." | Behaved as expected: 1 test method(s), 4 assertion(s) held. | Pass |
| UTC-36 | File uploads | media_validate_upload() | A PHP script renamed to .jpg is refused because its content is not a JPEG. | ValidationError "The file content does not match its type." is raised. | Behaved as expected: 1 test method(s), 1 assertion(s) held. | Pass |
| UTC-37 | File uploads | media_validate_upload() | File types outside the allowlist are refused. | setup.exe and logo.svg are refused as not allowed; notes.txt is refused with the list of allowed types. | Behaved as expected: 2 test method(s), 4 assertion(s) held. | Pass |
| UTC-38 | File uploads | media_validate_upload() | Files above the size limit, empty files and failed uploads are refused. | An 11 MB file, a 0-byte file and an upload with UPLOAD_ERR_PARTIAL raise ValidationError with a clear message. | Behaved as expected: 2 test method(s), 3 assertion(s) held. | Pass |
| UTC-39 | File uploads | media_validate_upload() | Genuine PDF and Office files are accepted as documents; a fake PDF is refused. | A %PDF file and a ZIP-based .docx are accepted; a text file named .pdf is refused. | Behaved as expected: 1 test method(s), 3 assertion(s) held. | Pass |
| UTC-40 | Data import | import_read_file() | CSV files with a UTF-8 byte-order mark, semicolons and blank lines are read correctly. | Headers are returned without the BOM, semicolon-delimited cells are split, and blank rows are dropped. | Behaved as expected: 2 test method(s), 4 assertion(s) held. | Pass |
| UTC-41 | Data import | import_read_file() | XLSX workbooks are read as data, including shared strings, inline strings and skipped columns. | The first sheet is read; text from shared and inline strings and numbers land in the right columns; a gap column stays empty. | Behaved as expected: 1 test method(s), 3 assertion(s) held. | Pass |
| UTC-42 | Data import | import_norm() | Column headings are matched regardless of case, spaces and punctuation. | "CDA Registration No." and "cda_registration_no" normalise to the same key. | Behaved as expected: 1 test method(s), 3 assertion(s) held. | Pass |
| UTC-43 | Website content | slugify() | Page addresses are built from titles, including ñ and accented letters. | Titles become lowercase hyphenated slugs; ñ becomes n; empty input becomes "item"; length is capped. | Behaved as expected: 1 test method(s), 4 assertion(s) held. | Pass |
| UTC-44 | Duplicate detection | name_key() | Person names are normalised so the same person is detected as a possible duplicate. | Case, punctuation and extra spaces do not change the key; the key orders last\|first\|middle. | Behaved as expected: 1 test method(s), 3 assertion(s) held. | Pass |
| UTC-45 | Duplicate detection | coop_name_key() | Cooperative names are compared without generic words so near-duplicates are flagged. | "Bayanihan Multi-Purpose Cooperative" and "BAYANIHAN MULTI PURPOSE COOP, INC." give the same key. | Behaved as expected: 1 test method(s), 4 assertion(s) held. | Pass |
| UTC-46 | Data import | parse_date_input() / valid_date() | Dates are accepted in ISO, US month/day/year and Excel serial forms; impossible dates are refused. | 2026-03-15, 3/15/2026 and Excel serial 46096 all give 2026-03-15; 2026-02-30 is not a valid date; blank gives null. | Behaved as expected: 2 test method(s), 9 assertion(s) held. | Pass |
| UTC-47 | Data protection | mask_email() / mask_phone() | Contact details are masked for users who may not see them in full. | maria.santos@example.org becomes m••••••@example.org; 0917 123 4567 shows only the last three digits. | Behaved as expected: 1 test method(s), 5 assertion(s) held. | Pass |
| UTC-48 | Records | full_name() | Names are shown with a middle initial and suffix. | First, middle, last and suffix give "Juan S. Dela Cruz Jr."; missing parts leave no double spaces. | Behaved as expected: 1 test method(s), 3 assertion(s) held. | Pass |
| UTC-49 | Audit trail | diff_changes() | Change logs list only changed fields and hide protected values. | Timestamps are ignored, unchanged fields are skipped, and fields marked protected show [protected] instead of the value. | Behaved as expected: 1 test method(s), 4 assertion(s) held. | Pass |
| UTC-50 | Reports | fmt_pct() / fmt_num() / human_size() | Report figures format safely, including division by zero. | fmt_pct(1, 0) is empty; 25 of 200 is 12.50%; 1234567 shows as 1,234,567; 1536 bytes shows as 2 KB and 2 MiB as 2.0 MB. | Behaved as expected: 1 test method(s), 7 assertion(s) held. | Pass |
| UTC-51 | Website content | excerpt() | Card summaries are plain text, trimmed to length. | Tags are removed, entities decoded, whitespace collapsed, and long text ends with an ellipsis within the limit. | Behaved as expected: 1 test method(s), 3 assertion(s) held. | Pass |
| UTC-52 | User interface | status_text() | Statuses are shown as plain coloured text, not capsules, and are escaped. | Approved renders as <span class="st st-ok">Approved</span>; unknown statuses use the muted tone; labels are escaped. | Behaved as expected: 1 test method(s), 5 assertion(s) held. | Pass |
| UTC-53 | User interface | url() | Links are built under the configured base path and skip empty parameters. | url("admin/x.php", ["id" => 5, "q" => ""]) gives /coop-natcco/admin/x.php?id=5. | Behaved as expected: 1 test method(s), 3 assertion(s) held. | Pass |

53 of 53 unit test cases passed on PHP 8.3.6.

## 3. Functional tests (end-to-end)

These run over HTTP against Apache, with real sessions, CSRF tokens, forms and uploads. Results are confirmed
in the database where needed.

| ID | Area | Description | Expected result | Actual result | Result |
|---|---|---|---|---|---|
| FT-01 | Public website | Home page loads with live content | HTTP 200; the latest news, upcoming events, legislation and contact sections are shown | HTTP 200 with news, events, legislation and contact sections | Pass |
| FT-02 | Public website | Every public page opens without errors | All 33 public pages return HTTP 200 and none shows an error message | 33 pages returned 200 with no error output | Pass |
| FT-03 | Public website | Missing pages show the friendly not-found page | HTTP 404 with the site not-found page for an unknown file and an unknown news slug | HTTP 404 and 404, friendly page shown | Pass |
| FT-04 | Public website | Cooperative directory filters by region and lists only verified, consenting cooperatives | Filtering by Region VII lists only Region VII cooperatives, and every listed cooperative is verified, approved and opted in | 5 rows shown, all Region VII; total 5 matches the database | Pass |
| FT-05 | Public website | Site search finds published content | Searching "consultation" returns the published consultation events and news | Results include "Luzon Regional Consultation on Cooperative Financing" | Pass |
| FT-06 | Help desk | A primary cooperative submits a concern on the help desk | A ticket number (CNPL-HD-YYYY-NNNNNN) and a 10-character access key are shown; the ticket is stored with the email encrypted | Ticket CNPL-HD-2026-000013 created; access key shown once; email stored encrypted | Pass |
| FT-07 | Help desk | The requester tracks the ticket with ticket number, email and access key | Correct details show the ticket; a wrong access key shows the generic "could not find" message | Correct details show the ticket; wrong key gives the generic message | Pass |
| FT-08 | Events | A visitor registers for an event; a second registration with the same email is refused | First registration shows "You are registered." with a reference; the duplicate shows "already registered" | Registered once with a reference number; duplicate refused; 1 row stored | Pass |
| FT-09 | Contact | A visitor sends a message through the contact form | A thank-you message is shown and exactly one new contact message is stored | Confirmation shown; contact messages 2 -> 3 | Pass |
| FT-10 | Data privacy | A data subject files a privacy request | A reference code is shown and the request is stored with the email encrypted | Reference PR-2026-7A7EGD issued; email encrypted at rest | Pass |
| FT-11 | Authentication | Sign in with correct credentials | The National Administrator lands on the dashboard | Redirected to admin/index.php showing the dashboard | Pass |
| FT-12 | Authentication | Sign in with a wrong password | The generic message "The username or password is incorrect" is shown and no session is created | Generic error shown; admin pages still redirect to sign-in | Pass |
| FT-13 | Authentication | Sign in with an unknown username | Exactly the same message as a wrong password (no account enumeration) | Both attempts show: "The username or password is incorrect, or the account is not active." | Pass |
| FT-14 | Authentication | Sign out | After signing out with the POST form, admin pages redirect to the sign-in page and the old session is revoked | Signed out; admin/index.php now redirects to account/login.php | Pass |
| FT-15 | National database | A Data Encoder registers a cooperative in Region VII | The record is saved with review status "pending" and a CNPL reference number, inside the encoder's region | Saved #128 CNPL-COOP-000128 with review status pending | Pass |
| FT-16 | Approvals | The Regional Administrator approves the submission from the approval inbox | The cooperative appears in the inbox and becomes "approved" after the bulk decision | Listed in the inbox; review status is now approved | Pass |
| FT-17 | National database | Editing a cooperative keeps a revision and an audit entry | A new revision row lists the changed field and a "update" audit entry is written | Revisions 2 -> 3 (contact_person changed); audit entry present | Pass |
| FT-18 | National database | Archive and restore a cooperative | Archiving hides it from the active list; restoring brings it back | archived_at set, then cleared on restore | Pass |
| FT-19 | Exports | The Regional Administrator exports the cooperative list to CSV | A CSV download with a header row, only Region VII rows, and a new export log entry | 13 rows, all Region VII; export logged | Pass |
| FT-20 | Data import | Staged import: encoder uploads, maps and submits; regional admin approves and processes | One invalid row is reported, the encoder cannot approve the batch, and after a second person approves it the two valid rows become approved cooperatives | Batch #1: 2 valid, 1 invalid reported; encoder self-approval refused (403); approved by the Regional Administrator and processed into 2 approved cooperatives | Pass |
| FT-21 | Website content | Content Manager drafts news, submits for review, and an administrator publishes it | The post is hidden from the public while in review and appears on the news page after publishing | Hidden (404) while in review; published by the National Administrator and listed on news.php | Pass |
| FT-22 | Website content | A post scheduled for a future date stays hidden until then | A published post with publish_at tomorrow returns 404 publicly and is not listed | Saved as published for 2026-11-17 00:51:00; public page 404 and not listed | Pass |
| FT-23 | Help desk | A Help Desk Officer replies and adds an internal note | The requester sees the reply on the tracking page but never the internal note | Reply visible on the tracking page; internal note hidden | Pass |
| FT-24 | Reports | A report is exported to CSV | The distribution report downloads as CSV and an export log entry is written | CSV with 19 lines; export logged | Pass |
| FT-25 | Elections | Election performance compares years with source labels | The page shows the election years from the data and labels the sample source as not official | Years 2016, 2019, 2022 and 2025 shown | Pass |
| FT-26 | Notifications | Help desk officers are notified about new tickets | The help desk officer has a notification that mentions the new ticket | 1 notification(s) about CNPL-HD-2026-000013 | Pass |
| FT-27 | Account | A user changes their password | A weak new password is refused with the policy message; a strong one is accepted and works at the next sign-in | Weak password refused; new password works and the old one no longer does | Pass |
| FT-28 | Account | Forgotten password reset by email link | The same confirmation shows for known and unknown emails; the emailed link sets a new password | Identical confirmation for both emails; link from the mail log set a working password | Pass |
| FT-29 | Authentication (MFA) | Enrol an authenticator app and sign in with a second factor | Enrolment shows recovery codes; the next sign-in asks for a code; a reused code is refused; a recovery code works once | Enrolled (8 recovery codes); sign-in asked for a code; replayed TOTP refused; recovery code accepted once and refused the second time | Pass |
| FT-30 | Backups | The Super Administrator creates and verifies an encrypted backup | A backup record with status "ok" is created and verification succeeds | Backup #1 (completed, encrypted, 336 KB); verification: OK: Decrypted and decompressed successfully (1.3 MB of SQL) | Pass |
| FT-31 | Audit trail | The audit log hash chain is intact after all the activity above | audit_verify_chain() reports no broken link | 874 entries checked, chain intact | Pass |

## 4. Security tests (end-to-end)

| ID | Area | Description | Expected result | Actual result | Result |
|---|---|---|---|---|---|
| ST-01 | HTTP security | Security headers on every page | CSP without unsafe-inline, frame-ancestors none, X-Frame-Options DENY, nosniff, Referrer-Policy and Permissions-Policy are present | All headers present; no X-Powered-By | Pass |
| ST-02 | Session security | Session cookie flags | The session cookie is HttpOnly and SameSite=Lax (Secure is added automatically over HTTPS) | Set-Cookie: CNPLSESS=…; path=/coop-natcco/; HttpOnly; SameSite=Lax | Pass |
| ST-03 | CSRF | Form posts without a valid CSRF token are refused | HTTP 403 for a missing token and for a wrong token; nothing is stored | Missing token: 403; wrong token: 403; nothing stored | Pass |
| ST-04 | CSRF | Cross-site form posts are refused even with a token | A POST carrying another site in the Origin header returns 403 | Cross-origin POST refused (403); session unaffected | Pass |
| ST-05 | Access control | Admin pages and APIs require sign-in | Anonymous requests to admin pages redirect to sign-in; the lookup API answers 401 | 7 admin pages redirect to sign-in; lookup API returns 401 | Pass |
| ST-06 | Access control | Roles cannot open modules outside their permissions | The Help Desk Officer gets 403 for cooperatives, members, users, audit, settings and exports | All 7 requests returned 403 | Pass |
| ST-07 | Access control (IDOR) | A City Administrator cannot open a cooperative in another city by changing the id | Own-city cooperative #82 opens (200); Compostela cooperative #87 returns 404 | Own: 200; other city view: 404; other city edit: 404 | Pass |
| ST-08 | Access control (IDOR) | Posting an edit for an out-of-scope record changes nothing | The forged POST is refused and the Compostela cooperative keeps its contact person | Refused with 404; record unchanged | Pass |
| ST-09 | Access control (scope) | A City Administrator cannot create a record outside the assigned city | Choosing another city is refused with a scope error and no cooperative is created | Not created; form answered with: Please correct the following:The selected location is outside your assigned data scope. | Pass |
| ST-10 | Access control (escalation) | An administrator cannot grant a role above their own | The National Administrator cannot assign Super Administrator; a lower role becomes a pending request that the same person cannot approve | Super Administrator refused; Cooperative Officer request pending; self-approval refused (403); approved by a second administrator | Pass |
| ST-11 | XSS | Script in rich text is removed before it reaches the public site | The published article contains no <script>, event handler or javascript: link | Published page keeps "Safe text" and contains no script, handler or javascript: link | Pass |
| ST-12 | XSS | Reflected input is escaped | Search terms with markup are shown as text on public and admin pages | Payload shown escaped on search.php and admin/cooperatives.php | Pass |
| ST-13 | SQL injection | Injection attempts in search, filters, sorting and ids do nothing | Every request answers normally with no database error, no extra rows and the tables intact | Statuses 200, 200, 200, 200, 404; no errors, no leaks, no delay, tables intact | Pass |
| ST-14 | Brute force | Repeated wrong passwords lock the sign-in | After 5 failures even the correct password is refused with the lockout message | Correct password refused during lockout; security event recorded | Pass |
| ST-15 | Privacy | Password reset does not reveal which emails have accounts | Known and unknown emails get the same confirmation, and an unknown email sends nothing | Same confirmation; no email for the unknown address | Pass |
| ST-16 | Server hardening | Application internals cannot be downloaded | Config, includes, storage, database dumps, docs, tests, scripts and dotfiles return 403/404 (Apache .htaccess) | 14 paths refused (403/404); no directory listings | Pass |
| ST-17 | File access | Private files are only served to authorised users | A private document returns 404 to visitors and 200 to an administrator; path tricks return 404 | Private: visitor 404, admin 200; public 200; traversal attempts 404/404 | Pass |
| ST-18 | File uploads | Malicious uploads are rejected through the media library | A PHP script disguised as .jpg and a double-extension file are refused and nothing is stored | avatar.jpg: The file content does not match its type.; shell.php.jpg: This file type is not allowed. | Pass |
| ST-19 | Brute force | Ticket tracking cannot be brute-forced | Repeated wrong access keys are answered generically and then blocked with HTTP 429 | Blocked with 429 after 8 wrong keys | Pass |
| ST-20 | Session security | The session id changes at sign-in (no session fixation) | A session id planted before sign-in is not the one used afterwards | Session id regenerated at sign-in; attacker-chosen id discarded | Pass |
| ST-21 | Session security | Signing out requires a POST with a CSRF token | Opening the logout address with GET does not end the session | GET logout answered 303; session still valid | Pass |
| ST-22 | Open redirect | Return addresses cannot send users to another site | After sign-in the user stays inside the application even when the return address points elsewhere | Landed on /coop-natcco/admin/index.php | Pass |
| ST-23 | Session security | Suspending an account ends its open sessions immediately | A signed-in user is signed out on the next request after an administrator suspends the account | Account suspended; the open session was redirected to sign-in | Pass |
| ST-24 | Spam protection | Bots that fill the hidden field or submit instantly are ignored | No ticket is stored for a filled honeypot or for a submission under 2 seconds | Neither submission created a ticket | Pass |
| ST-25 | Account security | A password reset link works only once | Opening the used link again shows the expired/used message and does not change the password | Used and random tokens both refused | Pass |
| ST-26 | Data protection | Personal data is encrypted at rest | Member, personnel and ticket contact fields are stored as k1: ciphertext with no readable email or phone | 12 encrypted columns checked; every stored value is ciphertext | Pass |
| ST-27 | Audit trail | The application database account cannot alter or delete audit entries | UPDATE and DELETE on audit_logs are refused for the application account (least-privilege grants) | Both statements refused for 'cnpl_app' (error 1142) | Pass |
| ST-28 | Access control (scope) | Lookup API only returns records inside the user's scope | The City Administrator finds Consolacion cooperatives but not the Compostela cooperative | 4 Consolacion results; 0 results for the Compostela cooperative | Pass |
| ST-29 | Public API | The public geography API returns only geography and validates input | Provinces for Region VII are returned as JSON with no personal data; invalid input is refused | Region VII provinces returned; invalid request answered 400 | Pass |
| ST-30 | Error handling | Errors never show technical details | Malformed ids and unknown actions give a normal 400/404 page with no stack trace or SQL | All malformed requests answered without technical detail | Pass |
| ST-31 | Privacy | Regular members are never exposed on the public website | Searching the public site for a member's name finds nothing, and no public page lists members | No member name in public search; no public page lists members | Pass |
| ST-32 | Mass assignment | Hidden or system fields cannot be set through the form | A forged review_status=approved and created_by=1 on create are ignored; the record stays pending and belongs to the encoder | Saved as pending/unverified, created by the encoder (#13) | Pass |
| ST-33 | Exports | Exported spreadsheets cannot run formulas planted in data | A cooperative named "=HYPERLINK(...)" is exported as text starting with an apostrophe | Exported as '=HYPERLINK(...) (text) | Pass |
| ST-34 | Spam protection | Public forms are rate limited per address | The contact form answers 429 after 5 messages in 15 minutes | Statuses 200, 200, 200, 200, 200, 429 | Pass |
| ST-35 | Account security | Privileged roles must use multifactor authentication when the policy is on | With mfa_required_privileged=1 a Regional Administrator without MFA is sent to MFA setup and cannot open the dashboard | Sign-in and dashboard both redirected to account/mfa-setup.php | Pass |

## 5. Other checks

| Check | Method | Result |
|---|---|---|
| Role-by-page access | Each of the 14 demo accounts opened 100 admin pages and actions: every module's list, view, create, edit, import, export, every report and settings tab, the security center tabs and the lookup APIs. The test also watched the application error log. | No error or server failure in 1,400 requests. Pages outside a role's permissions answered 403. Records outside a user's scope answered 404. |
| Visual and browser check | 25 representative public and admin pages loaded in headless Chrome at phone and desktop widths, and reviewed from screenshots. The pages: home, directory, help desk and tracking, events and calendar, event registration, legislation, leadership, structure, history, gallery, FAQ, sign-in, dashboard, approvals, cooperative list, view, create and edit, election performance, ticket. | No browser console errors and no Content-Security-Policy violations. The layout issues found are listed in section 7. |
| Clean installation | `coop_natcco_clean.sql` imported into an empty database. The test signed in as `superadmin`. | Sign-in forced a password change, then MFA enrolment. All public pages showed proper empty states, with no invented organizational content. |
| SQL files | Both files imported into an empty database in strict mode with the compact row format. | Clean: 68 tables, 1,642 cities and municipalities, 1 account. Sample: 68 tables, 127 cooperatives, 649 members, 14 accounts. No warnings. |
| Schema scan | Every `CREATE TABLE` was parsed for engine and indexed column lengths. The schema was searched for JSON, CTE, window functions, generated columns, CHECK, FULLTEXT and fractional-second datetimes. | All 68 tables use InnoDB. No indexed VARCHAR exceeds 191. No unsupported features. |
| Query scan | All PHP files were searched for MySQL 5.6+/8.0 syntax (JSON functions, `WITH`, `OVER`, `ROW_NUMBER`, `->>`, `REGEXP_*`, `ANY_VALUE`, `LATERAL`, `SKIP LOCKED`, FULLTEXT `MATCH`). | No matches. |
| PHP 8.1 compatibility | The code was searched for features newer than PHP 8.1: readonly classes, standalone `true`/`false`/`null` types, typed class constants, `json_validate`, `mb_str_pad`, the `Random\Randomizer` methods added in 8.3, `array_find`. | No matches. |

## 6. Running the tests again

```
# Unit tests (no database needed; about one second)
php tests/run-unit.php
php tests/run-unit.php --md=unit-results.md        # also write a results table

# With PHPUnit installed, the same files also run under PHPUnit
phpunit -c tests/phpunit.xml

# End-to-end tests: only against a copy loaded with database/coop_natcco_sample.sql
php tests/e2e/run-e2e.php http://localhost/coop-natcco
php tests/e2e/run-e2e.php http://localhost/coop-natcco --md=e2e-results.md
php tests/e2e/run-e2e.php http://localhost/coop-natcco ST-07                  # one case
```

The end-to-end suite creates test records and `e2e.*` test accounts. Reload the sample SQL afterwards to return to
the original sample data.

- **ST-27 is skipped while the configuration uses the MySQL root account**, as on a default XAMPP. To run it,
  load `grants.sql` and switch the configuration to `cnpl_app`.
- **ST-16 checks that Apache refuses internal folders.** It passes only when Apache applies the `.htaccess`
  files. PHP's built-in server ignores them.

## 7. Defects found during testing and fixed

| Found by | Defect | Fix |
|---|---|---|
| Dashboard review | The "Waiting for approval" panel failed with a database error: the cooperative approval source used the wrong table alias. | Gave each approval source an explicit id column. The approval inbox orders by it. |
| Dashboard review | A failure inside a partly rendered page printed a second full HTML page inside the first. | Errors after output has started are now shown as an inline notice. |
| ST-03 | Under Apache, an expired or missing CSRF token returned HTTP 500 instead of a clear refusal. The non-standard status 419 is turned into 500 by Apache. | CSRF failures now return 403 with the "Session expired" page. |
| ST-29 | The public geography API answered 200 with an empty list for unknown request types. | It now answers 400 with an error message. |
| UTC-02 | CSV export prefixed negative numbers such as -12.50 with an apostrophe, turning them into text. | Only non-numeric values with a leading + or - are prefixed. |
| Code review during UTC-41 | The XLSX reader misplaced cells written without cell references, which some spreadsheet tools do. | Cells without a reference are placed in sequence. |
| Header check | Apache and PHP both sent the security headers, so pages had duplicates with different Permissions-Policy values. | `.htaccess` now adds headers only to static files. PHP sends them for pages. |
| Header check | The service worker script was cached for 30 days, which delays updates. | `sw.js` is served with `Cache-Control: no-cache`. |
| Mobile review | `.wrap.section` lost its side padding on phones, so content touched the screen edges on the directory, help desk and legislation pages. | The section rule now sets only vertical padding. |
| Mobile review | Admin filter panels pushed the records below the fold on phones. Card rows repeated the record name as a label. | Filters collapse behind a **Filters** button on phones, opening automatically when a filter is active. The first column is shown as the card title. |
| Design review | The notification count used a filled capsule. | It is now plain bold text, matching the rest of the interface. |

Six first-run end-to-end failures were mistakes in the test scripts themselves, not defects. Each test was
corrected and re-run:

- column names (`invalid_rows`, the lookup API's `items` key);
- a search term that the page legitimately reflects back;
- the backup verification message;
- the import expectation (an approved import batch creates approved records by design);
- a test account reused between runs.

Two unit-test expectations were corrected in the same way:

- `e()` escapes `'` as `&apos;` (HTML5);
- `human_size()` rounds kilobytes to whole numbers.
