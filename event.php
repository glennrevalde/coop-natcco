<?php
/**
 * Event details, calendar file and registration (with capacity and waiting list).
 */
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

$p = [get('slug')];
$w = pub_where('x', $p);
$ev = q_one("SELECT x.*, cc.name AS category_name, r.name AS region_name FROM events x LEFT JOIN content_categories cc ON cc.id = x.category_id LEFT JOIN regions r ON r.id = x.region_id WHERE x.slug = ? AND {$w}", $p);
if (!$ev || ($ev['visibility'] === 'private' && !current_user())) {
    not_found();
}

if (get('ics') === '1') {
    $fmt = fn($d) => gmdate('Ymd\THis\Z', strtotime($d));
    $esc = fn($s) => str_replace(["\\", ";", ",", "\n"], ["\\\\", "\\;", "\\,", "\\n"], (string) $s);
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $ev['slug'] . '.ics"');
    echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//COOP NATCCO Partylist//Events//EN\r\nBEGIN:VEVENT\r\nUID:event-" . (int) $ev['id'] . '@' . preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n"
        . 'DTSTAMP:' . $fmt(now()) . "\r\nDTSTART:" . $fmt($ev['starts_at']) . "\r\nDTEND:" . $fmt($ev['ends_at'] ?: date('Y-m-d H:i:s', strtotime($ev['starts_at']) + 7200)) . "\r\n"
        . 'SUMMARY:' . $esc($ev['title']) . "\r\nLOCATION:" . $esc(trim($ev['location_name'] . ' ' . $ev['venue_address'])) . "\r\nDESCRIPTION:" . $esc(excerpt($ev['summary'] ?: $ev['description'], 500)) . "\r\nURL:" . abs_url('event.php', ['slug' => $ev['slug']]) . "\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
    exit;
}

$counts = q_pairs('SELECT registration_status, COUNT(*) FROM event_registrations WHERE event_id = ? GROUP BY registration_status', [$ev['id']]);
$taken = (int) ($counts['registered'] ?? 0) + (int) ($counts['attended'] ?? 0);
$full = $ev['registration_limit'] && $taken >= (int) $ev['registration_limit'];
$open = $ev['registration_enabled'] && $ev['status'] === 'published' && $ev['event_status'] === 'scheduled' && strtotime($ev['starts_at']) > time()
    && (!$ev['registration_deadline'] || strtotime($ev['registration_deadline']) > time());
$errors = [];
$done = null;
$u = current_user();
$vals = ['full_name' => $u['full_name'] ?? '', 'email' => $u['email'] ?? '', 'phone' => '', 'organization' => ''];

if ($open && is_post()) {
    public_form_limit('event_register', 8, 600);
    foreach ($vals as $k => $v) {
        $vals[$k] = mb_substr(post($k), 0, $k === 'organization' ? 191 : 150);
    }
    if (honeypot_tripped()) {
        $errors['full_name'] = 'Your submission could not be accepted. Please try again.';
    }
    if ($vals['full_name'] === '') {
        $errors['full_name'] = 'Enter your full name.';
    }
    if (!filter_var($vals['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if ($vals['phone'] !== '' && !preg_match('/^[0-9+()\-\s.]{5,40}$/', $vals['phone'])) {
        $errors['phone'] = 'Enter a valid phone number.';
    }
    if (post('consent') !== '1') {
        $errors['consent'] = 'Please agree to the processing of your registration details.';
    }
    if (!$errors && q_val('SELECT id FROM event_registrations WHERE event_id = ? AND email_bidx = ?', [$ev['id'], blind_index($vals['email'])])) {
        $errors['email'] = 'This email address is already registered for this event.';
    }
    if (!$errors) {
        $status = $full ? 'waitlisted' : 'registered';
        $ref = 'EV' . random_code(8);
        q_insert('event_registrations', ['event_id' => $ev['id'], 'reference_code' => $ref, 'full_name' => $vals['full_name'], 'email_enc' => encrypt_value($vals['email']), 'email_bidx' => blind_index($vals['email']),
            'phone_enc' => encrypt_value($vals['phone'] ?: null), 'organization' => $vals['organization'] ?: null, 'user_id' => $u['id'] ?? null, 'registration_status' => $status, 'consent_given' => 1,
            'ip_address' => client_ip(), 'created_at' => now()]);
        send_mail($vals['email'], $status === 'registered' ? 'Event registration received' : 'You are on the event waiting list',
            "Hello {$vals['full_name']},\n\n" . ($status === 'registered' ? 'Your registration' : 'Your waiting-list request') . " for \"{$ev['title']}\" on " . fmt_datetime($ev['starts_at']) . " was received.\nReference: {$ref}\n\nEvent details: " . abs_url('event.php', ['slug' => $ev['slug']]), 'event_upcoming');
        audit_log('event_registration', 'events', (int) $ev['id'], 'New ' . $status . ' registration ' . $ref);
        $done = ['ref' => $ref, 'status' => $status];
    }
}
$p = [$ev['id']];
$updates = q_all("SELECT x.* FROM posts x WHERE x.event_id = ? AND " . pub_where('x', $p) . ' ORDER BY COALESCE(x.publish_at, x.published_at) DESC', $p);
$past = strtotime($ev['starts_at']) < time();
public_start($ev['title'], ['crumbs' => [['Events', url('events.php')], [excerpt($ev['title'], 50), null]], 'description' => $ev['summary'] ?: excerpt($ev['description'], 160),
    'image' => $ev['cover_media_id'] ? media_url((int) $ev['cover_media_id']) : null]);
?>
<div class="wrap section split">
    <article class="article">
        <?php if ($ev['cover_media_id']): ?><figure class="article-cover"><img src="<?= e(media_url((int) $ev['cover_media_id'])) ?>" alt=""></figure><?php endif; ?>
        <?php if ($ev['event_status'] !== 'scheduled'): ?><div class="notice notice-<?= $ev['event_status'] === 'cancelled' ? 'error' : 'warn' ?>" role="status">This event is <?= e($ev['event_status']) ?>.</div><?php endif; ?>
        <?php if ($ev['visibility'] === 'private'): ?><div class="notice notice-info" role="status">Private organizational event. Do not share this page outside the organization.</div><?php endif; ?>
        <?php if ($ev['summary']): ?><p class="lead"><?= e($ev['summary']) ?></p><?php endif; ?>
        <div class="prose"><?= $ev['description'] ?></div>
        <?php if ($updates): ?><h2 class="h3 mt">Announcements and updates</h2><ul class="foot-list related-list"><?php foreach ($updates as $up): ?><li><a href="<?= e(url('post.php', ['slug' => $up['slug']])) ?>"><?= e($up['title']) ?></a> <span class="muted small"><?= e(fmt_date($up['publish_at'] ?: $up['published_at'])) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
    </article>
    <aside>
        <div class="box aside-box">
            <h2 class="h3">Details</h2>
            <dl class="dl-pub">
                <dt>Starts</dt><dd><?= e(fmt_datetime($ev['starts_at'])) ?></dd>
                <?php if ($ev['ends_at']): ?><dt>Ends</dt><dd><?= e(fmt_datetime($ev['ends_at'])) ?></dd><?php endif; ?>
                <?php if ($ev['location_name']): ?><dt>Venue</dt><dd><?= e($ev['location_name']) ?><?= $ev['venue_address'] ? '<br>' . e($ev['venue_address']) : '' ?></dd><?php endif; ?>
                <?php if ($ev['region_name']): ?><dt>Region</dt><dd><?= e($ev['region_name']) ?></dd><?php endif; ?>
                <?php if ($ev['organizer']): ?><dt>Organizer</dt><dd><?= e($ev['organizer']) ?></dd><?php endif; ?>
                <?php if ($ev['contact_email']): ?><dt>Contact</dt><dd><a href="mailto:<?= e($ev['contact_email']) ?>"><?= e($ev['contact_email']) ?></a></dd><?php endif; ?>
                <?php if ($ev['category_name']): ?><dt>Category</dt><dd><?= e($ev['category_name']) ?></dd><?php endif; ?>
            </dl>
            <?php if (!$past): ?><p class="mt"><a class="btn btn-secondary btn-sm" href="<?= e(url('event.php', ['slug' => $ev['slug'], 'ics' => 1])) ?>">Add to my calendar</a></p><?php endif; ?>
        </div>
        <?php if ($ev['registration_enabled']): ?>
        <div class="box box-cta aside-box mt" id="register">
            <h2 class="h3">Registration</h2>
            <?php if ($done): ?>
                <div class="result-box" role="status"><p><strong><?= $done['status'] === 'registered' ? 'You are registered.' : 'The event is full, so you are on the waiting list.' ?></strong></p><p>Reference number</p><p class="code"><?= e($done['ref']) ?></p><p class="small">A confirmation was sent to your email address.</p></div>
            <?php elseif (!$open): ?>
                <p class="muted"><?= $past ? 'Registration has closed because the event has started.' : 'Registration is closed.' ?></p>
            <?php else: ?>
                <?php if ($ev['registration_limit']): ?><p class="small"><?= $full ? '<strong>The event is full.</strong> New registrations go to the waiting list.' : fmt_num(max(0, (int) $ev['registration_limit'] - $taken)) . ' of ' . fmt_num($ev['registration_limit']) . ' places left.' ?></p><?php endif; ?>
                <?php if ($ev['registration_deadline']): ?><p class="small muted">Deadline: <?= e(fmt_datetime($ev['registration_deadline'])) ?></p><?php endif; ?>
                <?= form_error_summary($errors) ?>
                <form method="post" action="#register" novalidate><?= csrf_field() ?><?= honeypot_field() ?>
                    <div class="grid">
                        <?= form_field('full_name', ['label' => 'Full name', 'required' => true, 'max' => 150, 'width' => 'full', 'autocomplete' => 'name'], $vals['full_name'], $errors) ?>
                        <?= form_field('email', ['label' => 'Email', 'type' => 'email', 'required' => true, 'width' => 'full', 'autocomplete' => 'email'], $vals['email'], $errors) ?>
                        <?= form_field('phone', ['label' => 'Mobile number (optional)', 'type' => 'tel', 'max' => 40, 'width' => 'full', 'autocomplete' => 'tel'], $vals['phone'], $errors) ?>
                        <?= form_field('organization', ['label' => 'Cooperative or organization (optional)', 'max' => 191, 'width' => 'full'], $vals['organization'], $errors) ?>
                        <div class="field field-full<?= isset($errors['consent']) ? ' has-error' : '' ?>"><label class="check" for="f_consent"><input type="checkbox" id="f_consent" name="consent" value="1"<?= post('consent') === '1' ? ' checked' : '' ?>> <span>I agree that my details are processed to manage this event, as described in the <a href="<?= e(url('privacy.php')) ?>">Privacy Notice</a>.</span></label><?= isset($errors['consent']) ? '<p class="err">' . e($errors['consent']) . '</p>' : '' ?></div>
                    </div>
                    <button class="btn btn-primary" type="submit"><?= $full ? 'Join the waiting list' : 'Register' ?></button>
                </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </aside>
</div>
<?php public_end();
