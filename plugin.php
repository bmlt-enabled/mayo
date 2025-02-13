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
require_once __DIR__ . '/includes/REST.php';

use BmltEnabled\Mayo\Admin;
use BmltEnabled\Mayo\PublicInterface;
use BmltEnabled\Mayo\REST;

// Initialize components
add_action('plugins_loaded', function () {
    Admin::init();
    PublicInterface::init();
    REST::init();
});

register_activation_hook(__FILE__, 'mayo_activate');
add_action('plugins_loaded', 'mayo_check_version');
add_filter('archive_template', 'load_archive_template');
add_filter('single_template', 'load_single_template');

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

function load_archive_template($template) {
    if (is_post_type_archive('mayo_event')) {
        $custom_template = plugin_dir_path(__FILE__) . 'templates/archive-mayo_event.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }
    return $template;
}

function load_single_template($template) {
    if (is_singular('mayo_event')) {
        $custom_template = plugin_dir_path(__FILE__) . 'templates/single-mayo_event.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }
    return $template;
}
