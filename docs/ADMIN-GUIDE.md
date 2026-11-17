# System administrator and user guide

Sign in at `/account/login.php`. After signing in, the left menu shows only the modules your role allows.
Every list, figure and report is limited to your **data scope** (see below), and every change is recorded in
the audit log.

## 1. Roles

Roles are listed from the lowest rank to the highest. An administrator can grant only roles ranked below their
own, and only roles whose permissions are a subset of their own. When anyone other than a Super Administrator
grants a role, a second administrator must approve it.

| Rank | Role | Default scope | Main use |
|---|---|---|---|
| 15 | CNPL Ambassador | assigned area | Activities and coordination in the designated area |
| 15 | CNPL Focal Person | assigned area | Coordination activities in the designated area |
| 20 | CNPL Point Person | assigned cooperatives | Assigned cooperatives, tasks and activity reports |
| 20 | Cooperative Officer/Staff | one cooperative | Day-to-day records of their cooperative |
| 25 | Data Encoder | region | Data entry and imports; submissions wait for approval |
| 30 | Cooperative Administrator | one cooperative | Cooperative profile, officers, staff and members |
| 40 | Content Manager | none (website only) | Public website content and media |
| 40 | Help Desk Officer | none (help desk only) | Tickets assigned to them, or all tickets with permission |
| 50 | City/Municipal Administrator | city or municipality | Records in that locality |
| 55 | Auditor | national, read-only | Audit log, security center, reports |
| 60 | Provincial Administrator | province | Records in the province |
| 70 | Regional Administrator | region | Records in the region, approvals, regional reports |
| 90 | National Administrator | national | All organizational records, approvals, content, users below national level |
| 100 | Super Administrator | everything | System settings, roles and permissions, backups, all accounts |

Privileged roles must use multifactor authentication when **Settings → Security → Require MFA for privileged
roles** is on (the default in the clean database). These are Super, National, Regional, Provincial and
City/Municipal Administrator, Cooperative Administrator, Auditor and Content Manager.

**Permissions** (Admin → Roles and permissions) are shown as a matrix of 114 permissions. Only a Super
Administrator can edit it. Changes take effect at the user's next request, and are logged.

### Data scope

A user's scope comes from their **scope assignments** (Admin → Users → open a user → Data scope) and, for
assignment-based roles, from their active designations:

- **National** sees all records.
- **Region / province / city** sees records located there, including cooperatives and the people linked to them.
- **Cooperative** sees that cooperative's records only.
- **Point persons** see the cooperatives they are actively assigned to.
- **Ambassadors and focal persons** see their designated area.

A record outside your scope is answered with "not found". It is never just hidden from the list while still
reachable by its address. Creating or moving a record outside your scope is refused.

## 2. Everyday record work

Each registry uses the same screens.

- **List.** Search, filters (on phones, tap **Filters**), sortable columns and pages.
- **New / Edit.** Required fields are marked `*`. Duplicate checks warn about similar names or CDA numbers.
  Unsaved changes trigger a warning if you leave the page.
- **View.** Every field is shown. Related records are listed, and you can attach supporting documents
  (PDF, Office or images, up to the upload limit). A **revision history** shows who changed what.
- **Archive / Restore.** Records are never deleted from the screens. Archived records leave the active lists
  and can be restored by an authorized user.
- **Export CSV.** Exports what the current filters show, within your scope. Each export is logged. Cells that
  start with a formula character are neutralized.

### Review and verification

When someone without approval rights creates or edits a cooperative, officer or staff record, member,
point person, ambassador, CNPL officer or activity, the record is saved as **Pending approval**.

1. Approvers see it in **Approvals** (the number appears in the menu). They can approve, or reject with
   remarks, one at a time or in bulk.
2. The submitter is notified of the decision.
3. **Verification** is a separate step for cooperatives (for example, after checking the CDA certificate).
   Use **Mark as verified** on the record. Only verified, approved, active cooperatives that agreed to be listed
   appear in the public directory.

### Registries

| Module | What it holds | Notes |
|---|---|---|
| Cooperatives | Primary cooperatives: CDA number, type, category, address, region, province, city, contact, status | The reference number `CNPL-COOP-000001` is assigned automatically. Public listing is opt-in. |
| Cooperative officers and staff | People linked to a cooperative, with position, type, term and status | Pick an existing person or add a new one. Each person record is shared by every role they hold. |
| Regular members | Members of participating cooperatives, with lawful basis and consent reference | Contact details are encrypted and masked for users without permission. Membership is never treated as political affiliation. |
| CNPL point persons | Who coordinates with each cooperative, with assignment dates and status | An active assignment gives the linked user access to that cooperative. |
| Point-person tasks | Tasks given to point persons, with due date, status and completion notes | Point persons update their own tasks. |
| Ambassadors and focal persons | Designations with type, area (region, province or city), term and status | |
| CNPL officers / Officer appointments | National officers, organizational units, positions and appointment history | Current officers appear on the public Leadership and Structure pages. |
| Activities | Meetings, trainings, outreach and consultations, with participants and outcomes | |
| People directory | The shared person records used by the modules above | Contact details are encrypted at rest. |

## 3. Elections

1. **Election years.** Add each election year with its date and notes.
2. **Official sources.** Record each COMELEC or certified document: title, issuing body, date issued, reference
   and the attached file. Every result must cite a source.
3. **Election results.** Enter aggregate figures by level: national, region, province or city. These are votes
   obtained, total party-list votes cast, registered voters and rank. One record per area per year is allowed.
4. **Verification.** New or edited results stay **pending** until a *different* user with the verify permission
   confirms that the figures match the source. Editing a verified result returns it to pending. Every step is
   kept in the verification history.
5. **Election performance.** Compares years and drills down from national to region, province and city. It
   shows vote share and change, and exports to CSV or prints. Unverified results are marked as such.

## 4. Imports

Available in modules that allow it, such as cooperatives, officers and staff, and members.

1. **Download template.** The column headings match the field names.
2. **Upload** a CSV or XLSX file (up to 5,000 rows). Only cell values are read; formulas and macros are ignored.
3. **Map columns.** Columns are matched automatically and can be changed.
4. **Validate.** Every row is checked: required fields, formats, scope and duplicates. Download the
   problems as CSV, fix the file and upload it again.
5. **Submit for approval.** A different user with import approval rights approves or rejects the batch. The
   uploader cannot approve their own batch.
6. **Processing** runs in groups of 100 rows, each group in its own transaction. Imported records count as
   approved because the batch itself was approved.

## 5. Website content

Every public page is managed here. Nothing about the organization is hard-coded.

| Module | Notes |
|---|---|
| News and updates | One module for news, announcements, achievements, newsletters, programs, activities and publications (choose the type). Cover image, attachment, video link (YouTube or Vimeo only), category, featured flag. |
| Pages | About us, History introduction, Privacy notice, Terms of use, Accessibility statement. |
| Events | Date, time, venue or online link, capacity, registration deadline, visibility. Registrations are listed per event with check-in, cancellation, waiting list promotion (which emails the attendee) and CSV export. |
| Legislative updates | Measure type and number, title, status, filing and approval dates, sponsors, official source link. **Official information** and **CNPL commentary** are separate fields, and "officially verified" is set only after checking the official record. |
| Awards, Policies, History milestones, Galleries, FAQs | Policies keep each published version. Galleries hold images and videos. |
| Menus | Main and footer menus, with order and visibility. |
| Media library | Uploaded files with visibility (public or private), title and alternative text. Private files are served only to signed-in users allowed to see them. |

**Publishing workflow.** A record moves from draft to in review to published, and may later be archived.
- Users with the publish permission (Super Administrator, National Administrator and Content Manager by
  default) can publish directly. Others **submit for review**, and a publisher approves.
- Set **Publish at** to schedule a post. It stays hidden until that time.
- The **Preview** link shows unpublished content to signed-in editors only.
- Every save keeps a revision.

## 6. Help desk

- Primary cooperatives submit concerns on the public **Help desk** page. Each ticket gets a number such as
  `CNPL-HD-2026-000123` and an access key that is shown once. Requesters follow the ticket on **Track a ticket**,
  using the number, their email and the key, and can reply there.
- Officers see tickets in **Admin → Help desk**, with filters for status, priority, category, region and
  assignee.
- Open a ticket to:
  - assign it, and change its status (open, assigned, in progress, awaiting response, resolved, closed)
    and priority;
  - reply to the requester, who is emailed;
  - add an **internal note**, which is never shown to the requester;
  - attach files.
- **Confidential** tickets are visible only to officers with the confidential-ticket permission.

## 7. Users and access

**Admin → Users**

- **New user.** Enter the name, username and email, then the role and scope. Then choose how the user gets in:
  - **Email a one-time setup link** (recommended, valid for 72 hours);
  - or **show a temporary password once**.

  Either way, the user must set their own password at first sign-in.
- **Open a user** to:
  - add or remove roles. Grants above your rank are refused, and grants by non-Super Administrators wait for
    a second administrator;
  - add or remove scopes;
  - suspend or reactivate the account, which ends its sessions immediately;
  - end all sessions;
  - reset MFA, which makes the user set it up again at the next sign-in;
  - send a password reset link.

**Account security.** Each user manages their own password, multifactor authentication, recovery codes and active
sessions under **My account and security**. Repeated failed sign-ins lock the sign-in for the configured time.

## 8. Settings

Admin → Settings has two kinds of settings groups.

- **Website groups**: Identity, Contact, Social, Home page and Footer. Users with the manage-settings permission
  can edit them (Super Administrator, National Administrator and Content Manager by default).
- **Critical groups**: Email delivery, Security, Backups, Audit retention and Privacy. These need the
  security-settings permission (Super Administrator only by default), and saving asks for your password again.

| Group | Settings |
|---|---|
| Identity | Organization name, tagline, description, official logo, site-wide notice banner |
| Contact, Social | Office address, phone, email, office hours, map link; social media links |
| Home page | Introduction, mission, vision, objectives, which sections appear, number of news items |
| Security | Password minimum length, lockout threshold and minutes, session idle and absolute limits, reset link validity, upload size limit, MFA for privileged roles |
| Notifications | Email on/off, driver (log, SMTP or PHP mail), sender, SMTP server and credentials (the password is stored encrypted) |
| Privacy | Data Protection Officer, response days for privacy requests, retention periods for tickets, event registrations and member records |
| Audit, Backup | Audit retention, backup retention days and minimum backups kept |

## 9. Security, privacy and records

- **Security center:**
  - the overview, including the audit log integrity check;
  - alerts, which you can mark reviewed or escalate to an incident;
  - sign-in history, privileged permissions, exports, imports and the email log.
- **Incidents.** Record, contain and resolve security incidents and personal data breaches. Each record holds the
  severity, detection time, affected systems and data, actions taken, and the dates the National Privacy
  Commission (NPC) and the data subjects were notified.
- **Privacy requests.** Requests from the public form are tracked to completion within the configured number of
  days (access, correction, deletion, objection, portability).
- **Record of processing.** The register of processing activities required by the Data Privacy Act.
- **Audit log.** A read-only, hash-chained list of every sign-in, view of sensitive records, change, export,
  import, approval and setting change. You can filter and print it.
- **Backups.** Create, verify and download encrypted backups. Download asks for your password. Scheduled
  backups run from `scripts/backup.php`; see BACKUP-RECOVERY.md.

## 10. Reports

There are 12 reports:

- cooperative registration and geographic distribution;
- officers and staff, membership totals (counts only), point-person coverage, and ambassadors and focal persons;
- activities, help desk performance and event participation;
- election performance and content publication;
- data completeness and verification.

Each report respects your scope. It can be filtered by date and region, printed, or exported to CSV, and each
export is logged.

## 11. Daily and weekly routine

| When | Who | What |
|---|---|---|
| Daily | Approvers | Clear the Approvals inbox |
| Daily | Help desk | Respond to new and awaiting tickets |
| Daily (automatic) | Server | `cron.php` maintenance and retention; `backup.php --verify` |
| Weekly | Super Administrator | Security center alerts and the audit integrity panel; check the last backup is verified and copied off the server |
| Monthly | Data Protection Officer | Privacy requests, record of processing, retention review list |
| Quarterly | Super Administrator | Review users, roles and scopes; remove access no longer needed; test a backup restore |
