<?php

namespace BmltEnabled\Mayo;

class Admin {
    public static function init() {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_action('init', [__CLASS__, 'register_meta_fields']);
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        
        // Add custom columns
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
    }

    public static function render_admin_page() {
        echo '<div id="mayo-admin"></div>';
    }

    public static function enqueue_scripts($hook) {
        if (!in_array($hook, ['toplevel_page_mayo-events', 'mayo_page_mayo-shortcodes', 'mayo_page_mayo-settings'])) {
            return;
        }

        wp_enqueue_script(
            'mayo-admin',
            plugin_dir_url(__DIR__) . 'assets/js/dist/admin.bundle.js',
            [
                'wp-element',
                'wp-components',
                'wp-api-fetch',
                'wp-plugins',
                'wp-edit-post',
                'wp-i18n',
                'wp-data'
            ],
            '1.0',
            true
        );

        wp_enqueue_style('wp-components');
        wp_enqueue_style(
            'mayo-admin',
            plugin_dir_url(__DIR__) . 'assets/css/admin.css',
            [],
            '1.0'
        );
    }

    public static function set_custom_columns($columns) {
        return [
            'cb' => $columns['cb'],
            'title' => __('Event Name', 'mayo-event-manager'),
            'event_type' => __('Type', 'mayo-event-manager'),
            'event_datetime' => __('Date & Time', 'mayo-event-manager'),
            'status' => __('Status', 'mayo-event-manager'),
            'service_body' => __('Service Body', 'mayo-event-manager'),
            'date' => $columns['date']
        ];
    }

    public static function render_custom_columns($column, $post_id) {
        switch ($column) {
            case 'event_type':
                echo esc_html(get_post_meta($post_id, 'event_type', true));
                break;
            case 'service_body':
                echo esc_html(get_post_meta($post_id, 'service_body', true));
                break;
            case 'event_datetime':
                $start_date = get_post_meta($post_id, 'event_start_date', true);
                $end_date = get_post_meta($post_id, 'event_end_date', true);
                $start_time = get_post_meta($post_id, 'event_start_time', true);
                $end_time = get_post_meta($post_id, 'event_end_time', true);
                
                // Format start date/time
                if ($start_date) {
                    $start_formatted = wp_date('M j, Y', strtotime($start_date));
                    if ($start_time) {
                        $start_formatted .= ' ' . wp_date('g:i A', strtotime($start_time));
                    }
                }
                
                // Format end date/time
                if ($end_date || $end_time) {
                    $end_formatted = '';
                    if ($end_date) {
                        $end_formatted = wp_date('M j, Y', strtotime($end_date));
                    } else {
                        $end_formatted = $start_formatted ? wp_date('M j, Y', strtotime($start_date)) : '';
                    }
                    if ($end_time) {
                        $end_formatted .= ' ' . wp_date('g:i A', strtotime($end_time));
                    }
                    
                    if ($start_formatted && $end_formatted) {
                        echo esc_html("$start_formatted - $end_formatted");
                    } else {
                        echo esc_html($start_formatted ?: $end_formatted);
                    }
                } else {
                    echo esc_html($start_formatted ?? '');
                }
                break;
            case 'status':
                echo esc_html(get_post_status($post_id));
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
    }

    public static function render_shortcodes_page() {
        echo '<div id="mayo-shortcode-root" class="wrap"></div>';
    }

    public static function render_settings_page() {
        echo '<div id="mayo-settings-root"></div>';
    }
}