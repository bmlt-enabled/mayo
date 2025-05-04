<?php

// Load WordPress testing environment
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

// Load the plugin
tests_add_filter('muplugins_loaded', function() {
    require dirname(dirname(__FILE__)) . '/mayo-events-manager.php';
});

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php'; 