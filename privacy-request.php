<?php
/**
 * Data subject requests (access, correction, deletion, objection) under RA 10173.
 */
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

$types = ['access' => 'Access to my personal data', 'correction' => 'Correction of my personal data', 'deletion' => 'Deletion or blocking of my personal data', 'objection' => 'Objection to processing', 'portability' => 'A copy of my data in a usable format', 'other' => 'Other privacy concern'];
$vals = ['requester_name' => '', 'email' => '', 'request_type' => '', 'details' => '', 'member_ref' => ''];
$errors = [];
$ref = null;
if (is_post()) {
    public_form_limit('privacy_request', 4, 3600);
    foreach ($vals as $k => $v) {
        $vals[$k] = post($k);
    }
    if (honeypot_tripped()) {
        $errors['requester_name'] = 'Your request could not be accepted. Please try again.';
    }
    if ($vals['requester_name'] === '' || mb_strlen($vals['requester_name']) > 150) {
        $errors['requester_name'] = 'Enter your full name.';
    }
    if (!filter_var($vals['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (!isset($types[$vals['request_type']])) {
        $errors['request_type'] = 'Choose the type of request.';
    }
    if (mb_strlen($vals['details']) < 10 || mb_strlen($vals['details']) > 5000) {
        $errors['details'] = 'Describe your request in at least 10 characters.';
    }
    if (post('confirm') !== '1') {
        $errors['confirm'] = 'Confirm that the request concerns your own personal data or that you are authorized to make it.';
    }
    if (!$errors) {
        $ref = 'PR-' . date('Y') . '-' . random_code(6);
        $memberId = $vals['member_ref'] !== '' ? q_val('SELECT id FROM members WHERE member_ref = ?', [mb_strtoupper(trim($vals['member_ref']))]) : null;
        $id = q_insert('privacy_requests', ['reference_code' => $ref, 'requester_name' => $vals['requester_name'], 'requester_email_enc' => encrypt_value($vals['email']), 'request_type' => $vals['request_type'],
            'details' => $vals['details'] . ($vals['member_ref'] !== '' && !$memberId ? "\n\n[Member reference given: " . mb_substr($vals['member_ref'], 0, 30) . ']' : ''), 'member_id' => $memberId ?: null,
            'due_date' => date('Y-m-d', strtotime('+' . setting_int('privacy_request_days', 30) . ' days')), 'ip_address' => client_ip(), 'created_at' => now()]);
        audit_log('privacy_request', 'privacy_requests', $id, 'New privacy request ' . $ref);
        notify_perm('privacy.manage', 'approval_pending', 'A new data privacy request was received', '', url('admin/privacy-requests.php', ['action' => 'view', 'id' => $id]));
        send_mail($vals['email'], 'We received your privacy request', "Hello {$vals['requester_name']},\n\nYour privacy request was received. Reference: {$ref}.\nWe may contact you to verify your identity before acting on it.", 'security');
    }
}
public_start('Data privacy request', ['crumbs' => [['Privacy Notice', url('privacy.php')], ['Data privacy request', null]], 'sub' => 'Exercise your rights under the Data Privacy Act of 2012.']);
?>
<div class="wrap section split"><div>
<?php if ($ref): ?>
    <div class="result-box" role="status" tabindex="-1" data-focus><h2>Your request was received</h2><p>Reference</p><p class="code"><?= e($ref) ?></p><p>We aim to respond within <?= setting_int('privacy_request_days', 30) ?> days and may ask you to verify your identity first.</p></div>
<?php else: ?>
    <?= form_error_summary($errors) ?>
    <form method="post" class="form-card" novalidate><?= csrf_field() ?><?= honeypot_field() ?>
        <div class="grid">
            <?= form_field('requester_name', ['label' => 'Full name', 'required' => true, 'max' => 150, 'autocomplete' => 'name'], $vals['requester_name'], $errors) ?>
            <?= form_field('email', ['label' => 'Email', 'type' => 'email', 'required' => true, 'autocomplete' => 'email'], $vals['email'], $errors) ?>
            <?= form_field('request_type', ['label' => 'What would you like to do?', 'type' => 'select', 'options' => $types, 'required' => true, 'width' => 'full'], $vals['request_type'], $errors) ?>
            <?= form_field('member_ref', ['label' => 'Member reference, if you have one (optional)', 'max' => 30, 'width' => 'full'], $vals['member_ref'], $errors) ?>
            <?= form_field('details', ['label' => 'Details', 'type' => 'textarea', 'required' => true, 'max' => 5000, 'rows' => 6, 'help' => 'Tell us which records your request is about. Do not include ID numbers or bank details.'], $vals['details'], $errors) ?>
            <div class="field field-full<?= isset($errors['confirm']) ? ' has-error' : '' ?>"><label class="check" for="f_confirm"><input type="checkbox" id="f_confirm" name="confirm" value="1"<?= post('confirm') === '1' ? ' checked' : '' ?>> <span>This request concerns my own personal data, or I am authorized to make it on behalf of the person concerned.</span></label><?= isset($errors['confirm']) ? '<p class="err">' . e($errors['confirm']) . '</p>' : '' ?></div>
        </div>
        <button class="btn btn-primary" type="submit">Submit request</button>
    </form>
<?php endif; ?>
</div><aside><div class="box"><h2 class="h3">Your rights</h2><p>You may ask for access to, correction of, or deletion of your personal information, object to its processing, and file a complaint with the National Privacy Commission.</p><p><a href="<?= e(url('privacy.php')) ?>">Read the Privacy Notice</a></p></div></aside></div>
<?php public_end();
