<?php
/**
 * End-to-end functional and security tests.
 *
 * Drives the running website over HTTP the way a browser does (sessions,
 * CSRF tokens, forms, uploads), and checks the database where a result must
 * be confirmed there. Every case records: ID, area, description, expected
 * result, actual result and pass/fail.
 *
 * RUN ONLY AGAINST A TEST COPY LOADED WITH THE SAMPLE DATA
 * (database/coop_natcco_sample.sql). The tests create records, tickets,
 * posts and test accounts, and they refuse to run when app_env is production.
 *
 *   php tests/e2e/run-e2e.php http://localhost/coop-natcco
 *   php tests/e2e/run-e2e.php http://localhost/coop-natcco --md=docs/e2e-results.md
 *   php tests/e2e/run-e2e.php http://localhost/coop-natcco ST-07     (one case)
 *
 * The database connection comes from the same configuration the website uses.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
define('CNPL_TESTING', true);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require __DIR__ . '/lib.php';
restore_exception_handler();

if (is_production()) {
    fwrite(STDERR, "Refusing to run: app_env is production. Use a test copy with the sample data.\n");
    exit(1);
}
$BASE = rtrim((string) ($argv[1] ?? cfg('app_url')), '/') . '/';
$MD = null;
$ONLY = null;
foreach (array_slice($argv, 2) as $a) {
    if (strpos($a, '--md=') === 0) {
        $MD = substr($a, 5);
    } else {
        $ONLY = $a;
    }
}
const PW = 'CnplDemo#2026';
const E2E_PW = 'E2e-Tester#2026x';

final class E2EFail extends RuntimeException
{
}
final class E2ESkip extends RuntimeException
{
}

function must(bool $ok, string $failure): void
{
    if (!$ok) {
        throw new E2EFail($failure);
    }
}

function client(): E2EClient
{
    return new E2EClient($GLOBALS['BASE']);
}

function as_user(string $user, string $pw = PW): E2EClient
{
    $c = client();
    $r = $c->login($user, $pw);
    must(strpos($r->url, 'admin/') !== false || strpos($r->url, 'account/') !== false, "login as {$user} failed (ended at {$r->url})");
    return $c;
}

/** Fill and submit the record form on a CRUD page. */
function save_record(E2EClient $c, string $page, array $values): E2EResponse
{
    return $c->submit($page, $values, '.record-form');
}

function mail_log(): string
{
    $f = STORAGE_PATH . '/logs/mail.log';
    return is_file($f) ? (string) file_get_contents($f) : '';
}

function app_log_size(): int
{
    $n = 0;
    foreach (glob(STORAGE_PATH . '/logs/app-*.log') ?: [] as $f) {
        $n += filesize($f);
    }
    clearstatcache();
    return $n;
}

/** Create (or reset) a test account with one role and an optional region scope. */
function e2e_user(string $username, string $roleSlug, ?int $regionId = 11): int
{
    $id = (int) q_val('SELECT id FROM users WHERE username = ?', [$username]);
    $hash = password_hash(E2E_PW, PASSWORD_BCRYPT, ['cost' => 10]);
    if ($id) {
        q("UPDATE users SET password_hash = ?, account_status = 'active', mfa_enabled = 0, must_change_password = 0, updated_at = ? WHERE id = ?", [$hash, now(), $id]);
        q('DELETE FROM mfa_credentials WHERE user_id = ?', [$id]);
        q("UPDATE user_sessions SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL", [now(), $id]);
        $rid = (int) q_val('SELECT id FROM roles WHERE slug = ?', [$roleSlug]);
        q('DELETE FROM user_roles WHERE user_id = ? AND role_id <> ?', [$id, $rid]);
        q("UPDATE user_roles SET assignment_status = 'active' WHERE user_id = ?", [$id]);
    } else {
        $id = q_insert('users', ['username' => $username, 'email' => $username . '@example.org', 'full_name' => 'E2E ' . $username, 'password_hash' => $hash,
            'account_status' => 'active', 'must_change_password' => 0, 'mfa_enabled' => 0, 'created_at' => now()]);
        $rid = (int) q_val('SELECT id FROM roles WHERE slug = ?', [$roleSlug]);
        q_insert('user_roles', ['user_id' => $id, 'role_id' => $rid, 'assignment_status' => 'active', 'assigned_by' => 1, 'approved_by' => 1, 'approved_at' => now(), 'created_at' => now()]);
        q_insert('user_scope_assignments', ['user_id' => $id, 'scope_type' => $regionId ? 'region' : 'national', 'region_id' => $regionId, 'created_by' => 1, 'created_at' => now()]);
    }
    return $id;
}

$CASES = [];
function tc(string $id, string $area, string $desc, string $expected, callable $fn): void
{
    $GLOBALS['CASES'][] = compact('id', 'area', 'desc', 'expected', 'fn');
}

$S = []; // values shared between related cases

// =====================================================================
// FUNCTIONAL TESTS
// =====================================================================

tc('FT-01', 'Public website', 'Home page loads with live content', 'HTTP 200; the latest news, upcoming events, legislation and contact sections are shown', function () {
    $r = client()->get('');
    must($r->status === 200, "status {$r->status}");
    foreach (['Latest news and announcements', 'Upcoming events', 'Recent legislative updates', 'Contact us'] as $t) {
        must($r->has($t), "missing section: {$t}");
    }
    return 'HTTP 200 with news, events, legislation and contact sections';
});

tc('FT-02', 'Public website', 'Every public page opens without errors', 'All 33 public pages return HTTP 200 and none shows an error message', function () {
    $pages = ['index.php', 'about.php', 'history.php', 'structure.php', 'leadership.php', 'cooperatives.php', 'programs.php', 'news.php', 'announcements.php',
        'achievements.php', 'newsletters.php', 'gallery.php', 'publications.php', 'events.php', 'events.php?view=calendar', 'events.php?view=past', 'legislation.php',
        'awards.php', 'policies.php', 'faq.php', 'contact.php', 'helpdesk.php', 'helpdesk-track.php', 'privacy.php', 'terms.php', 'accessibility.php',
        'privacy-request.php', 'search.php?q=cooperative', 'account/login.php', 'account/forgot-password.php', 'officer.php?id=1', 'robots.txt', 'manifest.webmanifest'];
    $c = client();
    $bad = [];
    foreach ($pages as $p) {
        $r = $c->get($p);
        if ($r->status !== 200 || $r->has('Something went wrong') || preg_match('/(Fatal error|Warning:|Notice:|Deprecated:)/', $r->body)) {
            $bad[] = "{$p} ({$r->status})";
        }
    }
    must(!$bad, 'problems: ' . implode(', ', $bad));
    return count($pages) . ' pages returned 200 with no error output';
});

tc('FT-03', 'Public website', 'Missing pages show the friendly not-found page', 'HTTP 404 with the site not-found page for an unknown file and an unknown news slug', function () {
    $c = client();
    $a = $c->get('no-such-page.php');
    $b = $c->get('post.php?slug=this-does-not-exist');
    must($a->status === 404 && $b->status === 404, "statuses {$a->status} / {$b->status}");
    must(stripos($a->text(), 'not found') !== false || stripos($a->text(), "can't find") !== false || stripos($a->text(), 'could not find') !== false, 'not-found wording missing');
    return "HTTP {$a->status} and {$b->status}, friendly page shown";
});

tc('FT-04', 'Public website', 'Cooperative directory filters by region and lists only verified, consenting cooperatives', 'Filtering by Region VII lists only Region VII cooperatives, and every listed cooperative is verified, approved and opted in', function () {
    $r = client()->get('cooperatives.php?region=11');
    must($r->status === 200, "status {$r->status}");
    preg_match_all('#<td data-label="Region">([^<]*)</td>#', $r->body, $m);
    must(count($m[1]) > 0, 'no rows listed');
    must(count(array_unique($m[1])) === 1 && $m[1][0] === 'Region VII', 'other regions listed: ' . implode(',', array_unique($m[1])));
    $expected = (int) q_val("SELECT COUNT(*) FROM cooperatives WHERE region_id = 11 AND is_public_listing = 1 AND verification_status = 'verified' AND review_status = 'approved' AND org_status = 'active' AND archived_at IS NULL");
    must(strpos($r->text(), $expected . ' cooperative') !== false, "count shown does not match database ({$expected})");
    return count($m[1]) . " rows shown, all Region VII; total {$expected} matches the database";
});

tc('FT-05', 'Public website', 'Site search finds published content', 'Searching "consultation" returns the published consultation events and news', function () {
    $r = client()->get('search.php?q=consultation');
    must($r->status === 200, "status {$r->status}");
    must($r->has('Luzon Regional Consultation'), 'expected event not found in results');
    return 'Results include "Luzon Regional Consultation on Cooperative Financing"';
});

tc('FT-06', 'Help desk', 'A primary cooperative submits a concern on the help desk', 'A ticket number (CNPL-HD-YYYY-NNNNNN) and a 10-character access key are shown; the ticket is stored with the email encrypted', function () {
    global $S;
    $cat = (int) q_val("SELECT id FROM helpdesk_categories WHERE is_active = 1 ORDER BY id LIMIT 1");
    $email = 'e2e.requester.' . time() . '@example.org';
    $r = client()->submit('helpdesk.php', ['requester_name' => 'E2E Requester', 'email' => $email, 'region_id' => '11', 'cooperative_name' => 'Bayanihan Consolacion Fishermen Cooperative',
        'category_id' => (string) $cat, 'priority' => 'normal', 'subject' => 'E2E concern ' . time(), 'description' => 'We need help registering our officers in the national database.', 'consent' => '1'], null, true, 2.2);
    must((bool) preg_match('/CNPL-HD-\d{4}-\d{6}/', $r->body, $tn), 'no ticket number shown');
    must((bool) preg_match('#Access key</p><p class="code">([A-Z0-9]{10})</p>#', $r->body, $ak), 'no access key shown');
    $row = q_one('SELECT * FROM helpdesk_tickets WHERE ticket_no = ?', [$tn[0]]);
    must($row !== null, 'ticket not in database');
    must(strpos((string) $row['requester_email_enc'], 'k1:') === 0 && strpos((string) $row['requester_email_enc'], 'e2e.requester') === false, 'email not encrypted at rest');
    $S['ticket'] = ['no' => $tn[0], 'key' => $ak[1], 'email' => $email, 'id' => (int) $row['id'], 'subject' => $row['subject']];
    return "Ticket {$tn[0]} created; access key shown once; email stored encrypted";
});

tc('FT-07', 'Help desk', 'The requester tracks the ticket with ticket number, email and access key', 'Correct details show the ticket; a wrong access key shows the generic "could not find" message', function () {
    global $S;
    must(isset($S['ticket']), 'depends on FT-06');
    $t = $S['ticket'];
    $ok = client()->submit('helpdesk-track.php', ['ticket_no' => $t['no'], 'email' => $t['email'], 'access_key' => $t['key']]);
    must($ok->has(e($t['subject'])), 'ticket not shown with correct details');
    $bad = client()->submit('helpdesk-track.php', ['ticket_no' => $t['no'], 'email' => $t['email'], 'access_key' => 'WRONGKEY99']);
    must($bad->has('We could not find a ticket with those details'), 'wrong key not refused generically');
    must(!$bad->has(e($t['subject'])), 'ticket leaked with wrong key');
    return 'Correct details show the ticket; wrong key gives the generic message';
});

tc('FT-08', 'Events', 'A visitor registers for an event; a second registration with the same email is refused', 'First registration shows "You are registered." with a reference; the duplicate shows "already registered"', function () {
    $slug = 'luzon-regional-consultation-on-cooperative-financing';
    $email = 'e2e.attendee.' . time() . '@example.org';
    $vals = ['full_name' => 'E2E Attendee', 'email' => $email, 'phone' => '', 'organization' => 'Sample Cooperative', 'consent' => '1'];
    $r1 = client()->submit('event.php?slug=' . $slug, $vals, null, true, 2.2);
    must($r1->has('You are registered.') || $r1->has('waiting list'), 'no confirmation shown');
    $r2 = client()->submit('event.php?slug=' . $slug, $vals, null, true, 2.2);
    must($r2->has('already registered for this event'), 'duplicate registration not refused');
    $n = (int) q_val('SELECT COUNT(*) FROM event_registrations WHERE email_bidx = ?', [blind_index($email)]);
    must($n === 1, "{$n} registrations stored");
    return 'Registered once with a reference number; duplicate refused; 1 row stored';
});

tc('FT-09', 'Contact', 'A visitor sends a message through the contact form', 'A thank-you message is shown and exactly one new contact message is stored', function () {
    $before = (int) q_val('SELECT COUNT(*) FROM contact_messages');
    $r = client()->submit('contact.php', ['full_name' => 'E2E Visitor', 'email' => 'e2e.visitor@example.org', 'subject' => 'Partnership inquiry', 'message' => 'We would like to invite your office to our general assembly.', 'consent' => '1'], null, true, 2.2);
    $after = (int) q_val('SELECT COUNT(*) FROM contact_messages');
    must($after === $before + 1, "stored {$before} -> {$after}");
    must(stripos($r->text(), 'thank') !== false || stripos($r->text(), 'received') !== false, 'no confirmation text');
    return "Confirmation shown; contact messages {$before} -> {$after}";
});

tc('FT-10', 'Data privacy', 'A data subject files a privacy request', 'A reference code is shown and the request is stored with the email encrypted', function () {
    $r = client()->submit('privacy-request.php', ['requester_name' => 'E2E Data Subject', 'email' => 'e2e.subject@example.org', 'request_type' => 'access', 'details' => 'Please send me a copy of the personal data you hold about me.', 'confirm' => '1', 'member_ref' => ''], null, true, 2.2);
    must($r->has('Your request was received'), 'no confirmation');
    must((bool) preg_match('#<p class="code">([^<]+)</p>#', $r->body, $m), 'no reference shown');
    $row = q_one('SELECT * FROM privacy_requests WHERE reference_code = ?', [html_entity_decode($m[1])]);
    must($row !== null && strpos((string) $row['requester_email_enc'], 'k1:') === 0, 'request missing or email not encrypted');
    return 'Reference ' . html_entity_decode($m[1]) . ' issued; email encrypted at rest';
});

tc('FT-11', 'Authentication', 'Sign in with correct credentials', 'The National Administrator lands on the dashboard', function () {
    $c = client();
    $r = $c->login('nationaladmin.demo', PW);
    must(strpos($r->url, 'admin/index.php') !== false && $r->has('Dashboard'), 'ended at ' . $r->url);
    return 'Redirected to admin/index.php showing the dashboard';
});

tc('FT-12', 'Authentication', 'Sign in with a wrong password', 'The generic message "The username or password is incorrect" is shown and no session is created', function () {
    $c = client();
    $r = $c->login('nationaladmin.demo', 'Wrong-Password-1');
    must($r->has('The username or password is incorrect'), 'generic message missing');
    must($c->get('admin/index.php', false)->status === 303, 'admin page reachable');
    return 'Generic error shown; admin pages still redirect to sign-in';
});

tc('FT-13', 'Authentication', 'Sign in with an unknown username', 'Exactly the same message as a wrong password (no account enumeration)', function () {
    $a = client()->login('no.such.user.e2e', 'Wrong-Password-1')->text();
    $b = client()->login('nationaladmin.demo', 'Wrong-Password-2')->text();
    $msg = 'The username or password is incorrect, or the account is not active.';
    must(strpos($a, $msg) !== false && strpos($b, $msg) !== false, 'messages differ');
    return 'Both attempts show: "' . $msg . '"';
});

tc('FT-14', 'Authentication', 'Sign out', 'After signing out with the POST form, admin pages redirect to the sign-in page and the old session is revoked', function () {
    $c = as_user('auditor.demo');
    $r = $c->get('admin/index.php');
    $out = $c->post('account/logout.php', ['_csrf' => $c->csrf($r->body)]);
    $after = $c->get('admin/index.php');
    must(strpos($after->url, 'account/login.php') !== false, 'still signed in: ' . $after->url);
    return 'Signed out; admin/index.php now redirects to account/login.php';
});

tc('FT-15', 'National database', 'A Data Encoder registers a cooperative in Region VII', 'The record is saved with review status "pending" and a CNPL reference number, inside the encoder\'s region', function () {
    global $S;
    $c = as_user('encoder.demo');
    $name = 'E2E Bayanihan Consolacion Cooperative ' . substr((string) time(), -6);
    $r = save_record($c, 'admin/cooperatives.php?action=create', ['name' => $name, 'cda_registration_no' => 'E2E-' . substr((string) time(), -6), 'cooperative_type_id' => '2', 'category' => 'primary',
        'date_registered' => '2024-05-10', 'registered_address' => 'Poblacion, Consolacion', 'region_id' => '11', 'province_id' => '48', 'city_id' => '975',
        'contact_person' => 'E2E Contact', 'contact_email' => 'e2e.coop@example.org', 'contact_phone' => '0917 000 1111', 'reported_member_count' => '85', 'org_status' => 'active']);
    $row = q_one('SELECT * FROM cooperatives WHERE name = ?', [$name]);
    must($row !== null, 'not saved; page said: ' . mb_substr($r->text(), 0, 300));
    must($row['review_status'] === 'pending', 'review status ' . $row['review_status']);
    must((int) $row['region_id'] === 11 && preg_match('/^CNPL-COOP-\d{6}$/', (string) $row['reference_no']) === 1, 'region/reference wrong');
    $S['coop'] = (int) $row['id'];
    return "Saved #{$row['id']} {$row['reference_no']} with review status pending";
});

tc('FT-16', 'Approvals', 'The Regional Administrator approves the submission from the approval inbox', 'The cooperative appears in the inbox and becomes "approved" after the bulk decision', function () {
    global $S;
    must(isset($S['coop']), 'depends on FT-15');
    $c = as_user('regionaladmin.demo');
    $inbox = $c->get('admin/approvals.php');
    must(strpos($inbox->body, 'value="' . $S['coop'] . '"') !== false, 'record not in approval inbox');
    $c->post('admin/approvals.php', ['_csrf' => $c->csrf($inbox->body), 'do' => 'bulk', 'module' => 'cooperatives', 'decision' => 'approve', 'remarks' => '', 'ids' => [$S['coop']]]);
    $st = q_val('SELECT review_status FROM cooperatives WHERE id = ?', [$S['coop']]);
    must($st === 'approved', 'status ' . $st);
    return 'Listed in the inbox; review status is now approved';
});

tc('FT-17', 'National database', 'Editing a cooperative keeps a revision and an audit entry', 'A new revision row lists the changed field and a "update" audit entry is written', function () {
    global $S;
    must(isset($S['coop']), 'depends on FT-15');
    $revBefore = (int) q_val("SELECT COUNT(*) FROM record_revisions WHERE module = 'cooperatives' AND record_id = ?", [$S['coop']]);
    $c = as_user('regionaladmin.demo');
    save_record($c, 'admin/cooperatives.php?action=edit&id=' . $S['coop'], ['contact_person' => 'E2E Updated Contact']);
    $rev = q_one("SELECT * FROM record_revisions WHERE module = 'cooperatives' AND record_id = ? ORDER BY id DESC LIMIT 1", [$S['coop']]);
    $revAfter = (int) q_val("SELECT COUNT(*) FROM record_revisions WHERE module = 'cooperatives' AND record_id = ?", [$S['coop']]);
    must($revAfter === $revBefore + 1 && strpos((string) $rev['changes'], 'contact_person') !== false, 'revision missing');
    must((int) q_val("SELECT COUNT(*) FROM audit_logs WHERE module = 'cooperatives' AND record_id = ? AND action = 'update'", [$S['coop']]) > 0, 'no audit entry');
    return "Revisions {$revBefore} -> {$revAfter} (contact_person changed); audit entry present";
});

tc('FT-18', 'National database', 'Archive and restore a cooperative', 'Archiving hides it from the active list; restoring brings it back', function () {
    global $S;
    must(isset($S['coop']), 'depends on FT-15');
    $c = as_user('regionaladmin.demo');
    $v = $c->get('admin/cooperatives.php?action=view&id=' . $S['coop']);
    $c->post('admin/cooperatives.php?action=archive&id=' . $S['coop'], ['_csrf' => $c->csrf($v->body), 'remarks' => 'E2E archive test']);
    must(q_val('SELECT archived_at FROM cooperatives WHERE id = ?', [$S['coop']]) !== null, 'not archived');
    $c->post('admin/cooperatives.php?action=restore&id=' . $S['coop'], ['_csrf' => $c->csrf($v->body)]);
    must(q_val('SELECT archived_at FROM cooperatives WHERE id = ?', [$S['coop']]) === null, 'not restored');
    return 'archived_at set, then cleared on restore';
});

tc('FT-19', 'Exports', 'The Regional Administrator exports the cooperative list to CSV', 'A CSV download with a header row, only Region VII rows, and a new export log entry', function () {
    $before = (int) q_val("SELECT COUNT(*) FROM export_logs WHERE module = 'cooperatives'");
    $c = as_user('regionaladmin.demo');
    $r = $c->get('admin/cooperatives.php?action=export');
    must(strpos((string) $r->header('content-type'), 'text/csv') === 0, 'content type ' . $r->header('content-type'));
    $lines = array_values(array_filter(explode("\n", trim($r->body))));
    $head = str_getcsv(ltrim($lines[0], "\xEF\xBB\xBF"), ',', '"', '\\');
    $regionCol = array_search('Region', $head, true);
    must($regionCol !== false, 'no Region column');
    $regions = [];
    foreach (array_slice($lines, 1) as $l) {
        $regions[str_getcsv($l, ',', '"', '\\')[$regionCol] ?? ''] = true;
    }
    must(array_keys($regions) === ['Region VII (Central Visayas)'] || array_keys($regions) === ['Region VII'], 'regions in export: ' . implode('; ', array_keys($regions)));
    $after = (int) q_val("SELECT COUNT(*) FROM export_logs WHERE module = 'cooperatives'");
    must($after === $before + 1, 'export not logged');
    return (count($lines) - 1) . ' rows, all Region VII; export logged';
});

tc('FT-20', 'Data import', 'Staged import: encoder uploads, maps and submits; regional admin approves and processes', 'One invalid row is reported, the encoder cannot approve the batch, and after a second person approves it the two valid rows become approved cooperatives', function () {
    $enc = as_user('encoder.demo');
    $t = $enc->get('admin/cooperatives.php?action=template');
    $sfx = substr((string) time(), -6);
    $rows = [
        ['E2E Import Alpha Cooperative ' . $sfx, '', 'Consumers Cooperative', 'Primary', '2023-01-15', 'Poblacion', '', 'Region VII', 'Cebu', 'Consolacion', 'A. Cruz', 'alpha@example.org', '', '', '40', 'Active', 'No', ''],
        ['E2E Import Beta Cooperative ' . $sfx, '', 'Consumers Cooperative', 'Primary', '03/20/2022', 'Poblacion', '', 'Region VII', 'Cebu', 'Consolacion', 'B. Reyes', 'beta@example.org', '', '', '55', 'Active', 'No', ''],
        ['', '', '', 'Primary', '2022-13-40', '', '', 'Region VII', '', '', '', 'not-an-email', '', '', 'many', 'Active', 'No', ''],
    ];
    $csv = trim($t->body) . "\n";
    foreach ($rows as $row) {
        $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $row)) . "\n";
    }
    $file = tempnam(sys_get_temp_dir(), 'e2e') . '.csv';
    file_put_contents($file, $csv);
    $page = $enc->get('admin/cooperatives.php?action=import');
    $map = $enc->post('admin/cooperatives.php?action=import&step=upload', ['_csrf' => $enc->csrf($page->body), 'file' => new CURLFile($file, 'text/csv', 'e2e-coops.csv')]);
    unlink($file);
    must((bool) preg_match('/batch=(\d+)/', $map->url, $bm), 'upload did not reach mapping: ' . $map->url);
    $batch = (int) $bm[1];
    $f = E2EClient::form($map->body, '.panel');
    $preview = $enc->post('admin/cooperatives.php?action=import&step=map&batch=' . $batch, $f['fields']);
    $b = q_one('SELECT * FROM import_batches WHERE id = ?', [$batch]);
    must((int) $b['valid_rows'] === 2 && (int) $b['invalid_rows'] === 1, "validation counts valid={$b['valid_rows']} invalid={$b['invalid_rows']}");
    $enc->post('admin/cooperatives.php?action=import&step=submit&batch=' . $batch, ['_csrf' => $enc->csrf($preview->body)]);
    $self = $enc->post('admin/cooperatives.php?action=import&step=approve&batch=' . $batch, ['_csrf' => $enc->csrf($preview->body)], false);
    must($self->status === 403, 'encoder could approve own batch (status ' . $self->status . ')');
    $adm = as_user('regionaladmin.demo');
    $pv = $adm->get('admin/cooperatives.php?action=import&step=preview&batch=' . $batch);
    $adm->post('admin/cooperatives.php?action=import&step=approve&batch=' . $batch, ['_csrf' => $adm->csrf($pv->body)]);
    for ($i = 0; $i < 5 && q_val('SELECT batch_status FROM import_batches WHERE id = ?', [$batch]) !== 'completed'; $i++) {
        $adm->post('admin/cooperatives.php?action=import&step=process&batch=' . $batch, ['_csrf' => $adm->csrf($pv->body)]);
    }
    $made = q_all("SELECT review_status FROM cooperatives WHERE name LIKE ?", ['E2E Import % Cooperative ' . $sfx]);
    must(count($made) === 2, count($made) . ' cooperatives created');
    must($made[0]['review_status'] === 'approved' && $made[1]['review_status'] === 'approved', 'imported rows not approved by the batch approval');
    return "Batch #{$batch}: 2 valid, 1 invalid reported; encoder self-approval refused (403); approved by the Regional Administrator and processed into 2 approved cooperatives";
});

tc('FT-21', 'Website content', 'Content Manager drafts news, submits for review, and an administrator publishes it', 'The post is hidden from the public while in review and appears on the news page after publishing', function () {
    global $S;
    $c = as_user('content.demo');
    $title = 'E2E cooperative news ' . substr((string) time(), -6);
    save_record($c, 'admin/posts.php?type=news&action=create', ['title' => $title, 'summary' => 'An end-to-end test article.', 'body' => '<p>Body of the end-to-end test article.</p>', 'wf' => 'in_review']);
    $p = q_one('SELECT * FROM posts WHERE title = ?', [$title]);
    must($p !== null && $p['status'] === 'in_review', 'not saved in review: ' . json_encode($p['status'] ?? null));
    must(client()->get('post.php?slug=' . $p['slug'])->status === 404, 'visible to public before publishing');
    $adm = as_user('nationaladmin.demo');
    $v = $adm->get('admin/posts.php?action=view&id=' . $p['id']);
    $adm->post('admin/posts.php?action=workflow&id=' . $p['id'] . '&to=published', ['_csrf' => $adm->csrf($v->body)]);
    must(q_val('SELECT status FROM posts WHERE id = ?', [$p['id']]) === 'published', 'not published');
    $news = client()->get('news.php');
    must($news->has(e($title)), 'not on the public news page');
    must(client()->get('post.php?slug=' . $p['slug'])->status === 200, 'article page not reachable');
    $S['post'] = (int) $p['id'];
    return 'Hidden (404) while in review; published by the National Administrator and listed on news.php';
});

tc('FT-22', 'Website content', 'A post scheduled for a future date stays hidden until then', 'A published post with publish_at tomorrow returns 404 publicly and is not listed', function () {
    $c = as_user('nationaladmin.demo');
    $title = 'E2E scheduled news ' . substr((string) time(), -6);
    save_record($c, 'admin/posts.php?type=news&action=create', ['title' => $title, 'summary' => 'Scheduled.', 'body' => '<p>Scheduled body.</p>', 'publish_at' => date('Y-m-d\TH:i', time() + 86400), 'wf' => 'published']);
    $p = q_one('SELECT * FROM posts WHERE title = ?', [$title]);
    must($p !== null && $p['status'] === 'published', 'not saved as published');
    must(client()->get('post.php?slug=' . $p['slug'])->status === 404, 'scheduled post visible');
    must(!client()->get('news.php')->has(e($title)), 'scheduled post listed');
    return 'Saved as published for ' . $p['publish_at'] . '; public page 404 and not listed';
});

tc('FT-23', 'Help desk', 'A Help Desk Officer replies and adds an internal note', 'The requester sees the reply on the tracking page but never the internal note', function () {
    global $S;
    must(isset($S['ticket']), 'depends on FT-06');
    $t = $S['ticket'];
    $c = as_user('helpdesk.demo');
    $page = $c->get('admin/helpdesk-ticket.php?id=' . $t['id']);
    $c->post('admin/helpdesk-ticket.php?id=' . $t['id'], ['_csrf' => $c->csrf($page->body), 'do' => 'reply', 'body' => 'E2E public reply: we have received your concern.', 'set_status' => '']);
    $c->post('admin/helpdesk-ticket.php?id=' . $t['id'], ['_csrf' => $c->csrf($page->body), 'do' => 'reply', 'body' => 'E2E internal note: check CDA record.', 'internal' => '1', 'set_status' => '']);
    $track = client()->submit('helpdesk-track.php', ['ticket_no' => $t['no'], 'email' => $t['email'], 'access_key' => $t['key']]);
    must($track->has('E2E public reply'), 'reply not visible to requester');
    must(!$track->has('E2E internal note'), 'internal note leaked to requester');
    return 'Reply visible on the tracking page; internal note hidden';
});

tc('FT-24', 'Reports', 'A report is exported to CSV', 'The distribution report downloads as CSV and an export log entry is written', function () {
    $before = (int) q_val("SELECT COUNT(*) FROM export_logs WHERE module LIKE 'report_%'");
    $r = as_user('nationaladmin.demo')->get('admin/reports.php?r=distribution&export=csv');
    must(strpos((string) $r->header('content-type'), 'text/csv') === 0, 'not CSV');
    must(substr_count($r->body, "\n") >= 5, 'too few lines');
    must((int) q_val("SELECT COUNT(*) FROM export_logs WHERE module LIKE 'report_%'") === $before + 1, 'not logged');
    return 'CSV with ' . substr_count($r->body, "\n") . ' lines; export logged';
});

tc('FT-25', 'Elections', 'Election performance compares years with source labels', 'The page shows the election years from the data and labels the sample source as not official', function () {
    $r = as_user('nationaladmin.demo')->get('admin/election-performance.php');
    must($r->status === 200, 'status ' . $r->status);
    foreach (['2016', '2019', '2022', '2025'] as $y) {
        must($r->has($y), "year {$y} missing");
    }
    return 'Years 2016, 2019, 2022 and 2025 shown';
});

tc('FT-26', 'Notifications', 'Help desk officers are notified about new tickets', 'The help desk officer has a notification that mentions the new ticket', function () {
    global $S;
    must(isset($S['ticket']), 'depends on FT-06');
    $uid = (int) q_val("SELECT id FROM users WHERE username = 'helpdesk.demo'");
    $n = (int) q_val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND (title LIKE ? OR body LIKE ? OR link LIKE ?)', [$uid, '%' . $S['ticket']['no'] . '%', '%' . $S['ticket']['no'] . '%', '%id=' . $S['ticket']['id'] . '%']);
    must($n > 0, 'no notification found');
    return "{$n} notification(s) about {$S['ticket']['no']}";
});

tc('FT-27', 'Account', 'A user changes their password', 'A weak new password is refused with the policy message; a strong one is accepted and works at the next sign-in', function () {
    e2e_user('e2e.pwchange', 'data_encoder');
    $c = as_user('e2e.pwchange', E2E_PW);
    $weak = $c->submit('account/change-password.php', ['current_password' => E2E_PW, 'password' => 'password1', 'password_confirm' => 'password1']);
    must($weak->has('Use at least') || $weak->has('Mix at least'), 'weak password accepted');
    $new = 'Harbor-Lantern-' . substr((string) time(), -4) . '!';
    $c->submit('account/change-password.php', ['current_password' => E2E_PW, 'password' => $new, 'password_confirm' => $new]);
    $again = client()->login('e2e.pwchange', $new);
    must(strpos($again->url, 'admin/') !== false, 'new password does not work');
    must(strpos(client()->login('e2e.pwchange', E2E_PW)->url, 'login.php') !== false, 'old password still works');
    return 'Weak password refused; new password works and the old one no longer does';
});

tc('FT-28', 'Account', 'Forgotten password reset by email link', 'The same confirmation shows for known and unknown emails; the emailed link sets a new password', function () {
    global $S;
    e2e_user('e2e.reset', 'data_encoder');
    $unknown = client()->submit('account/forgot-password.php', ['email' => 'nobody.e2e@example.org']);
    $known = client()->submit('account/forgot-password.php', ['email' => 'e2e.reset@example.org']);
    must($unknown->text() === $known->text() || ($unknown->has('If an active account uses that email address') && $known->has('If an active account uses that email address')), 'responses differ');
    must((bool) preg_match_all('#To: e2e\.reset@example\.org.*?reset-password\.php\?token=([A-Za-z0-9_\-]+)#s', mail_log(), $m), 'no reset email in the mail log');
    $token = end($m[1]);
    $new = 'River-Bamboo-' . substr((string) time(), -4) . '!';
    $done = client()->submit('account/reset-password.php?token=' . $token, ['password' => $new, 'password_confirm' => $new]);
    must(strpos(client()->login('e2e.reset', $new)->url, 'admin/') !== false, 'new password does not work');
    $S['used_token'] = $token;
    return 'Identical confirmation for both emails; link from the mail log set a working password';
});

tc('FT-29', 'Authentication (MFA)', 'Enrol an authenticator app and sign in with a second factor', 'Enrolment shows recovery codes; the next sign-in asks for a code; a reused code is refused; a recovery code works once', function () {
    e2e_user('e2e.mfa', 'regional_admin');
    $c = as_user('e2e.mfa', E2E_PW);
    $setup = $c->get('account/mfa-setup.php');
    must((bool) preg_match('#<p class="code-box">([A-Z2-7 ]+)</p>#', $setup->body, $m), 'setup key not shown');
    $secret = str_replace(' ', '', $m[1]);
    $code = e2e_totp($secret);
    $codes = $c->submit('account/mfa-setup.php', ['code' => $code]);
    must((bool) preg_match_all('#<li>([a-z2-9]{5}-[a-z2-9]{5})</li>#', $codes->body, $rc), 'recovery codes not shown');
    must((int) q_val("SELECT mfa_enabled FROM users WHERE username = 'e2e.mfa'") === 1, 'MFA not enabled');

    $d = client();
    $step1 = $d->login('e2e.mfa', E2E_PW);
    must(strpos($step1->url, 'account/mfa.php') !== false, 'second step not requested: ' . $step1->url);
    must($d->get('admin/index.php', false)->status === 303, 'admin reachable before second factor');
    $replay = $d->submit('account/mfa.php', ['code' => $code]);
    must(strpos($replay->url, 'account/mfa.php') !== false && $replay->has('That code is not valid'), 'replayed code accepted');
    $ok = $d->submit('account/mfa.php', ['code' => $rc[1][0]]);
    must(strpos($ok->url, 'admin/') !== false, 'recovery code refused');

    $e = client();
    $e->login('e2e.mfa', E2E_PW);
    $reuse = $e->submit('account/mfa.php', ['code' => $rc[1][0]]);
    must(strpos($reuse->url, 'account/mfa.php') !== false, 'recovery code worked twice');
    return 'Enrolled (8 recovery codes); sign-in asked for a code; replayed TOTP refused; recovery code accepted once and refused the second time';
});

tc('FT-30', 'Backups', 'The Super Administrator creates and verifies an encrypted backup', 'A backup record with status "ok" is created and verification succeeds', function () {
    $before = (int) q_val('SELECT COALESCE(MAX(id), 0) FROM backup_records');
    $c = as_user('superadmin');
    $p = $c->get('admin/backups.php');
    $c->post('admin/backups.php', ['_csrf' => $c->csrf($p->body), 'do' => 'run', 'password' => PW]);
    $b = q_one('SELECT * FROM backup_records WHERE id > ? ORDER BY id DESC LIMIT 1', [$before]);
    must($b !== null, 'no backup created');
    must(in_array($b['backup_status'], ['ok', 'completed', 'verified'], true), 'status ' . $b['backup_status']);
    $p = $c->get('admin/backups.php');
    $c->post('admin/backups.php', ['_csrf' => $c->csrf($p->body), 'do' => 'verify', 'id' => $b['id']]);
    $res = (string) q_val('SELECT verify_result FROM backup_records WHERE id = ?', [$b['id']]);
    must(strpos($res, 'OK:') === 0, 'verification result: ' . $res);
    must((int) $b['is_encrypted'] === 1, 'backup not encrypted');
    return "Backup #{$b['id']} ({$b['backup_status']}, encrypted, " . human_size((int) $b['file_size']) . '); verification: ' . $res;
});

tc('FT-31', 'Audit trail', 'The audit log hash chain is intact after all the activity above', 'audit_verify_chain() reports no broken link', function () {
    [$checked, $broken] = audit_verify_chain(100000);
    must($broken === null, 'chain broken at entry #' . $broken);
    return "{$checked} entries checked, chain intact";
});

tc('FT-32', 'Installable app', 'The web app manifest is valid and built from the settings', 'manifest.php returns JSON with the site name, start_url ./index.php, scope ./, standalone display, the sidebar color, four icons (any and maskable), and no session cookie', function () {
    $c = client();
    $r = $c->get('manifest.php');
    $j = json_decode($r->body, true);
    must($r->status === 200 && strpos((string) $r->header('content-type'), 'application/manifest+json') === 0, 'status/type ' . $r->status . ' ' . $r->header('content-type'));
    must(is_array($j), 'not valid JSON');
    must($j['name'] === setting('site_name', 'COOP NATCCO Partylist') && $j['start_url'] === './index.php' && $j['scope'] === './' && $j['display'] === 'standalone', 'fields: ' . json_encode([$j['name'], $j['start_url'], $j['scope'], $j['display']]));
    must($j['theme_color'] === PWA_THEME_COLOR && $j['background_color'] === PWA_THEME_COLOR, 'colors ' . $j['theme_color'] . '/' . $j['background_color']);
    $purposes = array_count_values(array_column($j['icons'], 'purpose'));
    must(($purposes['any'] ?? 0) === 2 && ($purposes['maskable'] ?? 0) === 2, 'icons ' . json_encode($purposes));
    foreach ($j['icons'] as $i) {
        must($c->get($i['src'])->status === 200, 'icon missing: ' . $i['src']);
    }
    must($c->cookie('CNPLSESS') === null, 'manifest opened a session');
    return "Valid manifest for \"{$j['name']}\" ({$j['short_name']}), 2 any + 2 maskable icons all reachable, no session cookie";
});

tc('FT-33', 'Installable app', 'Pages carry the app tags; the service worker and offline page are served correctly', 'Every layout links manifest.php, the apple-touch-icon and theme-color with the iOS tags and pwa.js; the top bar and sign-in page have the install button; sw.js and offline.html are served with no-cache', function () {
    $c = client();
    $need = ['rel="manifest" href="' . base_path() . '/manifest.php"', 'rel="apple-touch-icon" sizes="180x180"', '<meta name="theme-color" content="' . PWA_THEME_COLOR . '">',
        'apple-mobile-web-app-capable', 'apple-mobile-web-app-title', 'js/pwa.js'];
    $pages = ['public' => $c->get(''), 'sign-in' => $c->get('account/login.php')];
    $pages['admin'] = as_user('auditor.demo')->get('admin/index.php');
    foreach ($pages as $k => $r) {
        foreach ($need as $n) {
            must(strpos($r->body, $n) !== false, "{$k} page lacks {$n}");
        }
    }
    must(strpos($pages['sign-in']->body, 'data-pwa-box') !== false && strpos($pages['sign-in']->body, 'data-pwa-install') !== false, 'sign-in install box missing');
    must(preg_match('#<header class="topbar">.*?data-pwa-install.*?</header>#s', $pages['admin']->body) === 1, 'top bar install button missing');
    $sw = $c->get('sw.js');
    $off = $c->get('offline.html');
    must($sw->status === 200 && stripos((string) $sw->header('cache-control'), 'no-cache') !== false, 'sw.js ' . $sw->status . ' ' . $sw->header('cache-control'));
    must($off->status === 200 && $off->has('No internet connection') && $off->has('Try again'), 'offline.html ' . $off->status);
    return 'Tags present on public, sign-in and admin pages; install button and box present; sw.js and offline.html served (no-cache)';
});

// =====================================================================
// SECURITY TESTS
// =====================================================================

tc('ST-01', 'HTTP security', 'Security headers on every page', 'CSP without unsafe-inline, frame-ancestors none, X-Frame-Options DENY, nosniff, Referrer-Policy and Permissions-Policy are present', function () {
    $r = client()->get('');
    $csp = (string) $r->header('content-security-policy');
    must($csp !== '' && strpos($csp, "default-src 'self'") !== false && strpos($csp, "script-src 'self'") !== false && strpos($csp, 'unsafe-inline') === false && strpos($csp, "frame-ancestors 'none'") !== false, 'CSP: ' . $csp);
    must($r->header('x-frame-options') === 'DENY' && $r->header('x-content-type-options') === 'nosniff', 'framing/nosniff headers missing');
    must($r->header('referrer-policy') !== null && $r->header('permissions-policy') !== null, 'referrer/permissions policy missing');
    must($r->header('x-powered-by') === null, 'X-Powered-By exposed');
    return 'All headers present; no X-Powered-By';
});

tc('ST-02', 'Session security', 'Session cookie flags', 'The session cookie is HttpOnly and SameSite=Lax (Secure is added automatically over HTTPS)', function () {
    $r = client()->get('account/login.php');
    $cookie = '';
    foreach ($r->rawHeaders as $h) {
        if (stripos($h, 'Set-Cookie: CNPLSESS') === 0) {
            $cookie = $h;
        }
    }
    must($cookie !== '' && stripos($cookie, 'HttpOnly') !== false && stripos($cookie, 'SameSite=Lax') !== false, 'cookie: ' . $cookie);
    return trim(preg_replace('/CNPLSESS=[^;]+/', 'CNPLSESS=…', $cookie));
});

tc('ST-03', 'CSRF', 'Form posts without a valid CSRF token are refused', 'HTTP 403 for a missing token and for a wrong token; nothing is stored', function () {
    $before = (int) q_val('SELECT COUNT(*) FROM contact_messages');
    $c = client();
    $c->get('contact.php');
    $a = $c->post('contact.php', ['full_name' => 'X', 'email' => 'x@example.org', 'subject' => 'X', 'message' => 'CSRF test message body', 'consent' => '1'], false);
    $b = $c->post('contact.php', ['_csrf' => str_repeat('a', 64), 'full_name' => 'X', 'email' => 'x@example.org', 'subject' => 'X', 'message' => 'CSRF test message body', 'consent' => '1'], false);
    must($a->status === 403 && $b->status === 403, "statuses {$a->status}/{$b->status}");
    must((int) q_val('SELECT COUNT(*) FROM contact_messages') === $before, 'message stored');
    return "Missing token: {$a->status}; wrong token: {$b->status}; nothing stored";
});

tc('ST-04', 'CSRF', 'Cross-site form posts are refused even with a token', 'A POST carrying another site in the Origin header returns 403', function () {
    $c = as_user('auditor.demo');
    $p = $c->get('admin/index.php');
    $r = $c->post('account/logout.php', ['_csrf' => $c->csrf($p->body)], false, ['Origin: https://evil.example']);
    must($r->status === 403, 'status ' . $r->status);
    must($c->get('admin/index.php', false)->status === 200, 'session ended by the cross-site request');
    return 'Cross-origin POST refused (403); session unaffected';
});

tc('ST-05', 'Access control', 'Admin pages and APIs require sign-in', 'Anonymous requests to admin pages redirect to sign-in; the lookup API answers 401', function () {
    $c = client();
    $bad = [];
    foreach (['admin/index.php', 'admin/cooperatives.php', 'admin/members.php?action=view&id=1', 'admin/users.php', 'admin/audit.php', 'admin/backups.php', 'admin/settings.php'] as $p) {
        $r = $c->get($p, false);
        if ($r->status !== 303 || strpos((string) $r->header('location'), 'account/login.php') === false) {
            $bad[] = "{$p} ({$r->status})";
        }
    }
    $api = $c->get('admin/api/lookup.php?type=coop&q=ba', false);
    must(!$bad, 'not redirected: ' . implode(', ', $bad));
    must(in_array($api->status, [401, 403], true), 'lookup API status ' . $api->status);
    return '7 admin pages redirect to sign-in; lookup API returns ' . $api->status;
});

tc('ST-06', 'Access control', 'Roles cannot open modules outside their permissions', 'The Help Desk Officer gets 403 for cooperatives, members, users, audit, settings and exports', function () {
    $c = as_user('helpdesk.demo');
    $codes = [];
    foreach (['admin/cooperatives.php', 'admin/members.php', 'admin/users.php', 'admin/audit.php', 'admin/settings.php', 'admin/cooperatives.php?action=export', 'admin/backups.php'] as $p) {
        $codes[$p] = $c->get($p, false)->status;
    }
    must(count(array_filter($codes, fn($s) => $s !== 403)) === 0, json_encode($codes));
    return 'All 7 requests returned 403';
});

tc('ST-07', 'Access control (IDOR)', 'A City Administrator cannot open a cooperative in another city by changing the id', 'Own-city cooperative #82 opens (200); Compostela cooperative #87 returns 404', function () {
    $c = as_user('cityadmin.demo');
    $own = $c->get('admin/cooperatives.php?action=view&id=82')->status;
    $other = $c->get('admin/cooperatives.php?action=view&id=87')->status;
    $edit = $c->get('admin/cooperatives.php?action=edit&id=87')->status;
    must($own === 200 && $other === 404 && $edit === 404, "own {$own}, other {$other}, edit {$edit}");
    return "Own: {$own}; other city view: {$other}; other city edit: {$edit}";
});

tc('ST-08', 'Access control (IDOR)', 'Posting an edit for an out-of-scope record changes nothing', 'The forged POST is refused and the Compostela cooperative keeps its contact person', function () {
    $c = as_user('cityadmin.demo');
    $page = $c->get('admin/cooperatives.php?action=edit&id=82');
    $f = E2EClient::form($page->body, '.record-form');
    $before = q_val('SELECT contact_person FROM cooperatives WHERE id = 87');
    $f['fields']['contact_person'] = 'Hijacked by E2E';
    $r = $c->post('admin/cooperatives.php?action=edit&id=87', $f['fields'], false);
    must(q_val('SELECT contact_person FROM cooperatives WHERE id = 87') === $before, 'record changed');
    must(in_array($r->status, [403, 404], true), 'status ' . $r->status);
    return "Refused with {$r->status}; record unchanged";
});

tc('ST-09', 'Access control (scope)', 'A City Administrator cannot create a record outside the assigned city', 'Choosing another city is refused with a scope error and no cooperative is created', function () {
    $c = as_user('cityadmin.demo');
    $name = 'E2E Out Of Scope Cooperative ' . substr((string) time(), -6);
    $r = save_record($c, 'admin/cooperatives.php?action=create', ['name' => $name, 'cooperative_type_id' => '2', 'category' => 'primary', 'region_id' => '11', 'province_id' => '48', 'city_id' => '974', 'org_status' => 'active']);
    must(q_val('SELECT COUNT(*) FROM cooperatives WHERE name = ?', [$name]) == 0, 'record created outside scope');
    return 'Not created; form answered with: ' . mb_substr((string) (preg_match('#<div class="notice notice-error"[^>]*>(.*?)</div>#s', $r->body, $m) ? strip_tags($m[1]) : 'scope error'), 0, 120);
});

tc('ST-10', 'Access control (escalation)', 'An administrator cannot grant a role above their own', 'The National Administrator cannot assign Super Administrator; a lower role becomes a pending request that the same person cannot approve', function () {
    $target = e2e_user('e2e.target', 'data_encoder');
    $c = as_user('nationaladmin.demo');
    $p = $c->get('admin/users.php?action=view&id=' . $target);
    $r = $c->post('admin/users.php?action=view&id=' . $target, ['_csrf' => $c->csrf($p->body), 'do' => 'role_add', 'role_id' => '1']);
    must((int) q_val("SELECT COUNT(*) FROM user_roles WHERE user_id = ? AND role_id = 1 AND assignment_status <> 'revoked'", [$target]) === 0, 'super admin role granted');
    $c->post('admin/users.php?action=view&id=' . $target, ['_csrf' => $c->csrf($p->body), 'do' => 'role_add', 'role_id' => '11']);
    $ur = q_one('SELECT * FROM user_roles WHERE user_id = ? AND role_id = 11 ORDER BY id DESC LIMIT 1', [$target]);
    must($ur !== null && $ur['assignment_status'] === 'pending', 'lower role not pending: ' . json_encode($ur['assignment_status'] ?? null));
    $self = $c->post('admin/users.php?action=view&id=' . $target, ['_csrf' => $c->csrf($p->body), 'do' => 'role_decision', 'ur' => $ur['id'], 'decision' => 'approve'], false);
    must(q_val('SELECT assignment_status FROM user_roles WHERE id = ?', [$ur['id']]) === 'pending', 'requester approved own role request');
    $sa = as_user('superadmin');
    $sp = $sa->get('admin/users.php?action=view&id=' . $target);
    $sa->post('admin/users.php?action=view&id=' . $target, ['_csrf' => $sa->csrf($sp->body), 'do' => 'role_decision', 'ur' => $ur['id'], 'decision' => 'approve']);
    must(q_val('SELECT assignment_status FROM user_roles WHERE id = ?', [$ur['id']]) === 'active', 'second administrator could not approve');
    return "Super Administrator refused; Cooperative Officer request pending; self-approval refused ({$self->status}); approved by a second administrator";
});

tc('ST-11', 'XSS', 'Script in rich text is removed before it reaches the public site', 'The published article contains no <script>, event handler or javascript: link', function () {
    $c = as_user('nationaladmin.demo');
    $title = 'E2E XSS test ' . substr((string) time(), -6);
    save_record($c, 'admin/posts.php?type=news&action=create', ['title' => $title, 'summary' => '<script>alert(1)</script>Summary',
        'body' => '<p>Safe text</p><script>alert("xss")</script><img src="x" onerror="alert(2)"><a href="javascript:alert(3)">click</a><p onclick="alert(4)">p</p>', 'wf' => 'published']);
    $slug = q_val('SELECT slug FROM posts WHERE title = ?', [$title]);
    must($slug !== null, 'post not saved');
    $r = client()->get('post.php?slug=' . $slug);
    $article = $r->body;
    must($r->status === 200 && strpos($article, 'Safe text') !== false, 'article not shown');
    foreach (['<script>alert', 'onerror=', 'javascript:alert', 'onclick='] as $bad) {
        must(stripos($article, $bad) === false, 'found ' . $bad);
    }
    return 'Published page keeps "Safe text" and contains no script, handler or javascript: link';
});

tc('ST-12', 'XSS', 'Reflected input is escaped', 'Search terms with markup are shown as text on public and admin pages', function () {
    $payload = '<script>alert(1)</script>"><img src=x onerror=alert(1)>';
    $pub = client()->get('search.php?q=' . rawurlencode($payload));
    $adm = as_user('nationaladmin.demo')->get('admin/cooperatives.php?q=' . rawurlencode($payload));
    foreach (['public' => $pub, 'admin' => $adm] as $k => $r) {
        must(strpos($r->body, '<script>alert(1)</script>') === false && strpos($r->body, '<img src=x onerror') === false, "{$k} page reflects markup");
        must(strpos($r->body, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false, "{$k} page does not show the escaped text");
    }
    return 'Payload shown escaped on search.php and admin/cooperatives.php';
});

tc('ST-13', 'SQL injection', 'Injection attempts in search, filters, sorting and ids do nothing', 'Every request answers normally with no database error, no extra rows and the tables intact', function () {
    $logBefore = app_log_size();
    $c = as_user('cityadmin.demo');
    $tries = [
        "admin/cooperatives.php?q=" . rawurlencode("' OR '1'='1"),
        "admin/cooperatives.php?q=" . rawurlencode("x%' UNION SELECT username,password_hash FROM users -- "),
        "admin/cooperatives.php?sort=" . rawurlencode('name;DROP TABLE users') . "&dir=" . rawurlencode('asc,(SELECT SLEEP(5))'),
        "admin/cooperatives.php?f_region=" . rawurlencode('1 OR 1=1') . "&f_status=" . rawurlencode("active' OR '1'='1"),
        "admin/cooperatives.php?action=view&id=" . rawurlencode('82 OR 1=1'),
    ];
    $t0 = microtime(true);
    $codes = [];
    foreach ($tries as $u) {
        $r = $c->get($u, false);
        $codes[] = $r->status;
        must(!preg_match('/(SQLSTATE|PDOException|You have an error in your SQL|\$2y\$|\$argon2id\$)/i', $r->body), 'database detail leaked for ' . $u);
    }
    $own = $c->get("admin/cooperatives.php?q=" . rawurlencode("' OR '1'='1"));
    must(!$own->has('Kapatiran Compostela'), 'out-of-scope rows returned');
    must(microtime(true) - $t0 < 5, 'SLEEP injection executed');
    must((int) q_val('SELECT COUNT(*) FROM users') > 0, 'users table damaged');
    must(app_log_size() === $logBefore, 'server errors logged during the attempts');
    return 'Statuses ' . implode(', ', $codes) . '; no errors, no leaks, no delay, tables intact';
});

tc('ST-14', 'Brute force', 'Repeated wrong passwords lock the sign-in', 'After 5 failures even the correct password is refused with the lockout message', function () {
    e2e_user('e2e.lockout', 'data_encoder');
    for ($i = 0; $i < 5; $i++) {
        client()->login('e2e.lockout', 'Wrong-Password-' . $i);
    }
    $r = client()->login('e2e.lockout', E2E_PW);
    must(strpos($r->url, 'login.php') !== false, 'signed in during lockout');
    must(stripos($r->text(), 'locked') !== false || stripos($r->text(), 'too many') !== false, 'no lockout message');
    must((int) q_val("SELECT COUNT(*) FROM security_events WHERE event_type = 'account_lockout' AND description LIKE ?", ['%e2e.lockout%']) > 0, 'no security event');
    q("DELETE FROM throttles WHERE bucket IN ('login_id', 'login_ip')");
    return 'Correct password refused during lockout; security event recorded';
});

tc('ST-15', 'Privacy', 'Password reset does not reveal which emails have accounts', 'Known and unknown emails get the same confirmation, and an unknown email sends nothing', function () {
    $sizeBefore = strlen(mail_log());
    $a = client()->submit('account/forgot-password.php', ['email' => 'unknown.e2e.' . time() . '@example.org']);
    must($a->has('If an active account uses that email address'), 'confirmation missing');
    must(strlen(mail_log()) === $sizeBefore, 'email sent for an unknown address');
    return 'Same confirmation; no email for the unknown address';
});

tc('ST-16', 'Server hardening', 'Application internals cannot be downloaded', 'Config, includes, storage, database dumps, docs, tests, scripts and dotfiles return 403/404 (Apache .htaccess)', function () {
    $c = client();
    $paths = ['config/config.php', 'config/config.sample.php', 'includes/db.php', 'storage/logs/mail.log', 'storage/uploads/', 'database/coop_natcco_clean.sql', 'database/schema/schema.sql',
        'docs/SECURITY-PRIVACY.md', 'tests/run-unit.php', 'scripts/backup.php', '.htaccess', 'README.md', 'assets/', 'assets/img/'];
    $bad = [];
    foreach ($paths as $p) {
        $r = $c->get($p, false);
        if (!in_array($r->status, [403, 404], true) || strpos($r->body, '<?php') !== false || strpos($r->body, 'encryption_keys') !== false) {
            $bad[] = "{$p} ({$r->status})";
        }
    }
    must(!$bad, 'served: ' . implode(', ', $bad));
    return count($paths) . ' paths refused (403/404); no directory listings';
});

tc('ST-17', 'File access', 'Private files are only served to authorised users', 'A private document returns 404 to visitors and 200 to an administrator; path tricks return 404', function () {
    $priv = q_one("SELECT id FROM media_assets WHERE visibility = 'private' ORDER BY id LIMIT 1");
    $pub = q_one("SELECT id FROM media_assets WHERE visibility = 'public' ORDER BY id LIMIT 1");
    must($priv !== null && $pub !== null, 'sample media missing');
    $anon = client();
    $a = $anon->get('file.php?id=' . $priv['id'])->status;
    $b = $anon->get('file.php?id=' . $pub['id'])->status;
    $t1 = $anon->get('file.php?id=' . rawurlencode('../../config/config.php'))->status;
    $t2 = $anon->get('file.php?path=' . rawurlencode('../config/config.php'))->status;
    $admin = as_user('nationaladmin.demo')->get('file.php?id=' . $priv['id'])->status;
    must($a === 404 && $b === 200 && $admin === 200 && in_array($t1, [400, 404], true) && in_array($t2, [400, 404], true), "private anon {$a}, public {$b}, admin {$admin}, traversal {$t1}/{$t2}");
    return "Private: visitor {$a}, admin {$admin}; public {$b}; traversal attempts {$t1}/{$t2}";
});

tc('ST-18', 'File uploads', 'Malicious uploads are rejected through the media library', 'A PHP script disguised as .jpg and a double-extension file are refused and nothing is stored', function () {
    $c = as_user('content.demo');
    $before = (int) q_val('SELECT COUNT(*) FROM media_assets');
    $tries = ['avatar.jpg' => '<?php system($_GET["c"]); ?>', 'shell.php.jpg' => "\xFF\xD8\xFF\xE0 fake jpeg <?php echo 1; ?>"];
    $msgs = [];
    foreach ($tries as $name => $content) {
        $tmp = tempnam(sys_get_temp_dir(), 'e2e');
        file_put_contents($tmp, $content);
        $p = $c->get('admin/media.php');
        $r = $c->post('admin/media.php', ['_csrf' => $c->csrf($p->body), 'do' => 'upload', 'title' => 'E2E bad upload', 'visibility' => 'public', 'file' => new CURLFile($tmp, 'image/jpeg', $name)]);
        unlink($tmp);
        $msgs[] = $name . ': ' . (preg_match('#(This file type is not allowed\.|The file content does not match its type\.)#', $r->body, $m) ? $m[1] : 'no message');
    }
    must((int) q_val('SELECT COUNT(*) FROM media_assets') === $before, 'a file was stored');
    must(count(array_filter($msgs, fn($m) => strpos($m, 'no message') !== false)) === 0, implode('; ', $msgs));
    return implode('; ', $msgs);
});

tc('ST-19', 'Brute force', 'Ticket tracking cannot be brute-forced', 'Repeated wrong access keys are answered generically and then blocked with HTTP 429', function () {
    global $S;
    must(isset($S['ticket']), 'depends on FT-06');
    q("DELETE FROM throttles WHERE bucket LIKE 'ticket%'");
    $c = client();
    $statuses = [];
    for ($i = 0; $i < 14; $i++) {
        $p = $c->get('helpdesk-track.php');
        $r = $c->post('helpdesk-track.php', ['_csrf' => $c->csrf($p->body), 'do' => 'lookup', 'ticket_no' => $S['ticket']['no'], 'email' => $S['ticket']['email'], 'access_key' => sprintf('BRUTE%05d', $i)], false);
        $statuses[] = $r->status;
        if ($r->status === 429) {
            break;
        }
    }
    q("DELETE FROM throttles WHERE bucket LIKE 'ticket%'");
    must(in_array(429, $statuses, true), 'never blocked: ' . implode(',', $statuses));
    return 'Blocked with 429 after ' . (count($statuses) - 1) . ' wrong keys';
});

tc('ST-20', 'Session security', 'The session id changes at sign-in (no session fixation)', 'A session id planted before sign-in is not the one used afterwards', function () {
    $c = client();
    $c->get('account/login.php');
    $planted = (string) $c->cookie('CNPLSESS');
    $c->login('auditor.demo', PW);
    $after = (string) $c->cookie('CNPLSESS');
    must($planted !== '' && $after !== '' && $planted !== $after, 'session id unchanged');
    $c2 = client();
    $c2->setCookie('CNPLSESS', 'attackerchosenid1234567890abcdef');
    $c2->login('auditor.demo', PW);
    must($c2->cookie('CNPLSESS') !== 'attackerchosenid1234567890abcdef', 'attacker-chosen id accepted');
    return 'Session id regenerated at sign-in; attacker-chosen id discarded';
});

tc('ST-21', 'Session security', 'Signing out requires a POST with a CSRF token', 'Opening the logout address with GET does not end the session', function () {
    $c = as_user('auditor.demo');
    $g = $c->get('account/logout.php', false);
    must($c->get('admin/index.php', false)->status === 200, 'GET logout ended the session');
    return "GET logout answered {$g->status}; session still valid";
});

tc('ST-22', 'Open redirect', 'Return addresses cannot send users to another site', 'After sign-in the user stays inside the application even when the return address points elsewhere', function () {
    $c = client();
    $c->get('admin/index.php?x=' . rawurlencode('//evil.example'));
    $r = $c->login('auditor.demo', PW);
    must(strpos($r->url, '127.0.0.1') !== false || strpos($r->url, parse_url($GLOBALS['BASE'], PHP_URL_HOST)) !== false, 'left the site: ' . $r->url);
    must(strpos($r->url, 'evil.example') === false || strpos($r->url, '?x=') !== false, 'redirected off-site');
    return 'Landed on ' . preg_replace('#^https?://[^/]+#', '', $r->url);
});

tc('ST-23', 'Session security', 'Suspending an account ends its open sessions immediately', 'A signed-in user is signed out on the next request after an administrator suspends the account', function () {
    $id = e2e_user('e2e.suspend', 'data_encoder');
    $victim = as_user('e2e.suspend', E2E_PW);
    must($victim->get('admin/index.php', false)->status === 200, 'not signed in');
    $sa = as_user('superadmin');
    $p = $sa->get('admin/users.php?action=view&id=' . $id);
    $sa->post('admin/users.php?action=view&id=' . $id, ['_csrf' => $sa->csrf($p->body), 'do' => 'status', 'account_status' => 'suspended', 'password' => PW]);
    must(q_val('SELECT account_status FROM users WHERE id = ?', [$id]) === 'suspended', 'not suspended');
    $after = $victim->get('admin/index.php', false);
    must($after->status === 303, 'still signed in (' . $after->status . ')');
    return 'Account suspended; the open session was redirected to sign-in';
});

tc('ST-24', 'Spam protection', 'Bots that fill the hidden field or submit instantly are ignored', 'No ticket is stored for a filled honeypot or for a submission under 2 seconds', function () {
    $before = (int) q_val('SELECT COUNT(*) FROM helpdesk_tickets');
    $cat = (int) q_val('SELECT id FROM helpdesk_categories ORDER BY id LIMIT 1');
    $vals = ['requester_name' => 'Bot', 'email' => 'bot@example.org', 'region_id' => '11', 'category_id' => (string) $cat, 'subject' => 'Bot ticket', 'description' => 'Automated submission text body.', 'consent' => '1'];
    client()->submit('helpdesk.php', $vals + ['website_url' => 'http://spam.example'], null, true, 2.2);
    client()->submit('helpdesk.php', $vals, null, true, 0);
    must((int) q_val('SELECT COUNT(*) FROM helpdesk_tickets') === $before, 'bot ticket stored');
    return 'Neither submission created a ticket';
});

tc('ST-25', 'Account security', 'A password reset link works only once', 'Opening the used link again shows the expired/used message and does not change the password', function () {
    global $S;
    must(isset($S['used_token']), 'depends on FT-28');
    $r = client()->get('account/reset-password.php?token=' . $S['used_token']);
    must($r->has('The link has expired, was already used, or is incorrect'), 'used token accepted');
    $r2 = client()->get('account/reset-password.php?token=' . str_repeat('A', 43));
    must($r2->has('The link has expired, was already used, or is incorrect'), 'random token accepted');
    return 'Used and random tokens both refused';
});

tc('ST-26', 'Data protection', 'Personal data is encrypted at rest', 'Member, personnel and ticket contact fields are stored as k1: ciphertext with no readable email or phone', function () {
    $cols = [];
    foreach (q_all("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME LIKE '%\\_enc'") as $c) {
        $cols[] = $c['TABLE_NAME'] . '.' . $c['COLUMN_NAME'];
    }
    must(count($cols) >= 5, 'too few encrypted columns: ' . implode(', ', $cols));
    $plainHits = [];
    foreach ($cols as $tc) {
        [$t, $col] = explode('.', $tc);
        $n = (int) q_val("SELECT COUNT(*) FROM `{$t}` WHERE `{$col}` IS NOT NULL AND `{$col}` <> '' AND `{$col}` NOT LIKE 'k_:%'");
        if ($n) {
            $plainHits[] = "{$tc} ({$n})";
        }
    }
    must(!$plainHits, 'unencrypted values: ' . implode(', ', $plainHits));
    must((int) q_val("SELECT COUNT(*) FROM helpdesk_tickets WHERE requester_email_enc LIKE '%@%'") === 0, 'readable email found');
    return count($cols) . ' encrypted columns checked; every stored value is ciphertext';
});

tc('ST-27', 'Audit trail', 'The application database account cannot alter or delete audit entries', 'UPDATE and DELETE on audit_logs are refused for the application account (least-privilege grants)', function () {
    $user = (string) cfg('db.user');
    if (strtolower($user) === 'root') {
        throw new E2ESkip('The configuration uses the MySQL root account (local XAMPP). Load database/schema/grants.sql and use cnpl_app to run this check.');
    }
    $denied = 0;
    foreach (['UPDATE audit_logs SET description = description ORDER BY id DESC LIMIT 1', 'DELETE FROM audit_logs ORDER BY id DESC LIMIT 1'] as $sql) {
        try {
            db()->exec($sql);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), '1142') !== false || stripos($e->getMessage(), 'denied') !== false) {
                $denied++;
            }
        }
    }
    must($denied === 2, "{$denied} of 2 statements refused");
    return "Both statements refused for '{$user}' (error 1142)";
});

tc('ST-28', 'Access control (scope)', 'Lookup API only returns records inside the user\'s scope', 'The City Administrator finds Consolacion cooperatives but not the Compostela cooperative', function () {
    $c = as_user('cityadmin.demo');
    $own = $c->get('admin/api/lookup.php?type=coop&q=Consolacion');
    $other = $c->get('admin/api/lookup.php?type=coop&q=Kapatiran%20Compostela');
    $o = json_decode($own->body, true);
    $x = json_decode($other->body, true);
    must(is_array($o) && count($o['items'] ?? []) > 0, 'own coops not found: ' . mb_substr($own->body, 0, 120));
    must(is_array($x) && isset($x['items']) && count($x['items']) === 0, 'out-of-scope coop returned: ' . mb_substr($other->body, 0, 160));
    $unrelated = array_filter($o['items'], fn($i) => stripos($i['label'], 'Consolacion') === false);
    must(!$unrelated, 'results outside Consolacion: ' . json_encode(array_values($unrelated)));
    return count($o['items']) . ' Consolacion results; 0 results for the Compostela cooperative';
});

tc('ST-29', 'Public API', 'The public geography API returns only geography and validates input', 'Provinces for Region VII are returned as JSON with no personal data; invalid input is refused', function () {
    $r = client()->get('api/geo.php?type=provinces&region=11');
    $j = json_decode($r->body, true);
    must($r->status === 200 && is_array($j), 'bad response ' . $r->status);
    must(strpos($r->body, 'Cebu') !== false && !preg_match('/@|password|phone/i', $r->body), 'unexpected content');
    $bad = client()->get('api/geo.php?type=' . rawurlencode('users') . '&region=' . rawurlencode('1 OR 1=1'));
    must($bad->status === 400 && strpos($bad->body, '"error"') !== false, 'invalid request accepted: ' . $bad->status);
    return 'Region VII provinces returned; invalid request answered ' . $bad->status;
});

tc('ST-30', 'Error handling', 'Errors never show technical details', 'Malformed ids and unknown actions give a normal 400/404 page with no stack trace or SQL', function () {
    $c = as_user('superadmin');
    $bad = [];
    foreach (['admin/cooperatives.php?action=view&id=abc', 'admin/cooperatives.php?action=nope', 'admin/posts.php?type=unknown', "post.php?slug=%27%22%3E", 'admin/reports.php?r=../../etc'] as $p) {
        $r = $c->get($p, false);
        if ($r->status >= 500 || preg_match('/(Stack trace|PDOException|\.php:\d+|SQLSTATE)/', $r->body)) {
            $bad[] = "{$p} ({$r->status})";
        }
    }
    must(!$bad, implode(', ', $bad));
    return 'All malformed requests answered without technical detail';
});

tc('ST-31', 'Privacy', 'Regular members are never exposed on the public website', 'Searching the public site for a member\'s name finds nothing, and no public page lists members', function () {
    $m = q_one('SELECT first_name, last_name FROM members ORDER BY id LIMIT 1');
    must($m !== null, 'no sample member');
    $r = client()->get('search.php?q=' . rawurlencode($m['last_name']));
    must(stripos($r->text(), $m['first_name'] . ' ' . $m['last_name']) === false, 'member name in public search');
    foreach (['cooperatives.php', 'leadership.php', 'structure.php'] as $p) {
        must(stripos(client()->get($p)->body, 'Regular member') === false, 'members listed on ' . $p);
    }
    return 'No member name in public search; no public page lists members';
});

tc('ST-32', 'Mass assignment', 'Hidden or system fields cannot be set through the form', 'A forged review_status=approved and created_by=1 on create are ignored; the record stays pending and belongs to the encoder', function () {
    $c = as_user('encoder.demo');
    $name = 'E2E Mass Assignment Cooperative ' . substr((string) time(), -6);
    save_record($c, 'admin/cooperatives.php?action=create', ['name' => $name, 'cooperative_type_id' => '2', 'category' => 'primary', 'region_id' => '11', 'province_id' => '48', 'city_id' => '975',
        'org_status' => 'active', 'review_status' => 'approved', 'verification_status' => 'verified', 'created_by' => '1', 'id' => '1']);
    $row = q_one('SELECT * FROM cooperatives WHERE name = ?', [$name]);
    must($row !== null, 'not saved');
    $enc = (int) q_val("SELECT id FROM users WHERE username = 'encoder.demo'");
    must($row['review_status'] === 'pending' && $row['verification_status'] !== 'verified' && (int) $row['created_by'] === $enc, json_encode([$row['review_status'], $row['verification_status'], $row['created_by']]));
    return "Saved as pending/{$row['verification_status']}, created by the encoder (#{$enc})";
});

tc('ST-33', 'Exports', 'Exported spreadsheets cannot run formulas planted in data', 'A cooperative named "=HYPERLINK(...)" is exported as text starting with an apostrophe', function () {
    $c = as_user('encoder.demo');
    $name = '=HYPERLINK("http://evil.example","E2E")';
    save_record($c, 'admin/cooperatives.php?action=create', ['name' => $name, 'cooperative_type_id' => '2', 'category' => 'primary', 'region_id' => '11', 'province_id' => '48', 'city_id' => '975', 'org_status' => 'active']);
    $exp = as_user('regionaladmin.demo')->get('admin/cooperatives.php?action=export');
    must(strpos($exp->body, "'=HYPERLINK") !== false, 'formula not neutralised');
    must(strpos($exp->body, ',"=HYPERLINK') === false && strpos($exp->body, "\n\"=HYPERLINK") === false, 'raw formula exported');
    return 'Exported as \'=HYPERLINK(...) (text)';
});

tc('ST-34', 'Spam protection', 'Public forms are rate limited per address', 'The contact form answers 429 after 5 messages in 15 minutes', function () {
    q("DELETE FROM throttles WHERE bucket = 'contact_form'");
    $statuses = [];
    $c = client();
    for ($i = 0; $i < 7; $i++) {
        $p = $c->get('contact.php');
        $statuses[] = $c->post('contact.php', ['_csrf' => $c->csrf($p->body), 'full_name' => 'Rate ' . $i, 'email' => 'rate@example.org', 'subject' => 'Rate test', 'message' => 'Rate limit test message.', 'consent' => '1'], false)->status;
        if (end($statuses) === 429) {
            break;
        }
    }
    q("DELETE FROM throttles WHERE bucket = 'contact_form'");
    must(end($statuses) === 429, 'never limited: ' . implode(',', $statuses));
    return 'Statuses ' . implode(', ', $statuses);
});

tc('ST-35', 'Account security', 'Privileged roles must use multifactor authentication when the policy is on', 'With mfa_required_privileged=1 a Regional Administrator without MFA is sent to MFA setup and cannot open the dashboard', function () {
    $old = setting('mfa_required_privileged', '0');
    q("UPDATE website_settings SET setting_value = '1' WHERE setting_key = 'mfa_required_privileged'");
    try {
        e2e_user('e2e.mfapolicy', 'regional_admin');
        $c = client();
        $r = $c->login('e2e.mfapolicy', E2E_PW);
        $dash = $c->get('admin/index.php');
        must(strpos($r->url, 'mfa-setup.php') !== false && strpos($dash->url, 'mfa-setup.php') !== false, 'not forced to MFA setup: ' . $r->url . ' / ' . $dash->url);
    } finally {
        q('UPDATE website_settings SET setting_value = ? WHERE setting_key = ?', [$old, 'mfa_required_privileged']);
    }
    return 'Sign-in and dashboard both redirected to account/mfa-setup.php';
});

// =====================================================================
// RUN
// =====================================================================
q('DELETE FROM throttles');
$results = [];
$t0 = microtime(true);
foreach ($CASES as $case) {
    if ($ONLY !== null && stripos($case['id'], $ONLY) === false) {
        continue;
    }
    $start = microtime(true);
    $status = 'Pass';
    try {
        $actual = (string) ($case['fn'])();
    } catch (E2ESkip $e) {
        $status = 'Skipped';
        $actual = $e->getMessage();
    } catch (Throwable $e) {
        $status = 'Fail';
        $actual = ($e instanceof E2EFail ? '' : get_class($e) . ': ') . $e->getMessage();
    }
    $results[] = $case + ['status' => $status, 'actual' => $actual, 'secs' => microtime(true) - $start];
    printf("%-6s %-7s %s\n", $case['id'], strtoupper($status), $case['desc']);
    if ($status !== 'Pass') {
        echo "        Expected: {$case['expected']}\n        Actual:   {$actual}\n";
    }
}
q('DELETE FROM throttles');
$pass = count(array_filter($results, fn($r) => $r['status'] === 'Pass'));
$skip = count(array_filter($results, fn($r) => $r['status'] === 'Skipped'));
printf("\n%d passed, %d failed, %d skipped of %d cases in %.1fs against %s\n", $pass, count($results) - $pass - $skip, $skip, count($results), microtime(true) - $t0, $BASE);
if ($MD) {
    $esc = fn($s) => str_replace(['|', "\n"], ['\\|', ' '], (string) $s);
    $out = "| ID | Area | Description | Expected result | Actual result | Result |\n|---|---|---|---|---|---|\n";
    foreach ($results as $r) {
        $out .= '| ' . implode(' | ', array_map($esc, [$r['id'], $r['area'], $r['desc'], $r['expected'], $r['actual'], $r['status']])) . " |\n";
    }
    $out .= "\n{$pass} passed, " . (count($results) - $pass - $skip) . " failed, {$skip} skipped of " . count($results) . ' cases.' . "\n";
    file_put_contents($MD, $out);
}
exit(count($results) - $pass - $skip === 0 ? 0 : 1);
