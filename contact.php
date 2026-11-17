<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

$vals = ['full_name' => current_user()['full_name'] ?? '', 'email' => current_user()['email'] ?? '', 'subject' => '', 'message' => ''];
$errors = [];
$ref = null;
if (is_post()) {
    public_form_limit('contact_form', 5, 900);
    foreach ($vals as $k => $v) {
        $vals[$k] = post($k);
    }
    if (honeypot_tripped()) {
        $errors['full_name'] = 'Your message could not be accepted. Please try again.';
    }
    if ($vals['full_name'] === '' || mb_strlen($vals['full_name']) > 150) {
        $errors['full_name'] = 'Enter your name.';
    }
    if (!filter_var($vals['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address so we can reply.';
    }
    if ($vals['subject'] === '' || mb_strlen($vals['subject']) > 191) {
        $errors['subject'] = 'Enter a subject.';
    }
    if (mb_strlen($vals['message']) < 10 || mb_strlen($vals['message']) > 5000) {
        $errors['message'] = 'Write a message of at least 10 characters.';
    }
    if (post('consent') !== '1') {
        $errors['consent'] = 'Please agree to the processing of your details so we can reply.';
    }
    if (!$errors) {
        $ref = 'CM' . random_code(8);
        $id = q_insert('contact_messages', ['reference_code' => $ref, 'full_name' => $vals['full_name'], 'email_enc' => encrypt_value($vals['email']), 'subject' => $vals['subject'],
            'message' => $vals['message'], 'ip_address' => client_ip(), 'created_at' => now()]);
        audit_log('contact_message', 'messages', $id, 'New contact message ' . $ref);
        notify_perm('messages.view', 'ticket_new', 'New message from the contact form', '', url('admin/messages.php', ['action' => 'view', 'id' => $id]));
    }
}
$map = setting('map_url');
public_start('Contact us', ['crumbs' => [['Contact us', null]], 'sub' => 'Reach the COOP NATCCO Partylist office. For cooperative concerns, the help desk lets you track your request.']);
?>
<div class="wrap section split">
<div>
<?php if ($ref): ?>
    <div class="result-box" role="status" tabindex="-1" data-focus><h2>Thank you, your message was sent</h2><p>Reference</p><p class="code"><?= e($ref) ?></p><p>We will reply to the email address you provided.</p></div>
<?php else: ?>
    <?= form_error_summary($errors) ?>
    <form method="post" class="form-card" novalidate><?= csrf_field() ?><?= honeypot_field() ?>
        <h2>Send us a message</h2>
        <div class="grid">
            <?= form_field('full_name', ['label' => 'Your name', 'required' => true, 'max' => 150, 'autocomplete' => 'name'], $vals['full_name'], $errors) ?>
            <?= form_field('email', ['label' => 'Email', 'type' => 'email', 'required' => true, 'autocomplete' => 'email'], $vals['email'], $errors) ?>
            <?= form_field('subject', ['label' => 'Subject', 'required' => true, 'max' => 191, 'width' => 'full'], $vals['subject'], $errors) ?>
            <?= form_field('message', ['label' => 'Message', 'type' => 'textarea', 'required' => true, 'max' => 5000, 'rows' => 6], $vals['message'], $errors) ?>
            <div class="field field-full<?= isset($errors['consent']) ? ' has-error' : '' ?>"><label class="check" for="f_consent"><input type="checkbox" id="f_consent" name="consent" value="1"<?= post('consent') === '1' ? ' checked' : '' ?>> <span>I agree that my details are used to reply to this message, as described in the <a href="<?= e(url('privacy.php')) ?>">Privacy Notice</a>.</span></label><?= isset($errors['consent']) ? '<p class="err">' . e($errors['consent']) . '</p>' : '' ?></div>
        </div>
        <button class="btn btn-primary" type="submit">Send message</button>
    </form>
<?php endif; ?>
</div>
<aside>
    <div class="box aside-box"><h2 class="h3">Office</h2>
        <ul class="contact-list">
        <?php if (setting('contact_address') !== ''): ?><li><?= icon('pin') ?><span><?= nl2br(e(setting('contact_address'))) ?></span></li><?php endif; ?>
        <?php if (setting('contact_phone') !== ''): ?><li><?= icon('phone') ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('contact_phone'))) ?>"><?= e(setting('contact_phone')) ?></a></li><?php endif; ?>
        <?php if (setting('contact_email') !== ''): ?><li><?= icon('mail') ?><a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a></li><?php endif; ?>
        <?php if (setting('office_hours') !== ''): ?><li><?= icon('clock') ?><span><?= e(setting('office_hours')) ?></span></li><?php endif; ?>
        <?php if (setting('contact_address') === '' && setting('contact_phone') === '' && setting('contact_email') === ''): ?><li><span class="muted">Office contact details will be posted soon. You can use the form in the meantime.</span></li><?php endif; ?>
        </ul>
    </div>
    <div class="box aside-box mt"><h2 class="h3">Cooperative concerns</h2><p>Use the help desk to get a ticket number and follow up.</p><a class="btn btn-secondary" href="<?= e(url('helpdesk.php')) ?>">Go to the help desk</a></div>
    <?php if ($map !== '' && strpos($map, 'https://www.google.com/maps/embed') === 0): ?><div class="mt"><iframe class="map-frame" src="<?= e($map) ?>" title="Map of the office location" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div><?php endif; ?>
</aside>
</div>
<?php public_end();
