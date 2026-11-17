<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

render_post_listing(['announcement'], 'Official announcements', 'Official announcements and notices.', UPDATE_TABS);
