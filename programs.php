<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

render_post_listing(['program', 'activity'], 'Programs and activities', 'Programs, services and cooperative activities of COOP NATCCO Partylist.', ['cooperatives.php' => 'Cooperative directory', 'programs.php' => 'Programs and activities']);
