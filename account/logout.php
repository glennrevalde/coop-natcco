<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

if (!is_post()) {
    // Sign-out must be a POST with a CSRF token to prevent forced logouts.
    redirect(current_user() ? 'admin/index.php' : 'account/login.php');
}
auth_logout();
flash('success', 'You have signed out.');
redirect('account/login.php');
