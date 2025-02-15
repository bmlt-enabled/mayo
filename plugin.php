<?php
/**
 * Plugin Name: Mayo Events Manager
 * Description: A plugin for managing events with admin approval, 
 * public submission, and recurring schedules.
 * Version: 1.0.7
 * Author: bmlt-enabled
 * License: MIT
 * Author URI: https://bmlt.app
 * php version 8.1
 *
 * @category WordPress_Plugin
 * @package  MayoEventsManager
 * @author   bmlt-enabled <help@bmlt.app>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://bmlt.app
 */

defined('ABSPATH') || exit;

define('MAYO_VERSION', '1.0.7');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/Admin.php';
require_once __DIR__ . '/includes/Frontend.php';
require_once __DIR__ . '/includes/Rest.php';

use BmltEnabled\Mayo\Admin;
use BmltEnabled\Mayo\Frontend;
use BmltEnabled\Mayo\Rest;

// Initialize components
add_action(
    'plugins_loaded',
    function () {
        Admin::init();
        Frontend::init();
        Rest::init();
    }
);

register_activation_hook(__FILE__, 'mayoActivate');
add_action('plugins_loaded', 'mayoCheckVersion');
add_filter('archive_template', 'loadArchiveTemplate');
add_filter('single_template', 'loadDetailsTemplate');

/**
 * Activate the plugin and flush rewrite rules.
 *
 * @return void
 */
function mayoActivate()
{
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Check the plugin version and update if necessary.
 *
 * @return void
 */
function mayoCheckVersion()
{
    $current_version = get_option('mayo_version');
    if (version_compare($current_version, MAYO_VERSION, '<')) {
        mayoActivate();
        update_option('mayo_version', MAYO_VERSION);
    }
}

/**
 * Load the archive template for mayo_event post type.
 *
 * @param string $template The path to the template.
 *
 * @return string The path to the custom template if it exists, 
 * otherwise the original template.
 */
function loadArchiveTemplate($template)
{
    if (is_post_type_archive('mayo_event')) {
        $custom_template = plugin_dir_path(__FILE__) . 
        'templates/archive-mayo-event.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }
    return $template;
}

/**
 * Load the single template for mayo_event post type.
 *
 * @param string $template The path to the template.
 *
 * @return string The path to the custom template if it exists, 
 * otherwise the original template.
 */
function loadDetailsTemplate($template)
{
    if (is_singular('mayo_event')) {
        $custom_template = plugin_dir_path(__FILE__) . 
        'templates/details-mayo-event.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }
    return $template;
}

/**
 * Enqueue admin scripts for the block editor.
 *
 * @return void
 */
function enqueueAdminScripts()
{
    wp_enqueue_script(
        'admin-bundle',
        plugin_dir_url(__FILE__) . 'assets/js/dist/admin.bundle.js',
        ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data'],
        '1.0',
        true
    );
}
add_action('enqueue_block_editor_assets', 'enqueueAdminScripts');

