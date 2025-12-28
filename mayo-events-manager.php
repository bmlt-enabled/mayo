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
        [
            'wp-plugins',
            'wp-edit-post',
            'wp-editor',
            'wp-element',
            'wp-components',
            'wp-data'
        ],
        defined('MAYO_VERSION') ? MAYO_VERSION : '1.0',
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

    // Handle manage subscription / unsubscribe
    if (isset($_GET['mayo_unsubscribe'])) {
        $token = sanitize_text_field($_GET['mayo_unsubscribe']);

        // Check if form was submitted (POST request)
        $is_post = $_SERVER['REQUEST_METHOD'] === 'POST';
        if ($is_post) {
            // Verify nonce
            if (!wp_verify_nonce($_POST['_wpnonce'], 'mayo_manage_' . $token)) {
                Bmltenabled_Mayo_displaySubscriptionMessage(
                    'Error',
                    'Invalid request. Please try again.',
                    false
                );
                exit;
            }

            // Handle unsubscribe
            if (isset($_POST['confirm_unsubscribe'])) {
                $result = Subscriber::unsubscribe($token);

                Bmltenabled_Mayo_displaySubscriptionMessage(
                    $result['success'] ? 'Unsubscribed' : 'Unsubscribe Error',
                    $result['message'],
                    $result['success']
                );
                exit;
            }

            // Handle save preferences
            if (isset($_POST['save_preferences'])) {
                $pref_cats = isset($_POST['pref_categories'])
                    ? array_map('intval', $_POST['pref_categories'])
                    : [];
                $pref_tags = isset($_POST['pref_tags'])
                    ? array_map('intval', $_POST['pref_tags'])
                    : [];
                $pref_sbs = isset($_POST['pref_service_bodies'])
                    ? array_map('sanitize_text_field', $_POST['pref_service_bodies'])
                    : [];
                $preferences = [
                    'categories' => $pref_cats,
                    'tags' => $pref_tags,
                    'service_bodies' => $pref_sbs,
                ];

                $result = Subscriber::update_preferences($token, $preferences);

                if ($result) {
                    Bmltenabled_Mayo_displaySubscriptionMessage(
                        'Preferences Saved',
                        'Your subscription preferences have been updated.',
                        true
                    );
                } else {
                    Bmltenabled_Mayo_displaySubscriptionMessage(
                        'Error',
                        'Failed to update preferences. Please try again.',
                        false
                    );
                }
                exit;
            }
        }

        // Show manage subscription page
        Bmltenabled_Mayo_displayManageSubscription($token);
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
 * Display manage subscription page
 *
 * @param string $token Subscriber token
 *
 * @return void
 */
function Bmltenabled_Mayo_displayManageSubscription($token)
{
    $site_name = get_bloginfo('name');
    $home_url = home_url();
    $nonce = wp_create_nonce('mayo_manage_' . $token);

    // Get subscriber data
    $subscriber = Subscriber::get_by_token($token);
    if (!$subscriber) {
        Bmltenabled_Mayo_displaySubscriptionMessage(
            'Error',
            'Subscription not found.',
            false
        );
        return;
    }

    // Get current preferences
    $current_prefs = !empty($subscriber->preferences)
        ? json_decode($subscriber->preferences, true)
        : ['categories' => [], 'tags' => [], 'service_bodies' => []];

    // Get available subscription options from settings
    $settings = get_option('mayo_settings', []);
    $enabled_categories = isset($settings['subscription_categories'])
        ? $settings['subscription_categories'] : [];
    $enabled_tags = isset($settings['subscription_tags'])
        ? $settings['subscription_tags'] : [];
    $enabled_service_bodies = isset($settings['subscription_service_bodies'])
        ? $settings['subscription_service_bodies'] : [];

    // Get category and tag details
    $categories = [];
    if (!empty($enabled_categories)) {
        $cat_terms = get_terms(
            [
            'taxonomy' => 'category',
            'include' => $enabled_categories,
            'hide_empty' => false,
            ]
        );
        if (!is_wp_error($cat_terms)) {
            $categories = $cat_terms;
        }
    }

    $tags = [];
    if (!empty($enabled_tags)) {
        $tag_terms = get_terms(
            [
            'taxonomy' => 'post_tag',
            'include' => $enabled_tags,
            'hide_empty' => false,
            ]
        );
        if (!is_wp_error($tag_terms)) {
            $tags = $tag_terms;
        }
    }

    // Get service body names from BMLT
    $service_bodies = [];
    if (!empty($enabled_service_bodies)) {
        $bmlt_root_server = isset($settings['bmlt_root_server'])
            ? $settings['bmlt_root_server']
            : '';

        // Fetch service bodies from BMLT server
        $bmlt_service_bodies = [];
        if (!empty($bmlt_root_server)) {
            $sb_url = rtrim($bmlt_root_server, '/')
                . '/client_interface/json/?switcher=GetServiceBodies';
            $response = wp_remote_get($sb_url, ['timeout' => 10]);
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                if (is_array($data)) {
                    foreach ($data as $sb) {
                        if (isset($sb['id']) && isset($sb['name'])) {
                            $bmlt_service_bodies[(string) $sb['id']] = $sb['name'];
                        }
                    }
                }
            }
        }

        foreach ($enabled_service_bodies as $sb_id) {
            $sb_name = isset($bmlt_service_bodies[(string) $sb_id])
                ? $bmlt_service_bodies[(string) $sb_id]
                : $sb_id;
            $service_bodies[] = ['id' => $sb_id, 'name' => $sb_name];
        }
    }

    $has_options = !empty($categories) || !empty($tags) || !empty($service_bodies);

    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Manage Subscription - <?php echo esc_html($site_name); ?></title>
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
                align-items: flex-start;
                min-height: calc(100vh - 80px);
            }
            .manage-box {
                background: #fff;
                padding: 40px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                max-width: 600px;
                width: 100%;
            }
            .manage-box h1 {
                margin: 0 0 10px;
                font-size: 24px;
                color: #333;
            }
            .manage-box .email-display {
                color: #666;
                margin-bottom: 24px;
                font-size: 14px;
            }
            .preference-section {
                margin-bottom: 24px;
            }
            .preference-section h3 {
                margin: 0 0 12px;
                font-size: 16px;
                color: #333;
            }
            .preference-section p {
                color: #666;
                margin: 0 0 16px;
                font-size: 14px;
            }
            .preference-group {
                margin-bottom: 20px;
            }
            .preference-group-title {
                font-weight: 600;
                color: #444;
                margin-bottom: 8px;
                font-size: 14px;
            }
            .checkbox-list {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
            }
            .checkbox-item {
                display: flex;
                align-items: center;
                gap: 6px;
            }
            .checkbox-item input[type="checkbox"] {
                width: 16px;
                height: 16px;
                cursor: pointer;
            }
            .checkbox-item label {
                cursor: pointer;
                font-size: 14px;
                color: #333;
            }
            .button-group {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                margin-top: 24px;
                padding-top: 24px;
                border-top: 1px solid #eee;
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
            .btn-primary {
                background: #0073aa;
                color: #fff;
            }
            .btn-primary:hover {
                background: #005a87;
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
            .unsubscribe-section {
                margin-top: 32px;
                padding-top: 24px;
                border-top: 1px solid #eee;
            }
            .unsubscribe-section h3 {
                margin: 0 0 12px;
                font-size: 16px;
                color: #333;
            }
            .unsubscribe-section p {
                color: #666;
                margin: 0 0 16px;
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <div class="manage-box">
            <h1>Manage Your Subscription</h1>
            <div class="email-display">
                Email: <?php echo esc_html($subscriber->email); ?>
            </div>

            <?php if ($has_options) : ?>
            <form method="post">
                <input type="hidden" name="_wpnonce"
                    value="<?php echo esc_attr($nonce); ?>">

                <div class="preference-section">
                    <h3>Your Preferences</h3>
                    <p>Select what you'd like to receive notifications about:</p>

                    <?php if (!empty($categories)) : ?>
                    <div class="preference-group">
                        <div class="preference-group-title">Categories</div>
                        <div class="checkbox-list">
                            <?php foreach ($categories as $cat) : ?>
                                <?php
                                $cat_id = esc_attr($cat->term_id);
                                $cats = $current_prefs['categories'] ?? [];
                                $pref_cats = array_map('intval', $cats);
                                $cat_checked = in_array(
                                    (int) $cat->term_id,
                                    $pref_cats,
                                    true
                                ) ? 'checked' : '';
                                ?>
                            <div class="checkbox-item">
                                <input type="checkbox"
                                    name="pref_categories[]"
                                    value="<?php echo $cat_id; ?>"
                                    id="cat-<?php echo $cat_id; ?>"
                                    <?php echo $cat_checked; ?>
                                >
                                <label for="cat-<?php echo $cat_id; ?>">
                                    <?php echo esc_html($cat->name); ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($tags)) : ?>
                    <div class="preference-group">
                        <div class="preference-group-title">Tags</div>
                        <div class="checkbox-list">
                            <?php foreach ($tags as $tag) : ?>
                                <?php
                                $tag_id = esc_attr($tag->term_id);
                                $tgs = $current_prefs['tags'] ?? [];
                                $pref_tags = array_map('intval', $tgs);
                                $tag_checked = in_array(
                                    (int) $tag->term_id,
                                    $pref_tags,
                                    true
                                ) ? 'checked' : '';
                                ?>
                            <div class="checkbox-item">
                                <input type="checkbox"
                                    name="pref_tags[]"
                                    value="<?php echo $tag_id; ?>"
                                    id="tag-<?php echo $tag_id; ?>"
                                    <?php echo $tag_checked; ?>
                                >
                                <label for="tag-<?php echo $tag_id; ?>">
                                    <?php echo esc_html($tag->name); ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($service_bodies)) : ?>
                    <div class="preference-group">
                        <div class="preference-group-title">Service Bodies</div>
                        <div class="checkbox-list">
                            <?php foreach ($service_bodies as $sb) : ?>
                                <?php
                                $sb_id = esc_attr($sb['id']);
                                $sb_checked = in_array(
                                    $sb['id'],
                                    $current_prefs['service_bodies'] ?? []
                                ) ? 'checked' : '';
                                ?>
                            <div class="checkbox-item">
                                <input type="checkbox"
                                    name="pref_service_bodies[]"
                                    value="<?php echo $sb_id; ?>"
                                    id="sb-<?php echo $sb_id; ?>"
                                    <?php echo $sb_checked; ?>
                                >
                                <label for="sb-<?php echo $sb_id; ?>">
                                    <?php echo esc_html($sb['name']); ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="button-group">
                    <button type="submit"
                        name="save_preferences"
                        class="btn btn-primary">
                        Save Preferences
                    </button>
                    <a href="<?php echo esc_url($home_url); ?>"
                        class="btn btn-secondary">Cancel
                    </a>
                </div>
            </form>
            <?php endif; ?>

            <div class="unsubscribe-section">
                <h3>Unsubscribe</h3>
                <p>
                    If you no longer want to receive announcements from
                    <?php echo esc_html($site_name); ?>, you can unsubscribe below.
                </p>
                <form method="post">
                    <input type="hidden" name="_wpnonce"
                        value="<?php echo esc_attr($nonce); ?>">
                    <input type="hidden" name="confirm_unsubscribe" value="1">
                    <button type="submit" class="btn btn-danger">
                        Unsubscribe
                    </button>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
}
