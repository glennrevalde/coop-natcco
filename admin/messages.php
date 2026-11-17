<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/modules.php';

crud_run(module_def('messages'));
