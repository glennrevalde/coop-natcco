# Sign-in accounts

Sign in at `/account/login.php`, for example http://localhost/coop-natcco/account/login.php. You can use the
username or the email address.

## Clean database (`coop_natcco_clean.sql`)

| Username | Email | Password | Role |
|---|---|---|---|
| `superadmin` | superadmin@example.org | `ChangeMe!2026Now` | Super Administrator |

At the first sign-in, the system requires a new password and then multifactor authentication set-up, because
privileged roles must use MFA. After that, open **My account and security** and change the email to a real
address, so password resets reach you. Then create named accounts for the other staff in Admin → Users.

## Sample database (`coop_natcco_sample.sql`)

Every account below uses the password **`CnplDemo#2026`**.

In the sample database:
- MFA is not enforced (`mfa_required_privileged` = 0), so the accounts work without an authenticator app.
- Each user can still turn MFA on under My account and security.
- No account is forced to change its password.

Accounts are listed from the lowest role to the highest:

| # | Username | Role (rank) | Data scope | What to try with it |
|---|---|---|---|---|
| 1 | `ambassador.demo` | CNPL Ambassador (15) | Designated area: Cebu province | Activities in the area; own designation |
| 2 | `focal.demo` | CNPL Focal Person (15) | Designated area: Region VII | Coordination activities in the region |
| 3 | `coopstaff.demo` | Cooperative Officer/Staff (20) | Bayanihan Consolacion Fishermen Cooperative | Day-to-day records of one cooperative; submissions wait for approval |
| 4 | `pointperson.demo` | CNPL Point Person (20) | Assigned cooperative: Bayanihan Consolacion Fishermen Cooperative | Assigned cooperative, own tasks, activity reports |
| 5 | `encoder.demo` | Data Encoder (25) | Region VII | Data entry and imports; everything waits for approval |
| 6 | `coopadmin.demo` | Cooperative Administrator (30) | Bayanihan Consolacion Fishermen Cooperative | Cooperative profile, officers, staff and members of one cooperative |
| 7 | `content.demo` | Content Manager (40) | Website only | News, pages, events, legislation, galleries, media; publishing |
| 8 | `helpdesk.demo` | Help Desk Officer (40) | Help desk only | Tickets: assign, reply, internal notes, status |
| 9 | `cityadmin.demo` | City/Municipal Administrator (50) | Consolacion, Cebu | Records in one municipality; out-of-scope records answer "not found" |
| 10 | `auditor.demo` | Auditor (55) | National, read-only | Audit log, security center, reports |
| 11 | `provadmin.demo` | Provincial Administrator (60) | Cebu province | Records and approvals in the province |
| 12 | `regionaladmin.demo` | Regional Administrator (70) | Region VII | Regional records, approvals, import approval, reports |
| 13 | `nationaladmin.demo` | National Administrator (90) | National | All organizational records, content, users below national level |
| 14 | `superadmin` | Super Administrator (100) | Everything | Settings, roles and permissions, backups, all accounts |

**Sample help desk ticket** for the public **Track a ticket** page:
- ticket number `CNPL-HD-2026-000001`;
- email `requester0@example.org`;
- access key `DEMOKEY000`.

The other 11 sample tickets follow the same pattern. Ticket number *n* uses email `requester(n−1)@example.org`
and access key `DEMOKEY` followed by *n*−1 as three digits. For example, `CNPL-HD-2026-000002` uses
`requester1@example.org` and `DEMOKEY001`, and `CNPL-HD-2026-000012` uses `requester11@example.org` and
`DEMOKEY011`.

## Test accounts created by the end-to-end tests

`tests/e2e/run-e2e.php` creates accounts named `e2e.*` (password `E2e-Tester#2026x`, or changed during the tests)
in the database it runs against. They are not in either SQL file. Reload the sample SQL to remove them.

## Before going live

- Load only `coop_natcco_clean.sql` in production. The demo accounts exist only in the sample file.
- Never reuse the demo password anywhere.
