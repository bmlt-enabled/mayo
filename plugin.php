<?php
/**
 * Plugin Name: Mayo
 * Description: A plugin for managing events with admin approval, public submission, and recurring schedules.
 * Version: 1.0
 * Author: bmlt-enabled
 * Author URI: https://bmlt.app
 */

defined('ABSPATH') || exit;

define('MAYO_VERSION', '1.0');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/Admin.php';
require_once __DIR__ . '/includes/Public.php';
require_once __DIR__ . '/includes/MigrationManager.php';
require_once __DIR__ . '/includes/REST.php';

use BmltEnabled\Mayo\Admin;
use BmltEnabled\Mayo\PublicInterface;
use BmltEnabled\Mayo\REST;
use BmltEnabled\Mayo\MigrationManager;

// Initialize components
add_action('plugins_loaded', function () {
    Admin::init();
    PublicInterface::init();
    REST::init();
    MigrationManager::getInstance()->runMigrations();
});

register_activation_hook(__FILE__, 'mayo_activate');
add_action('plugins_loaded', 'mayo_check_version');

function mayo_activate() {    
    // Flush rewrite rules
    flush_rewrite_rules();
}

function mayo_check_version() {
    $current_version = get_option('mayo_version');
    if (version_compare($current_version, MAYO_VERSION, '<')) {
        mayo_activate();
        update_option('mayo_version', MAYO_VERSION);
    }
}
