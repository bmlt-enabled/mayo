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
        if (!in_array($hook, ['toplevel_page_mayo-events', 'mayo_page_mayo-shortcodes', 'mayo_page_mayo-settings', 'mayo_page_mayo-css-classes'])) {
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
                'namespace' => 'event-manager/v1'
            ]
        );
        
        // Now enqueue the script after localization
        wp_enqueue_script('mayo-admin');
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
                        if (!empty($timezone_abbr)) {
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
}