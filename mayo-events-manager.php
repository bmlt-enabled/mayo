<?php

/**
 * Plugin Name: Mayo Events Manager
 * Description: A plugin for managing and displaying events.
 * Version: 1.8.0
 * Author: bmlt-enabled
 * License: GPLv2 or later
 * Author URI: https://bmlt.app
 * php version 8.2
 *
 * @category WordPress_Plugin
 * @package  MayoEventsManager
 * @author   bmlt-enabled <help@bmlt.app>
 * @license  http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
 * @link     https://bmlt.app
 */

if (! defined('ABSPATH') ) { 
    exit; // Exit if accessed directly
}

define('MAYO_VERSION', '1.8.0');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/Admin.php';
require_once __DIR__ . '/includes/Frontend.php';
require_once __DIR__ . '/includes/Rest.php';
require_once __DIR__ . '/includes/CalendarFeed.php';
require_once __DIR__ . '/includes/RssFeed.php';
require_once __DIR__ . '/includes/Announcement.php';
require_once __DIR__ . '/includes/Subscriber.php';
require_once __DIR__ . '/includes/Widgets/AnnouncementWidget.php';

use BmltEnabled\Mayo\Admin;
use BmltEnabled\Mayo\Subscriber;
use BmltEnabled\Mayo\Frontend;
use BmltEnabled\Mayo\Rest;
use BmltEnabled\Mayo\Announcement;
use BmltEnabled\Mayo\Widgets\AnnouncementWidget;

// Initialize components
add_action(
    'plugins_loaded',
    function () {
        Admin::init();
        Frontend::init();
        Rest::init();
        Announcement::init();
    }
);

// Register widgets
add_action(
    'widgets_init',
    function () {
        register_widget(AnnouncementWidget::class);
    }
);

register_activation_hook(__FILE__, 'Bmltenabled_Mayo_activate');
register_deactivation_hook(__FILE__, 'Bmltenabled_Mayo_deactivate');
add_action('plugins_loaded', 'Bmltenabled_Mayo_checkUpgrade');
add_filter('archive_template', 'Bmltenabled_Mayo_loadArchiveTemplate');
add_filter('single_template', 'Bmltenabled_Mayo_loadDetailsTemplate');

/**
 * Activate the plugin and flush rewrite rules.
 *
 * @return void
 */
function Bmltenabled_Mayo_activate()
{
    // If init hasn't fired, add an action to register post types
    if (!did_action('init')) {
        add_action('init', ['BmltEnabled\Mayo\Admin', 'register_post_type']);
        add_action('init', ['BmltEnabled\Mayo\Announcement', 'register_post_type']);
    } else {
        // If init has fired, register post types immediately
        Admin::register_post_type();
        Announcement::register_post_type();
    }

    // Create subscribers table
    Subscriber::create_table();

    // Flush rewrite rules after post types are registered
    flush_rewrite_rules();
}

/**
 * Deactivate the plugin.
 *
 * @return void
 */
function Bmltenabled_Mayo_deactivate()
{
    // Unregister post types
    unregister_post_type('mayo_event');
    unregister_post_type('mayo_announcement');

    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Check the plugin version and update if necessary.
 *
 * @return void
 */
function Bmltenabled_Mayo_checkUpgrade()
{
    $current_version = get_option('mayo_version');
    if (empty($current_version) 
        || version_compare($current_version, MAYO_VERSION, '<')
    ) {
        Bmltenabled_Mayo_activate();
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
function Bmltenabled_Mayo_loadArchiveTemplate($template)
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
function Bmltenabled_Mayo_loadDetailsTemplate($template)
{
    if (is_singular('mayo_event')) {
        $custom_template = plugin_dir_path(__FILE__) .
        'templates/details-mayo-event.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }
    if (is_singular('mayo_announcement')) {
        $custom_template = plugin_dir_path(__FILE__) .
        'templates/details-mayo-announcement.php';
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
function Bmltenabled_Mayo_enqueueAdminScripts()
{
    wp_enqueue_script(
        'admin-bundle',
        plugin_dir_url(__FILE__) . 'assets/js/dist/admin.bundle.js',
        ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data'],
        '1.0',
        true
    );
}
add_action('enqueue_block_editor_assets', 'Bmltenabled_Mayo_enqueueAdminScripts');

/**
 * Handle subscription confirmation and unsubscribe requests
 *
 * @return void
 */
function Bmltenabled_Mayo_handleSubscriptionRequests()
{
    // Handle confirmation
    if (isset($_GET['mayo_confirm'])) {
        $token = sanitize_text_field($_GET['mayo_confirm']);
        $result = Subscriber::confirm($token);

        // Display result page
        Bmltenabled_Mayo_displaySubscriptionMessage(
            $result['success'] ? 'Subscription Confirmed' : 'Confirmation Error',
            $result['message'],
            $result['success']
        );
        exit;
    }

    // Handle unsubscribe
    if (isset($_GET['mayo_unsubscribe'])) {
        $token = sanitize_text_field($_GET['mayo_unsubscribe']);

        // Check if form was submitted (POST request with confirmation)
        $is_post = $_SERVER['REQUEST_METHOD'] === 'POST';
        if ($is_post && isset($_POST['confirm_unsubscribe'])) {
            // Verify nonce
            if (!wp_verify_nonce($_POST['_wpnonce'], 'mayo_unsubscribe_' . $token)) {
                Bmltenabled_Mayo_displaySubscriptionMessage(
                    'Error',
                    'Invalid request. Please try again.',
                    false
                );
                exit;
            }

            $result = Subscriber::unsubscribe($token);

            Bmltenabled_Mayo_displaySubscriptionMessage(
                $result['success'] ? 'Unsubscribed' : 'Unsubscribe Error',
                $result['message'],
                $result['success']
            );
            exit;
        }

        // Show confirmation page
        Bmltenabled_Mayo_displayUnsubscribeConfirmation($token);
        exit;
    }
}
add_action('template_redirect', 'Bmltenabled_Mayo_handleSubscriptionRequests');

/**
 * Display a subscription-related message page
 *
 * @param string $title   Page title
 * @param string $message Message to display
 * @param bool   $success Whether this is a success message
 *
 * @return void
 */
function Bmltenabled_Mayo_displaySubscriptionMessage($title, $message, $success)
{
    $site_name = get_bloginfo('name');
    $home_url = home_url();
    $bg_color = $success ? '#d4edda' : '#f8d7da';
    $text_color = $success ? '#155724' : '#721c24';
    $border_color = $success ? '#c3e6cb' : '#f5c6cb';

    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo esc_html($title) . ' - ' . esc_html($site_name); ?></title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI",
                    Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue",
                    sans-serif;
                background: #f1f1f1;
                margin: 0;
                padding: 40px 20px;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: calc(100vh - 80px);
            }
            .message-box {
                background: #fff;
                padding: 40px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                max-width: 500px;
                text-align: center;
            }
            .message-box h1 {
                margin: 0 0 20px;
                font-size: 24px;
                color: #333;
            }
            .message-box .alert {
                padding: 15px 20px;
                border-radius: 4px;
                margin-bottom: 20px;
                background: <?php echo $bg_color; ?>;
                color: <?php echo $text_color; ?>;
                border: 1px solid <?php echo $border_color; ?>;
            }
            .message-box a {
                display: inline-block;
                padding: 10px 20px;
                background: #0073aa;
                color: #fff;
                text-decoration: none;
                border-radius: 4px;
                margin-top: 10px;
            }
            .message-box a:hover {
                background: #005a87;
            }
        </style>
    </head>
    <body>
        <div class="message-box">
            <h1><?php echo esc_html($title); ?></h1>
            <div class="alert"><?php echo esc_html($message); ?></div>
            <a href="<?php echo esc_url($home_url); ?>">
                Return to <?php echo esc_html($site_name); ?>
            </a>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Display unsubscribe confirmation page
 *
 * @param string $token Subscriber token
 *
 * @return void
 */
function Bmltenabled_Mayo_displayUnsubscribeConfirmation($token)
{
    $site_name = get_bloginfo('name');
    $home_url = home_url();
    $nonce = wp_create_nonce('mayo_unsubscribe_' . $token);

    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Unsubscribe - <?php echo esc_html($site_name); ?></title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI",
                    Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue",
                    sans-serif;
                background: #f1f1f1;
                margin: 0;
                padding: 40px 20px;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: calc(100vh - 80px);
            }
            .message-box {
                background: #fff;
                padding: 40px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                max-width: 500px;
                text-align: center;
            }
            .message-box h1 {
                margin: 0 0 20px;
                font-size: 24px;
                color: #333;
            }
            .message-box p {
                color: #666;
                margin-bottom: 24px;
                line-height: 1.6;
            }
            .button-group {
                display: flex;
                gap: 12px;
                justify-content: center;
                flex-wrap: wrap;
            }
            .btn {
                display: inline-block;
                padding: 12px 24px;
                text-decoration: none;
                border-radius: 4px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                border: none;
            }
            .btn-danger {
                background: #dc3545;
                color: #fff;
            }
            .btn-danger:hover {
                background: #c82333;
            }
            .btn-secondary {
                background: #6c757d;
                color: #fff;
            }
            .btn-secondary:hover {
                background: #5a6268;
            }
        </style>
    </head>
    <body>
        <div class="message-box">
            <h1>Unsubscribe from Announcements</h1>
            <p>
                Are you sure you want to unsubscribe from
                <?php echo esc_html($site_name); ?> announcements?
                You will no longer receive email notifications.
            </p>
            <form method="post" class="button-group">
                <input type="hidden" name="_wpnonce"
                    value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="confirm_unsubscribe" value="1">
                <button type="submit" class="btn btn-danger">
                    Yes, Unsubscribe
                </button>
                <a href="<?php echo esc_url($home_url); ?>"
                    class="btn btn-secondary">Cancel
                </a>
            </form>
        </div>
    </body>
    </html>
    <?php
}
