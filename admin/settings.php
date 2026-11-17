<?php
/**
 * Administrative settings. Critical groups need the settings.security permission
 * and password re-entry, and every change is audited.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/forms.php';

$me = require_perm('settings.view');
$groups = [
    'identity' => ['Website identity', false, [
        'site_name' => ['label' => 'Organization name', 'required' => true, 'max' => 120],
        'site_tagline' => ['label' => 'Tagline', 'max' => 160],
        'site_description' => ['label' => 'Short description for search engines', 'type' => 'textarea', 'max' => 300],
        'logo_media_id' => ['label' => 'Official logo', 'type' => 'media', 'kind' => 'image', 'visibility' => 'public', 'help' => 'Use only the officially approved logo. Leave empty to use the built-in logo.'],
        'demo_notice' => ['label' => 'Site-wide notice banner', 'max' => 255, 'help' => 'Optional. Shown at the top of every public page, for example during testing.'],
    ]],
    'contact' => ['Contact information', false, [
        'contact_address' => ['label' => 'Office address', 'type' => 'textarea', 'max' => 400, 'rows' => 3],
        'contact_phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
        'contact_email' => ['label' => 'Public email', 'type' => 'email'],
        'office_hours' => ['label' => 'Office hours', 'max' => 120],
        'map_url' => ['label' => 'Map embed link', 'type' => 'url', 'help' => 'A Google Maps "Embed a map" link (https://www.google.com/maps/embed?...).'],
    ]],
    'social' => ['Social media', false, [
        'social_facebook' => ['label' => 'Facebook page', 'type' => 'url'],
        'social_youtube' => ['label' => 'YouTube channel', 'type' => 'url'],
        'social_x' => ['label' => 'X (Twitter)', 'type' => 'url'],
        'social_instagram' => ['label' => 'Instagram', 'type' => 'url'],
        'social_tiktok' => ['label' => 'TikTok', 'type' => 'url'],
    ]],
    'homepage' => ['Home page', false, [
        'home_intro' => ['label' => 'Organizational introduction', 'type' => 'textarea', 'max' => 1200, 'rows' => 4],
        'home_mission' => ['label' => 'Mission', 'type' => 'textarea', 'max' => 1200, 'rows' => 3],
        'home_vision' => ['label' => 'Vision', 'type' => 'textarea', 'max' => 1200, 'rows' => 3],
        'home_objectives' => ['label' => 'Organizational objectives (one per line)', 'type' => 'textarea', 'max' => 3000, 'rows' => 5],
        'home_news_count' => ['label' => 'Number of news items', 'type' => 'number', 'min' => 2, 'max_value' => 12],
        'home_show_achievements' => ['label' => 'Show featured achievements', 'type' => 'bool'],
        'home_show_legislation' => ['label' => 'Show recent legislative updates', 'type' => 'bool'],
        'home_show_events' => ['label' => 'Show upcoming events', 'type' => 'bool'],
        'home_show_activities' => ['label' => 'Show featured cooperative activities', 'type' => 'bool'],
        'home_show_publications' => ['label' => 'Show official publications', 'type' => 'bool'],
    ]],
    'footer' => ['Footer', false, [
        'footer_about' => ['label' => 'Footer description', 'type' => 'textarea', 'max' => 400, 'rows' => 3],
        'footer_note' => ['label' => 'Copyright note', 'max' => 200],
    ]],
    'notifications' => ['Email delivery', true, [
        'mail_enabled' => ['label' => 'Send transactional email', 'type' => 'bool'],
        'mail_driver' => ['label' => 'Delivery method', 'type' => 'select', 'options' => ['log' => 'Write to the mail log (development)', 'smtp' => 'SMTP server', 'mail' => 'PHP mail()'], 'placeholder' => null],
        'mail_from_email' => ['label' => 'From address', 'type' => 'email', 'required' => true],
        'mail_from_name' => ['label' => 'From name', 'max' => 120],
        'smtp_host' => ['label' => 'SMTP host', 'max' => 191],
        'smtp_port' => ['label' => 'SMTP port', 'type' => 'number', 'min' => 1, 'max_value' => 65535],
        'smtp_secure' => ['label' => 'Encryption', 'type' => 'select', 'options' => ['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS'], 'placeholder' => null],
        'smtp_username' => ['label' => 'SMTP username', 'max' => 191],
        'smtp_password_enc' => ['label' => 'SMTP password', 'type' => 'password', 'help' => 'Stored encrypted. Leave blank to keep the current password.'],
    ]],
    'security' => ['Session and account security', true, [
        'password_min_length' => ['label' => 'Minimum password length', 'type' => 'number', 'min' => 10, 'max_value' => 64],
        'lockout_threshold' => ['label' => 'Failed sign-ins before lockout', 'type' => 'number', 'min' => 3, 'max_value' => 20],
        'lockout_minutes' => ['label' => 'Lockout duration (minutes)', 'type' => 'number', 'min' => 1, 'max_value' => 1440],
        'session_idle_minutes' => ['label' => 'Sign out after inactivity (minutes)', 'type' => 'number', 'min' => 5, 'max_value' => 240],
        'session_absolute_hours' => ['label' => 'Maximum session length (hours)', 'type' => 'number', 'min' => 1, 'max_value' => 72],
        'mfa_required_privileged' => ['label' => 'Require multifactor authentication for privileged roles', 'type' => 'bool'],
        'reset_token_minutes' => ['label' => 'Password reset link validity (minutes)', 'type' => 'number', 'min' => 10, 'max_value' => 1440],
        'upload_max_mb' => ['label' => 'Maximum upload size (MB)', 'type' => 'number', 'min' => 1, 'max_value' => 50],
    ]],
    'backup' => ['Backups', true, [
        'backup_retention_days' => ['label' => 'Keep backups for (days)', 'type' => 'number', 'min' => 7, 'max_value' => 3650],
        'backup_min_keep' => ['label' => 'Always keep at least this many backups', 'type' => 'number', 'min' => 1, 'max_value' => 365],
    ]],
    'audit' => ['Audit log retention', true, [
        'audit_retention_days' => ['label' => 'Audit log retention (days)', 'type' => 'number', 'min' => 365, 'max_value' => 7300, 'help' => 'Older entries may be archived by the database administrator following docs/SECURITY-PRIVACY.md. The application itself never deletes audit entries.'],
    ]],
    'privacy' => ['Privacy and retention', true, [
        'dpo_name' => ['label' => 'Data Protection Officer', 'max' => 150],
        'dpo_email' => ['label' => 'Data Protection Officer email', 'type' => 'email'],
        'member_retention_years' => ['label' => 'Keep member records after membership ends (years)', 'type' => 'number', 'min' => 1, 'max_value' => 30],
        'helpdesk_retention_years' => ['label' => 'Keep closed help desk tickets (years)', 'type' => 'number', 'min' => 1, 'max_value' => 30],
        'registration_retention_months' => ['label' => 'Keep event registrations after the event (months)', 'type' => 'number', 'min' => 1, 'max_value' => 120],
        'privacy_request_days' => ['label' => 'Target response time for privacy requests (days)', 'type' => 'number', 'min' => 5, 'max_value' => 90],
    ]],
];
$tab = isset($groups[get('tab')]) ? get('tab') : 'identity';
[$title, $critical, $fields] = $groups[$tab];
$canEdit = $critical ? can('settings.security') : can('settings.manage');
$errors = [];

if (is_post()) {
    if (!$canEdit) {
        deny('Edit settings group ' . $tab);
    }
    if ($critical) {
        $hash = (string) q_val('SELECT password_hash FROM users WHERE id = ?', [$me['id']]);
        if (!password_verify((string) ($_POST['confirm_password'] ?? ''), $hash)) {
            $errors['confirm_password'] = 'Enter your password to confirm changes to critical settings.';
            audit_log('settings_denied', 'settings', null, "Password confirmation failed for {$tab} settings", 'failure');
        }
    }
    $new = [];
    foreach ($fields as $k => $f) {
        $type = $f['type'] ?? 'text';
        $raw = $_POST[$k] ?? '';
        $raw = is_string($raw) ? trim($raw) : '';
        $label = $f['label'];
        if ($type === 'bool') {
            $new[$k] = $raw === '1' ? '1' : '0';
            continue;
        }
        if ($type === 'media') {
            if (upload_present($k . '__file')) {
                try {
                    $new[$k] = (string) media_upload($_FILES[$k . '__file'], 'image', 'public', 'Official logo');
                } catch (ValidationError $e) {
                    $errors[$k] = $e->getMessage();
                }
            } elseif (!empty($_POST[$k . '__remove'])) {
                $new[$k] = '';
            }
            continue;
        }
        if ($type === 'password') {
            if ($raw !== '') {
                $new[$k] = encrypt_value($raw);
            }
            continue;
        }
        if ($raw === '' && !empty($f['required'])) {
            $errors[$k] = $label . ' is required.';
        } elseif ($raw !== '') {
            if ($type === 'email' && !filter_var($raw, FILTER_VALIDATE_EMAIL)) {
                $errors[$k] = $label . ' must be a valid email address.';
            } elseif ($type === 'url' && (!preg_match('#^https://#i', $raw) || !filter_var($raw, FILTER_VALIDATE_URL))) {
                $errors[$k] = $label . ' must be a full https:// address.';
            } elseif ($type === 'number' && (!ctype_digit($raw) || (int) $raw < ($f['min'] ?? 0) || (int) $raw > ($f['max_value'] ?? PHP_INT_MAX))) {
                $errors[$k] = $label . ' must be a whole number between ' . ($f['min'] ?? 0) . ' and ' . ($f['max_value'] ?? 'any') . '.';
            } elseif ($type === 'select' && !isset($f['options'][$raw])) {
                $errors[$k] = $label . ' has an invalid value.';
            } elseif (mb_strlen($raw) > ($f['max'] ?? 500)) {
                $errors[$k] = $label . ' is too long.';
            }
        }
        if ($k === 'map_url' && $raw !== '' && strpos($raw, 'https://www.google.com/maps/embed') !== 0) {
            $errors[$k] = 'Use the Google Maps embed link that starts with https://www.google.com/maps/embed.';
        }
        $new[$k] = $raw;
    }
    if (!$errors) {
        $all = settings_all();
        $changes = [];
        foreach ($new as $k => $v) {
            if ((string) ($all[$k] ?? '') !== (string) $v) {
                $changes[$k] = ['from' => $k === 'smtp_password_enc' ? '[protected]' : ($all[$k] ?? ''), 'to' => $k === 'smtp_password_enc' ? '[protected]' : $v];
                setting_set($k, $v, (int) $me['id']);
                if ($critical) {
                    q('UPDATE website_settings SET is_critical = 1, setting_group = ? WHERE setting_key = ?', [$tab, $k]);
                }
            }
        }
        if ($changes) {
            audit_log('settings_changed', 'settings', null, ucfirst($title) . ' settings changed: ' . implode(', ', array_keys($changes)), 'success', $changes);
            if ($critical) {
                security_event('critical_settings_changed', 'medium', ucfirst($title) . ' settings changed by ' . $me['username'] . ': ' . implode(', ', array_keys($changes)));
            }
        }
        if (post('send_test') === '1' && $tab === 'notifications') {
            $ok = send_mail($me['email'], 'Test email from the COOP NATCCO Partylist system', "This is a test message. If you received it, email delivery works.", 'security');
            flash($ok ? 'success' : 'error', $ok ? 'Settings saved. A test email was sent to ' . mask_email($me['email']) . ' (or written to the mail log).' : 'Settings saved, but the test email failed. Check the email log in Security monitoring.');
        } else {
            flash('success', $changes ? 'Settings saved.' : 'No changes to save.');
        }
        redirect(url('admin/settings.php', ['tab' => $tab]));
    }
}

admin_start('Settings', [['Settings', null]]);
page_bar('Settings', 'Changes are recorded in the audit log.' . ($critical ? ' This group is critical: you need security permission and your password.' : ''));
echo flash_render();
echo '<nav class="tabs-inline" aria-label="Settings groups">';
foreach ($groups as $k => $g) {
    echo '<a href="' . e(url('admin/settings.php', ['tab' => $k])) . '"' . ($k === $tab ? ' aria-current="true"' : '') . '>' . e($g[0]) . '</a>';
}
echo '</nav>';
echo form_error_summary($errors);
$values = settings_all();
echo '<form method="post" enctype="multipart/form-data" class="record-form" novalidate>' . csrf_field();
echo '<fieldset class="panel"' . ($canEdit ? '' : ' disabled') . '><legend class="panel-title">' . e($title) . '</legend><div class="grid">';
foreach ($fields as $k => $f) {
    $v = is_post() ? ($_POST[$k] ?? ($values[$k] ?? '')) : ($values[$k] ?? '');
    if (($f['type'] ?? '') === 'password') {
        $v = '';
        $f['help'] = ($values[$k] ?? '') !== '' ? 'A password is saved. ' . $f['help'] : 'No password saved yet.';
    }
    if (($f['type'] ?? '') === 'media') {
        $v = ($values[$k] ?? '') !== '' ? (int) $values[$k] : null;
    }
    echo form_field($k, $f, $v, $errors);
}
if ($tab === 'notifications') {
    echo form_field('send_test', ['label' => 'Send a test email to me after saving', 'type' => 'bool'], '0');
}
if ($critical) {
    echo '<div class="field field-full' . (isset($errors['confirm_password']) ? ' has-error' : '') . '"><label for="f_confirm_password">Your password</label><input type="password" id="f_confirm_password" name="confirm_password" autocomplete="current-password" required>'
        . (isset($errors['confirm_password']) ? '<p class="err">' . e($errors['confirm_password']) . '</p>' : '<p class="help">Required to change critical settings.</p>') . '</div>';
}
echo '</div></fieldset>';
if ($canEdit) {
    echo '<div class="form-actions"><button class="btn btn-primary" type="submit">Save settings</button></div>';
} else {
    echo '<p class="muted">You can view these settings but not change them.</p>';
}
echo '</form>';
echo '<section class="panel panel-flat"><h2 class="panel-title">Related settings</h2><div class="btn-row">'
    . (can('menus.manage') ? '<a class="btn btn-secondary btn-sm" href="' . e(url('admin/menus.php')) . '">Menus</a>' : '')
    . (can('settings.manage') ? '<a class="btn btn-secondary btn-sm" href="' . e(url('admin/lookups.php')) . '">Categories and reference lists</a>' : '')
    . (can('roles.manage') ? '<a class="btn btn-secondary btn-sm" href="' . e(url('admin/roles.php')) . '">Roles and permissions</a>' : '')
    . (can('users.view') ? '<a class="btn btn-secondary btn-sm" href="' . e(url('admin/users.php')) . '">User accounts</a>' : '')
    . (can('structure.manage') ? '<a class="btn btn-secondary btn-sm" href="' . e(url('admin/org-units.php')) . '">Organizational structure</a>' : '')
    . '</div></section>';
admin_end();
