<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

render_post_listing(['achievement'], 'Achievements and accomplishments', 'Accomplishments of COOP NATCCO Partylist and the cooperative sector.', UPDATE_TABS);
