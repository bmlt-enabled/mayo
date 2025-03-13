<?php

/**
 * Plugin Name: Mayo Events Manager
 * Description: A plugin for managing events with admin approval,
 * public submission, and recurring schedules.
 * Version: 1.0.10
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

if (! defined('ABSPATH') ) { 
    exit; // Exit if accessed directly
}

define('MAYO_VERSION', '1.0.10');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/Admin.php';
require_once __DIR__ . '/includes/Frontend.php';
require_once __DIR__ . '/includes/Rest.php';
require_once __DIR__ . '/includes/CalendarRSS.php';

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

register_activation_hook(__FILE__, 'bmltenabled_mayo_activate');
register_deactivation_hook(__FILE__, 'bmltenabled_mayo_deactivate');
add_action('plugins_loaded', 'bmltenabled_mayo_checkUpgrade');
add_filter('archive_template', 'bmltenabled_mayo_loadArchiveTemplate');
add_filter('single_template', 'bmltenabled_mayo_loadDetailsTemplate');

/**
 * Activate the plugin and flush rewrite rules.
 *
 * @return void
 */
function bmltenabled_mayo_activate()
{
    // If init hasn't fired, add an action to register post type
    if (!did_action('init')) {
        add_action('init', ['BmltEnabled\Mayo\Admin', 'register_post_type']);
    } else {
        // If init has fired, register post type immediately
        Admin::register_post_type();
    }

    // Flush rewrite rules after post type is registered
    flush_rewrite_rules();
}

/**
 * Deactivate the plugin.
 *
 * @return void
 */
function bmltenabled_mayo_deactivate()
{
    // Unregister the post type
    unregister_post_type('mayo_event');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Check the plugin version and update if necessary.
 *
 * @return void
 */
function bmltenabled_mayo_checkUpgrade()
{
    $current_version = get_option('mayo_version');
    if (empty($current_version) 
        || version_compare($current_version, MAYO_VERSION, '<')
    ) {
        bmltenabled_mayo_activate();
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
function bmltenabled_mayo_loadArchiveTemplate($template)
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
function bmltenabled_mayo_loadDetailsTemplate($template)
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
function bmltenabled_mayo_enqueueAdminScripts()
{
    wp_enqueue_script(
        'admin-bundle',
        plugin_dir_url(__FILE__) . 'assets/js/dist/admin.bundle.js',
        ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data'],
        '1.0',
        true
    );
}
add_action('enqueue_block_editor_assets', 'bmltenabled_mayo_enqueueAdminScripts');
