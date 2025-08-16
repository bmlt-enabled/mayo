<?php

namespace BmltEnabled\Mayo;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Admin {
    public static function init() {
        // Register post type on init
        add_action('init', [__CLASS__, 'register_post_type'], 0); // Priority 0 to ensure it runs early
        add_action('init', [__CLASS__, 'register_meta_fields']);
        
        // Admin menu and scripts
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        
        // Custom columns
        add_filter('manage_mayo_event_posts_columns', [__CLASS__, 'set_custom_columns']);
        add_action('manage_mayo_event_posts_custom_column', [__CLASS__, 'render_custom_columns'], 10, 2);
        
        // Row actions
        add_filter('post_row_actions', [__CLASS__, 'add_row_actions'], 10, 2);
        
        // AJAX handlers
        add_action('wp_ajax_copy_event', [__CLASS__, 'handle_copy_event']);
        
        // Post status transition hook for event publish notifications
        add_action('transition_post_status', [__CLASS__, 'handle_event_status_transition'], 10, 3);
    }

    public static function register_post_type() {
            register_post_type('mayo_event', [
                'labels' => [
                    'name' => 'Events',
                    'singular_name' => 'Event',
                    'add_new' => 'Add New Event',
                    'add_new_item' => 'Add New Event',
                    'edit_item' => 'Edit Event',
                    'view_item' => 'View Event',
                ],
                'public' => true,
                'show_in_menu' => 'mayo-events',
                'supports' => ['title', 'editor', 'thumbnail', 'custom-fields', 'categories', 'tags'],
                'taxonomies' => ['category', 'post_tag'],
                'has_archive' => true,
                'publicly_queryable' => true,
                'rewrite' => [
                    'slug' => 'mayo',
                    'with_front' => true,
                ],
                'menu_icon' => 'dashicons-calendar',
                'show_in_rest' => true, // Enable Gutenberg editor
            ]);
        }

    public static function add_menu() {
        add_menu_page(
            'Mayo',
            'Mayo',
            'manage_options',
            'mayo-events',
            [__CLASS__, 'render_admin_page'],
            'dashicons-calendar'
        );

        add_submenu_page(
            'mayo-events',
            'Shortcodes',
            'Shortcodes',
            'manage_options',
            'mayo-shortcodes',
            [__CLASS__, 'render_shortcodes_page']
        );

        add_submenu_page(
            'mayo-events',
            'Mayo Settings',
            'Settings',
            'manage_options',
            'mayo-settings',
            [__CLASS__, 'render_settings_page']
        );

        add_submenu_page(
            'mayo-events',
            'CSS Classes',
            'CSS Classes',
            'manage_options',
            'mayo-css-classes',
            [__CLASS__, 'render_css_classes_page']
        );
    }

    public static function render_admin_page() {
        echo '<div id="mayo-admin"></div>';
    }

    public static function enqueue_scripts($hook) {
        if (!in_array($hook, ['toplevel_page_mayo-events', 'mayo_page_mayo-shortcodes', 'mayo_page_mayo-settings', 'mayo_page_mayo-css-classes', 'edit.php'])) {
            return;
        }

        // Register and enqueue the script
        wp_register_script(
            'mayo-admin',
            plugin_dir_url(__DIR__) . 'assets/js/dist/admin.bundle.js',
            [
                'wp-element',
                'wp-components',
                'wp-api-fetch',
                'wp-plugins',
                'wp-edit-post',
                'wp-i18n',
                'wp-data',
                'wp-block-editor',
                'wp-blocks'
            ],
            defined('MAYO_VERSION') ? MAYO_VERSION : '1.0',
            true
        );

        // Enqueue styles
        wp_enqueue_style('wp-components');
        wp_enqueue_style(
            'mayo-admin',
            plugin_dir_url(__DIR__) . 'assets/css/admin.css',
            [],
            defined('MAYO_VERSION') ? MAYO_VERSION : '1.0'
        );
        
        // Add the REST API nonce for our plugin
        wp_localize_script(
            'mayo-admin',
            'mayoApiSettings',
            [
                'root' => esc_url_raw(rest_url()),
                'nonce' => wp_create_nonce('wp_rest'),
                'namespace' => 'event-manager/v1',
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'ajaxNonce' => wp_create_nonce('mayo_admin_nonce')
            ]
        );
        
        // Now enqueue the script after localization
        wp_enqueue_script('mayo-admin');
        
        // Add inline script for admin list functionality
        if ($hook === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'mayo_event') {
            wp_add_inline_script('mayo-admin', '
                jQuery(document).ready(function($) {
                    // Handle copy event links
                    $(document).on("click", "a[href*=\'action=copy_event\']", function(e) {
                        e.preventDefault();
                        var href = $(this).attr("href");
                        var postId = href.match(/post=(\d+)/)[1];
                        
                        if (confirm("Are you sure you want to copy this event?")) {
                            $.post(mayoApiSettings.ajaxUrl, {
                                action: "copy_event",
                                post_id: postId,
                                _ajax_nonce: mayoApiSettings.ajaxNonce
                            }, function(response) {
                                location.reload();
                            }).fail(function() {
                                alert("Failed to copy event. Please try again.");
                            });
                        }
                    });
                });
            ');
        }
    }

    public static function set_custom_columns($columns) {
        return [
            'cb' => $columns['cb'],
            'title' => __('Event Name', 'mayo-events-manager'),
            'event_type' => __('Type', 'mayo-events-manager'),
            'event_datetime' => __('Date & Time', 'mayo-events-manager'),
            'attachments' => __('Attachments', 'mayo-events-manager'),
            'status' => __('Status', 'mayo-events-manager'),
            'service_body' => __('Service Body', 'mayo-events-manager'),
            'date' => $columns['date']
        ];
    }

    public static function render_custom_columns($column, $post_id) {
        switch ($column) {
            case 'event_type':
                echo esc_html(get_post_meta($post_id, 'event_type', true));
                break;
            case 'service_body':
                $service_body_id = get_post_meta($post_id, 'service_body', true);
                if ($service_body_id === '0') {
                    echo esc_html('Unaffiliated (0)');
                } else {
                    // Get the service body name from the BMLT root server
                    $settings = get_option('mayo_settings', []);
                    $bmlt_root_server = $settings['bmlt_root_server'] ?? '';
                    $found = false;
                    
                    if (!empty($bmlt_root_server)) {
                        $response = wp_remote_get($bmlt_root_server . '/client_interface/json/?switcher=GetServiceBodies');
                        
                        if (!is_wp_error($response)) {
                            $service_bodies = json_decode(wp_remote_retrieve_body($response), true);
                            
                            if (is_array($service_bodies)) {
                                foreach ($service_bodies as $body) {
                                    if ($body['id'] == $service_body_id) {
                                        echo esc_html($body['name'] . ' (' . $body['id'] . ')');
                                        $found = true;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    
                    // Fallback if we couldn't get the name
                    if (!$found) {
                        if (empty($service_body_id)) {
                            echo esc_html('—');
                        } else {
                            echo esc_html('Service Body (' . $service_body_id . ')');
                        }
                    }
                }
                break;
            case 'event_datetime':
                $start_date = get_post_meta($post_id, 'event_start_date', true);
                $end_date = get_post_meta($post_id, 'event_end_date', true);
                $start_time = get_post_meta($post_id, 'event_start_time', true);
                $end_time = get_post_meta($post_id, 'event_end_time', true);
                $timezone = get_post_meta($post_id, 'timezone', true);
                $recurring_pattern = get_post_meta($post_id, 'recurring_pattern', true);
                $skipped_occurrences = get_post_meta($post_id, 'skipped_occurrences', true) ?: [];
                
                // Create a DateTimeZone object from the stored timezone
                $tz = !empty($timezone) ? new \DateTimeZone($timezone) : wp_timezone();
                
                // Get timezone abbreviation to display
                $timezone_abbr = '';
                if (!empty($timezone)) {
                    // Create a datetime in the event's timezone to get its abbreviation
                    $dt_for_tz = new \DateTime('now', $tz);
                    $timezone_abbr = $dt_for_tz->format('T'); // Gets timezone abbreviation (like EDT, PST)
                }
                
                // Format start date/time
                if ($start_date) {
                    // Create DateTime object with the event's timezone
                    $start_dt = new \DateTime($start_date . ' ' . ($start_time ?: '00:00:00'), $tz);
                    $start_formatted = $start_dt->format('M j, Y');
                    if ($start_time) {
                        $start_formatted .= ' ' . $start_dt->format('g:i A');
                    }
                }
                
                // Format end date/time
                if ($end_date || $end_time) {
                    $end_formatted = '';
                    if ($end_date) {
                        // Create DateTime object with the event's timezone
                        $end_dt = new \DateTime($end_date . ' ' . ($end_time ?: '00:00:00'), $tz);
                        $end_formatted = $end_dt->format('M j, Y');
                    } else if ($start_date) {
                        // Use start date with end time
                        $end_dt = new \DateTime($start_date . ' ' . ($end_time ?: '00:00:00'), $tz);
                        $end_formatted = $end_dt->format('M j, Y');
                    }
                    
                    if ($end_time) {
                        if (isset($end_dt)) {
                            $end_formatted .= ' ' . $end_dt->format('g:i A');
                        }
                    }
                    
                    if ($start_formatted && $end_formatted) {
                        $display = "$start_formatted - $end_formatted";
                        if (!empty($timezone_abbr)) {
                            $display .= " ($timezone_abbr)";
                        }
                        echo esc_html($display);
                    } else {
                        $display = $start_formatted ?: $end_formatted;
                        if (!empty($timezone_abbr) && !empty($display)) {
                            $display .= " ($timezone_abbr)";
                        }
                        echo esc_html($display);
                    }
                } else {
                    $display = $start_formatted ?? '';
                    if (!empty($timezone_abbr) && !empty($display)) {
                        $display .= " ($timezone_abbr)";
                    }
                    echo esc_html($display);
                }
                
                // Add recurring information below the date/time
                if ($recurring_pattern && $recurring_pattern['type'] !== 'none') {
                    echo '<br><small class="recurring-indicator">';
                    echo '🔄 Recurring';
                    
                    // Add skipped count if any
                    if (!empty($skipped_occurrences)) {
                        echo ' • ' . count($skipped_occurrences) . ' skipped';
                    }
                    echo '</small>';
                }
                break;
            case 'status':
                echo esc_html(get_post_status($post_id));
                break;
            case 'attachments':
                // Check for featured image (flyer)
                if (has_post_thumbnail($post_id)) {
                    $thumb_url = get_the_post_thumbnail_url($post_id, 'thumbnail');
                    echo '<img src="' . esc_url($thumb_url) . '" width="50" height="50" style="margin-right: 10px;" />';
                }

                break;
        }
    }

    public static function register_meta_fields() {
        register_post_meta('mayo_event', 'event_type', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function() { 
                return current_user_can('edit_posts'); 
            }
        ]);

        register_post_meta('mayo_event', 'service_body', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function() { 
                return current_user_can('edit_posts'); 
            }
        ]);

        register_post_meta('mayo_event', 'event_start_date', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function() { 
                return current_user_can('edit_posts'); 
            }
        ]);

        register_post_meta('mayo_event', 'event_end_date', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function() { 
                return current_user_can('edit_posts'); 
            }
        ]);

        register_post_meta('mayo_event', 'event_start_time', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function() { 
                return current_user_can('edit_posts'); 
            }
        ]);

        register_post_meta('mayo_event', 'event_end_time', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function() { 
                return current_user_can('edit_posts'); 
            }
        ]);

        register_post_meta('mayo_event', 'timezone', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function() { 
                return current_user_can('edit_posts'); 
            }
        ]);

        register_post_meta('mayo_event', 'flyer_id', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => '',
            'auth_callback' => function() { 
                return current_user_can('edit_posts'); 
            }
        ]);

        register_post_meta('mayo_event', 'location_name', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function() { 
                return current_user_can('edit_posts'); 
            }
        ]);

        register_post_meta('mayo_event', 'location_address', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function() { 
                return current_user_can('edit_posts'); 
            }
        ]);

        register_post_meta('mayo_event', 'location_details', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function() { 
                return current_user_can('edit_posts'); 
            }
        ]);

        register_post_meta('mayo_event', 'recurring_pattern', [
            'show_in_rest' => [
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'type'         => ['type' => 'string'],
                        'interval'     => ['type' => 'integer'],
                        'weekdays'     => ['type' => 'array', 'items' => ['type' => 'integer']],
                        'monthlyType'  => ['type' => 'string'],
                        'monthlyDate'  => ['type' => 'string'],
                        'monthlyWeekday' => ['type' => 'string'],
                        'endDate'      => ['type' => 'string']
                    ]
                ]
            ],
            'single' => true,
            'type' => 'object',
            'default' => [
                'type' => 'none',
                'interval' => 1,
                'weekdays' => [],
                'monthlyType' => 'date',
                'monthlyDate' => '',
                'monthlyWeekday' => '',
                'endDate' => ''
            ],
            'auth_callback' => function() { 
                return current_user_can('edit_posts'); 
            }
        ]);

        register_post_meta('mayo_event', 'skipped_occurrences', [
            'show_in_rest' => [
                'schema' => [
                    'type' => 'array',
                    'items' => ['type' => 'string']
                ]
            ],
            'single' => true,
            'type' => 'array',
            'default' => [],
            'auth_callback' => function() { 
                return current_user_can('edit_posts'); 
            }
        ]);

        register_post_meta('mayo_event', 'contact_name', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function() { 
                return current_user_can('edit_posts'); 
            }
        ]);

        register_post_meta('mayo_event', 'email', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_email',
            'auth_callback' => function() { 
                return current_user_can('edit_posts'); 
            }
        ]);
    }

    public static function render_shortcodes_page() {
        echo '<div id="mayo-shortcode-root" class="wrap"></div>';
    }

    public static function render_settings_page() {
        // Output a container div for React to render into
        echo '<div id="mayo-settings-root"></div>';
    }

    public static function render_css_classes_page() {
        // Output a container div for React to render into
        echo '<div id="mayo-css-classes-root" class="wrap"></div>';
    }

    public static function add_row_actions($actions, $post) {
        if ($post->post_type === 'mayo_event') {
            $actions['copy'] = '<a href="' . esc_url(admin_url('admin-ajax.php?action=copy_event&post=' . $post->ID)) . '">Copy</a>';
        }
        return $actions;
    }

    public static function handle_copy_event() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'mayo_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if ($post_id > 0 && current_user_can('edit_posts')) {
            $original_post = get_post($post_id);
            if (!$original_post || $original_post->post_type !== 'mayo_event') {
                wp_send_json_error('Invalid event');
                return;
            }
            
            $new_post = [
                'post_title' => $original_post->post_title . ' (Copy)',
                'post_content' => $original_post->post_content,
                'post_status' => 'draft',
                'post_type' => 'mayo_event',
                'post_author' => get_current_user_id(),
            ];

            $new_post_id = wp_insert_post($new_post);
            if ($new_post_id && !is_wp_error($new_post_id)) {
                // Copy all meta fields
                $meta_fields = [
                    'event_type',
                    'service_body',
                    'event_start_date',
                    'event_end_date',
                    'event_start_time',
                    'event_end_time',
                    'timezone',
                    'location_name',
                    'location_address',
                    'location_details',
                    'recurring_pattern',
                    'contact_name',
                    'email'
                ];

                foreach ($meta_fields as $meta_field) {
                    $value = get_post_meta($post_id, $meta_field, true);
                    if ($value !== '') {
                        update_post_meta($new_post_id, $meta_field, $value);
                    }
                }
                
                // Copy featured image if exists
                if (has_post_thumbnail($post_id)) {
                    $thumbnail_id = get_post_thumbnail_id($post_id);
                    set_post_thumbnail($new_post_id, $thumbnail_id);
                }
                
                // Copy categories and tags
                $categories = wp_get_post_categories($post_id);
                if (!empty($categories)) {
                    wp_set_post_categories($new_post_id, $categories);
                }
                
                $tags = wp_get_post_tags($post_id);
                if (!empty($tags)) {
                    wp_set_post_tags($new_post_id, $tags);
                }
                
                wp_send_json_success([
                    'message' => 'Event copied successfully',
                    'new_post_id' => $new_post_id,
                    'edit_url' => get_edit_post_link($new_post_id, 'raw')
                ]);
            } else {
                wp_send_json_error('Failed to create copy');
            }
        } else {
            wp_send_json_error('Invalid post ID or insufficient permissions');
        }
    }
    
    /**
     * Handle post status transitions for mayo_event posts
     * Sends notification emails to event submitters when their events are published
     * 
     * @param string $new_status The new post status
     * @param string $old_status The old post status
     * @param WP_Post $post The post object
     */
    public static function handle_event_status_transition($new_status, $old_status, $post) {
        // Only handle mayo_event post type
        if ($post->post_type !== 'mayo_event') {
            return;
        }
        
        // Only send notification when status changes from pending to publish
        if ($old_status === 'pending' && $new_status === 'publish') {
            self::send_event_published_notification($post);
        }
    }
    
    /**
     * Send notification email to event submitter when their event is published
     * 
     * @param WP_Post $post The published event post
     */
    private static function send_event_published_notification($post) {
        // Get submitter email from post meta
        $submitter_email = get_post_meta($post->ID, 'email', true);
        $contact_name = get_post_meta($post->ID, 'contact_name', true);
        
        // If no email found, don't send notification
        if (empty($submitter_email) || !is_email($submitter_email)) {
            error_log('Mayo Events: No valid submitter email found for event ID ' . $post->ID);
            return;
        }
        
        // Get event metadata
        $event_type = get_post_meta($post->ID, 'event_type', true);
        $event_start_date = get_post_meta($post->ID, 'event_start_date', true);
        $event_end_date = get_post_meta($post->ID, 'event_end_date', true);
        $event_start_time = get_post_meta($post->ID, 'event_start_time', true);
        $event_end_time = get_post_meta($post->ID, 'event_end_time', true);
        $timezone = get_post_meta($post->ID, 'timezone', true);
        $service_body = get_post_meta($post->ID, 'service_body', true);
        $location_name = get_post_meta($post->ID, 'location_name', true);
        $location_address = get_post_meta($post->ID, 'location_address', true);
        $location_details = get_post_meta($post->ID, 'location_details', true);
        $recurring_pattern = get_post_meta($post->ID, 'recurring_pattern', true);
        
        // Get categories and tags
        $categories = wp_get_post_categories($post->ID, ['fields' => 'names']);
        $tags = wp_get_post_tags($post->ID, ['fields' => 'names']);
        
        // Build params array for email content
        $params = [
            'event_name' => get_the_title($post),
            'event_type' => $event_type,
            'event_start_date' => $event_start_date,
            'event_end_date' => $event_end_date,
            'event_start_time' => $event_start_time,
            'event_end_time' => $event_end_time,
            'timezone' => $timezone,
            'service_body' => $service_body,
            'contact_name' => $contact_name,
            'email' => $submitter_email,
            'description' => $post->post_content,
            'location_name' => $location_name,
            'location_address' => $location_address,
            'location_details' => $location_details,
            'recurring_pattern' => $recurring_pattern,
            'categories' => !empty($categories) ? implode(', ', $categories) : '',
            'tags' => !empty($tags) ? implode(', ', $tags) : ''
        ];
        
        // Build email content using shared method from Rest class
        $subject_template = 'Your Event Has Been Published: %s';
        $view_url = get_permalink($post->ID);
        
        $email_content = \BmltEnabled\Mayo\Rest::build_event_email_content($params, $subject_template, $view_url);
        
        // Send email
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        $sent = wp_mail($submitter_email, $email_content['subject'], $email_content['message'], $headers);
        
        if (!$sent) {
            error_log('Mayo Events: Failed to send event published notification to ' . $submitter_email . ' for event ID ' . $post->ID);
        }
    }
}