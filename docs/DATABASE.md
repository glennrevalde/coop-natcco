# Database architecture

- 68 InnoDB tables, utf8mb4 / utf8mb4_unicode_ci, 107 foreign keys, 269 indexes.
- The schema is in `database/schema/schema.sql`. Reference data is in `database/schema/seed_reference.sql`.
  Least-privilege grants are in `database/schema/grants.sql`.
- The ready-to-import files `database/coop_natcco_clean.sql` and `database/coop_natcco_sample.sql` are built
  from these.

## MySQL 5.5 compatibility rules

| Rule | Why |
|---|---|
| InnoDB with foreign keys on every relationship | Referential integrity and transactions |
| utf8mb4 (needs MySQL 5.5.3+) | Full Unicode, including ñ and emoji in names and content |
| Indexed VARCHAR columns are 191 characters or fewer | 767-byte index key limit of MySQL 5.5 with the COMPACT row format |
| DATETIME without `DEFAULT CURRENT_TIMESTAMP`; the application writes timestamps | MySQL 5.5 allows that default only on one TIMESTAMP column |
| No JSON type, CTEs (`WITH`), window functions, generated columns, CHECK constraints or InnoDB FULLTEXT | Not available in 5.5. Structured data such as import rows and change sets is stored as TEXT holding JSON produced by PHP. |
| Search uses `LIKE` with escaped wildcards, combined with indexed filters (region, status, type, dates) | No InnoDB FULLTEXT in 5.5 |
| All queries are prepared statements with native prepares; sort columns and identifiers come from allowlists in code | SQL injection prevention |

**How compatibility was verified.**
- The schema and both SQL files were loaded on MariaDB 10.11 in strict mode (`STRICT_ALL_TABLES`,
  `NO_ZERO_DATE`) with `innodb_default_row_format=compact`, which enforces the same 767-byte key limit.
- A static scan checked every query in the PHP code for syntax newer than MySQL 5.5.
- The real MySQL 5.5 binary was not available in the test environment (see TEST-RESULTS.md).

## Tables by area

### Geography (Philippine Standard Geographic Code)
| Table | Contents |
|---|---|
| `regions` | 18 regions including NCR, CAR, NIR and BARMM, with PSGC code and short name |
| `provinces` | 82 provinces, each linked to a region |
| `cities_municipalities` | 1,642 cities and municipalities. Highly urbanized and independent component cities have `province_id` NULL and belong to the region directly. Manila's sub-districts are not separate rows. |

### Identity, authentication and authorization
| Table | Contents |
|---|---|
| `users` | Accounts: username, email, Argon2id/bcrypt password hash, status, MFA flag, must-change-password |
| `roles`, `permissions`, `role_permissions` | 14 roles with rank and scope level; 114 permissions; the matrix |
| `user_roles` | Role grants with `assignment_status` (pending, active, revoked), who assigned and who approved |
| `user_scope_assignments` | Data scope: national, region, province, city or cooperative |
| `user_sessions` | Server-side session records: hashed session id, IP, user agent, last activity, MFA verified, revoked |
| `mfa_credentials` | TOTP secret (encrypted), hashed recovery codes, last used step (replay protection) |
| `password_reset_tokens` | Hashed single-use tokens for reset and account setup, with expiry |
| `authentication_events` | Sign-ins, failures, lockouts, MFA and password events |
| `throttles` | Rate limits and lockouts (hashed keys) |

### Organizational data
| Table | Contents |
|---|---|
| `organizational_units`, `positions` | National board, offices, departments, committees, regional offices; position titles |
| `persons` | One record per person, shared by every role they hold; email and phone encrypted, email blind index for duplicate checks |
| `officers`, `officer_appointments` | CNPL officers and their appointment history (unit, position, term, status) |
| `cooperative_types`, `cooperatives` | Primary cooperatives with CDA number, type, location, contact, organizational, verification and review status |
| `cooperative_personnel` | Officers and staff of each cooperative |
| `cnpl_point_persons`, `point_person_tasks` | Point-person assignments to cooperatives and their tasks |
| `ambassadors_focal_persons` | Ambassador and focal person designations (`designation_type`) with area and term |
| `members`, `cooperative_memberships` | Regular members with their current cooperative, membership status, encrypted contact details, lawful basis and consent reference; `cooperative_memberships` keeps the membership history (status changes with dates and reason) |
| `activities` | Organizational activities with participants and outcomes |

### Elections
| Table | Contents |
|---|---|
| `election_years` | Election years and dates |
| `election_sources` | Official or certified source documents (title, issuer, date, reference, file) |
| `election_results` | Aggregate results per year and area (national, region, province, city). One row per year per area (`UNIQUE (election_year_id, geo_key)`). |
| `election_result_verifications` | History of submissions, edits, verifications and rejections. The verifier must differ from the submitter. |

### Public website
| Table | Contents |
|---|---|
| `pages` | Fixed pages: About, History introduction, Privacy notice, Terms, Accessibility |
| `posts` | News, announcements, achievements, newsletters, programs, activities and publications (`post_type`), with publishing workflow and schedule |
| `content_categories` | Categories per content type |
| `events`, `event_registrations` | Events and registrations, with encrypted contact details and a blind index for duplicate checks |
| `legislative_updates` | Bills, resolutions and laws, with official information and commentary kept in separate fields |
| `awards`, `policies`, `policy_versions`, `history_milestones` | Awards, policies with every published version, history timeline |
| `galleries`, `gallery_items` | Photo and video galleries |
| `faqs`, `menu_items`, `website_settings` | FAQ, main and footer menus, settings (SMTP password encrypted) |
| `media_assets` | Every uploaded file: random stored name, SHA-256, MIME, size, dimensions, visibility |
| `contact_messages` | Contact form messages (email encrypted) |

### Support and operations
| Table | Contents |
|---|---|
| `helpdesk_categories`, `helpdesk_tickets`, `ticket_replies`, `ticket_attachments` | Help desk. Requester email and phone are encrypted; the access key is stored as a hash. |
| `notifications`, `notification_preferences`, `email_log` | In-system notifications, per-user preferences, sent-email log (masked recipient) |
| `approval_requests` | Pending decisions: role grants and import batches |
| `import_batches`, `import_rows` | Staged imports and the validation result of each row |
| `export_logs` | Who exported what, with filters and row count |
| `audit_logs` | Hash-chained audit trail: each entry stores the previous entry's hash. The app account can only INSERT and SELECT. |
| `security_events`, `security_incidents` | Security alerts and the incident register |
| `backup_records` | Backup files, checksums and verification results |
| `privacy_requests`, `processing_activities` | Data subject requests and the record of processing activities |
| `record_revisions`, `record_attachments` | Field-level change history and supporting documents for every module |

## Mapping to the table names in the specification

The specification lists starting table names. Most are used exactly; these were consolidated:

| Specification | This system | Reason |
|---|---|---|
| `sessions` | `user_sessions` | Clearer name; holds server-side session records |
| `ambassadors`, `focal_persons` | `ambassadors_focal_persons` (`designation_type` = ambassador or focal_person) | The same fields and workflow; one registry avoids duplicated code and data |
| `news_articles`, `newsletters`, `achievements`, `publications` | `posts` (`post_type`) | One publishing workflow, revision history and search for all article-like content |
| (not listed) | `persons` | One person can be a CNPL officer, a cooperative officer and a point person; contact details are stored once |
| (not listed) | `throttles`, `record_revisions`, `record_attachments`, `import_rows`, `email_log`, `notification_preferences`, `security_incidents`, `processing_activities`, `content_categories`, `galleries`, `gallery_items`, `faqs`, `menu_items`, `history_milestones`, `policy_versions`, `positions`, `cooperative_types`, `point_person_tasks`, `helpdesk_categories`, `contact_messages` | Lookup, versioning and operational tables the specification asks to add |

## Conventions

- `id INT UNSIGNED AUTO_INCREMENT` primary keys. Foreign keys restrict deletion by default; 13 relationships use `ON DELETE CASCADE` for child rows that cannot exist alone, such as role permissions, import rows and gallery items.
- `created_at`, `created_by`, `updated_at` and `updated_by` on every editable table, set by the application.
- Soft deletion via `archived_at`. Records are archived, not deleted, from the screens.
- Review workflow columns: `review_status` (pending, approved, rejected) and `review_remarks`.
- Human-readable reference numbers (`CNPL-COOP-000001`, `CNPL-HD-2026-000001`) are generated in the transaction that creates the record.
- Names used for duplicate checks are normalized into `name_key` columns (indexed).

## Encryption at rest (application layer)

Encrypted with AES-256-GCM. The stored format is `k1:<base64(iv | tag | ciphertext)>`, so keys can be rotated
with `scripts/rotate-keys.php`.

| Table | Encrypted columns | Blind index (exact-match search) |
|---|---|---|
| `persons` | `email_enc`, `phone_enc` | `email_bidx` |
| `members` | `email_enc`, `phone_enc`, `address_enc` | |
| `helpdesk_tickets` | `requester_email_enc`, `requester_phone_enc` | `requester_email_bidx` |
| `event_registrations` | `email_enc`, `phone_enc` | `email_bidx` |
| `contact_messages`, `privacy_requests` | email | |
| `mfa_credentials` | `secret_enc` | |
| `website_settings` | `smtp_password_enc` | |

Organizational contact details of cooperatives (the authorized contact's office email and phone) are business
contact data and are stored in plain text so they can be searched and exported by authorized users.

## Migration to a supported MySQL version

The schema uses only features shared by MySQL 5.5 and 8.0, so no conversion is needed. To move, restore a backup
into MySQL 8.0 and point the configuration at it; the steps are in DEPLOYMENT.md. After the move, possible
improvements are:

- InnoDB FULLTEXT search for content;
- `DATETIME DEFAULT CURRENT_TIMESTAMP`;
- native JSON columns for `import_rows.row_data` and `record_revisions.changes`.

None of these is required.
