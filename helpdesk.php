<?php
/**
 * Help desk for primary cooperative concerns (public and signed-in submission).
 */
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';
require APP_ROOT . '/includes/helpdesk.php';

$u = current_user();
$cats = q_pairs('SELECT id, name FROM helpdesk_categories WHERE is_active = 1 ORDER BY sort_order, name');
$vals = ['requester_name' => $u['full_name'] ?? '', 'email' => $u['email'] ?? '', 'phone' => $u['phone'] ?? '', 'cooperative_name' => '', 'region_id' => '', 'category_id' => '', 'priority' => 'normal', 'subject' => '', 'description' => ''];
$errors = [];
$result = null;
if (is_post()) {
    public_form_limit('helpdesk_submit', 6, 900);
    foreach ($vals as $k => $v) {
        $vals[$k] = post($k);
    }
    if (honeypot_tripped()) {
        $errors['requester_name'] = 'Your submission could not be accepted. Please try again.';
    }
    if ($vals['requester_name'] === '' || mb_strlen($vals['requester_name']) > 150) {
        $errors['requester_name'] = 'Enter your full name.';
    }
    if (!filter_var($vals['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address. We use it to confirm your ticket.';
    }
    if ($vals['phone'] !== '' && !preg_match('/^[0-9+()\-\s.]{5,40}$/', $vals['phone'])) {
        $errors['phone'] = 'Enter a valid phone number.';
    }
    if (mb_strlen($vals['cooperative_name']) > 191) {
        $errors['cooperative_name'] = 'The cooperative name is too long.';
    }
    if ($vals['region_id'] !== '' && !isset(regions_list()[(int) $vals['region_id']])) {
        $errors['region_id'] = 'Choose a region from the list.';
    }
    if (!isset($cats[(int) $vals['category_id']])) {
        $errors['category_id'] = 'Choose the category that best fits your concern.';
    }
    if (!in_array($vals['priority'], ['low', 'normal', 'high'], true)) {
        $errors['priority'] = 'Choose a priority.';
    }
    if ($vals['subject'] === '' || mb_strlen($vals['subject']) > 191) {
        $errors['subject'] = 'Enter a short subject (up to 191 characters).';
    }
    if (mb_strlen($vals['description']) < 20 || mb_strlen($vals['description']) > 8000) {
        $errors['description'] = 'Describe your concern in at least 20 characters.';
    }
    if (post('consent') !== '1') {
        $errors['consent'] = 'Please agree to the processing of your details for this concern.';
    }
    $info = null;
    if (!$errors && upload_present('attachment__file')) {
        try {
            $info = media_validate_upload($_FILES['attachment__file'], 'attachment');
        } catch (ValidationError $e) {
            $errors['attachment'] = $e->getMessage();
        }
    }
    if (!$errors) {
        $coopId = null;
        if (preg_match('/CNPL-COOP-\d{6}/', mb_strtoupper($vals['cooperative_name']), $m)) {
            $coopId = q_val('SELECT id FROM cooperatives WHERE reference_no = ?', [$m[0]]) ?: null;
        }
        $result = ticket_create([
            'category_id' => (int) $vals['category_id'], 'subject' => $vals['subject'], 'description' => $vals['description'], 'priority' => $vals['priority'],
            'is_confidential' => post('is_confidential') === '1' ? 1 : 0, 'requester_name' => $vals['requester_name'], 'requester_email_enc' => encrypt_value($vals['email']),
            'requester_email_bidx' => blind_index($vals['email']), 'requester_phone_enc' => encrypt_value($vals['phone'] ?: null), 'cooperative_id' => $coopId ? (int) $coopId : null,
            'cooperative_name' => $vals['cooperative_name'] ?: null, 'region_id' => $vals['region_id'] !== '' ? (int) $vals['region_id'] : null, 'submitted_by_user_id' => $u['id'] ?? null,
        ]);
        if ($info) {
            $mid = media_store($info, 'private', 'Ticket attachment');
            q_insert('ticket_attachments', ['ticket_id' => $result['id'], 'media_id' => $mid, 'is_internal' => 0, 'uploaded_by' => $u['id'] ?? null, 'created_at' => now()]);
        }
        $_SESSION['tracked_tickets'][] = (int) $result['id'];
    }
}
public_start('Help desk', ['crumbs' => [['Help desk', null]], 'sub' => 'For primary cooperatives and authorized users. Each concern gets a ticket number so you can follow its progress.']);
?>
<div class="wrap section split">
<div>
<?php if ($result): ?>
    <div class="result-box" role="status" tabindex="-1" data-focus>
        <h2>Your concern was received</h2>
        <p>Ticket number</p><p class="code"><?= e($result['ticket_no']) ?></p>
        <p>Access key</p><p class="code"><?= e($result['access_key']) ?></p>
        <p><strong>Write down or save the access key now.</strong> You need the ticket number, your email address and this key to check the status. For your security the key is not sent by email.</p>
        <p class="btn-row"><a class="btn btn-primary" href="<?= e(url('helpdesk-track.php', ['no' => $result['ticket_no']])) ?>">Track this ticket</a><a class="btn btn-secondary" href="<?= e(url('helpdesk.php')) ?>">Submit another concern</a></p>
    </div>
<?php else: ?>
    <?= form_error_summary($errors) ?>
    <form method="post" enctype="multipart/form-data" class="form-card" novalidate><?= csrf_field() ?><?= honeypot_field() ?>
        <h2>About you</h2>
        <div class="grid">
            <?= form_field('requester_name', ['label' => 'Full name', 'required' => true, 'max' => 150, 'autocomplete' => 'name'], $vals['requester_name'], $errors) ?>
            <?= form_field('email', ['label' => 'Email', 'type' => 'email', 'required' => true, 'autocomplete' => 'email'], $vals['email'], $errors) ?>
            <?= form_field('phone', ['label' => 'Mobile number (optional)', 'type' => 'tel', 'max' => 40, 'autocomplete' => 'tel'], $vals['phone'], $errors) ?>
            <?= form_field('region_id', ['label' => 'Region', 'type' => 'select', 'options' => regions_list(), 'placeholder' => '— Select region —'], $vals['region_id'], $errors) ?>
            <?= form_field('cooperative_name', ['label' => 'Primary cooperative', 'max' => 191, 'width' => 'full', 'help' => 'Name of your cooperative. Include its CNPL reference number if you know it.'], $vals['cooperative_name'], $errors) ?>
        </div>
        <h2>Your concern</h2>
        <div class="grid">
            <?= form_field('category_id', ['label' => 'Category', 'type' => 'select', 'options' => $cats, 'required' => true], $vals['category_id'], $errors) ?>
            <?= form_field('priority', ['label' => 'How urgent is it?', 'type' => 'select', 'options' => ['low' => 'Low, no deadline', 'normal' => 'Normal', 'high' => 'High, time-sensitive'], 'placeholder' => null], $vals['priority'], $errors) ?>
            <?= form_field('subject', ['label' => 'Subject', 'required' => true, 'max' => 191, 'width' => 'full'], $vals['subject'], $errors) ?>
            <?= form_field('description', ['label' => 'Details', 'type' => 'textarea', 'required' => true, 'max' => 8000, 'rows' => 7, 'help' => 'Describe the concern, what you need, and any deadlines. Do not include passwords or bank details.'], $vals['description'], $errors) ?>
            <?= form_field('attachment', ['label' => 'Supporting file (optional)', 'type' => 'media', 'kind' => 'attachment', 'width' => 'full'], null, $errors) ?>
            <div class="field field-full"><label class="check" for="f_conf"><input type="checkbox" id="f_conf" name="is_confidential" value="1"<?= post('is_confidential') === '1' ? ' checked' : '' ?>> <span>This concern is confidential. Only specifically authorized officers will see it.</span></label></div>
            <div class="field field-full<?= isset($errors['consent']) ? ' has-error' : '' ?>"><label class="check" for="f_consent"><input type="checkbox" id="f_consent" name="consent" value="1"<?= post('consent') === '1' ? ' checked' : '' ?>> <span>I agree that my details are used to respond to this concern, as described in the <a href="<?= e(url('privacy.php')) ?>">Privacy Notice</a>.</span></label><?= isset($errors['consent']) ? '<p class="err">' . e($errors['consent']) . '</p>' : '' ?></div>
        </div>
        <div class="btn-row"><button class="btn btn-primary" type="submit">Submit concern</button></div>
    </form>
<?php endif; ?>
</div>
<aside>
    <div class="box aside-box"><h2 class="h3">Already submitted?</h2><p>Check the status, read replies and send more information.</p><a class="btn btn-secondary" href="<?= e(url('helpdesk-track.php')) ?>">Track a ticket</a></div>
    <div class="box aside-box"><h2 class="h3">How it works</h2><ol class="objectives"><li>Submit your concern and save the ticket number and access key.</li><li>A help desk officer reviews and assigns it.</li><li>You receive an email whenever there is an update.</li><li>Read replies and respond on the tracking page.</li></ol></div>
    <div class="box aside-box"><h2 class="h3">Quick answers</h2><p>Many common questions are answered in the <a href="<?= e(url('faq.php')) ?>">frequently asked questions</a>.</p></div>
</aside>
</div>
<?php public_end();
