<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

render_post_listing(['newsletter'], 'Newsletters', 'Official newsletters for download.', UPDATE_TABS);
