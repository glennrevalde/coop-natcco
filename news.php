<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

render_post_listing(['news'], 'News', 'News articles and stories about the work of COOP NATCCO Partylist.', UPDATE_TABS);
