<?php
/**
 * Plugin Name: Mayo
 * Description: A plugin for managing events with admin approval, public submission, and recurring schedules.
 * Version: 1.0.0
 * Author: bmlt-enabled
 * Author URI: https://bmlt.app
 */

defined('ABSPATH') || exit;

define('MAYO_VERSION', '1.0.0');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/Admin.php';
require_once __DIR__ . '/includes/Frontend.php';
require_once __DIR__ . '/includes/Rest.php';

use BmltEnabled\Mayo\Admin;
use BmltEnabled\Mayo\Frontend;
use BmltEnabled\Mayo\Rest;

// Initialize components
add_action('plugins_loaded', function () {
    Admin::init();
    Frontend::init();
    Rest::init();
});

register_activation_hook(__FILE__, 'mayo_activate');
add_action('plugins_loaded', 'mayo_check_version');
add_filter('archive_template', 'load_archive_template');
add_filter('single_template', 'load_details_template');

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
        $custom_template = plugin_dir_path(__FILE__) . 'templates/archive-mayo-event.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }
    return $template;
}

function load_details_template($template) {
    if (is_singular('mayo_event')) {
        $custom_template = plugin_dir_path(__FILE__) . 'templates/details-mayo-event.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }
    return $template;
}

function enqueue_admin_scripts() {
    wp_enqueue_script(
        'admin-bundle',
        plugin_dir_url(__FILE__) . 'assets/js/dist/admin.bundle.js',
        ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data'],
        '1.0',
        true
    );
}
add_action('enqueue_block_editor_assets', 'enqueue_admin_scripts');

