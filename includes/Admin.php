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
                'slug' => 'events',
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
            [__CLASS__, 'render_shortcode_docs']
        );
    }

    public static function render_admin_page() {
        echo '<div id="mayo-admin"></div>';
    }

    public static function enqueue_scripts($hook) {
        // Only load admin bundle for mayo_event post type editing or shortcodes page
        if ($hook === 'post.php' || $hook === 'post-new.php' || $hook === 'mayo_page_mayo-shortcodes') {
            $screen = get_current_screen();
            if ($screen && ($screen->post_type === 'mayo_event' || $hook === 'mayo_page_mayo-shortcodes')) {
                // Add wp-edit-post to dependencies
                $deps = [
                    'wp-plugins',
                    'wp-edit-post',  // Make sure this is included
                    'wp-editor',
                    'wp-element',
                    'wp-components',
                    'wp-data',
                    'wp-i18n',
                    'wp-block-editor',
                    'wp-edit-post'   // This is the key dependency we need
                ];

                wp_enqueue_script(
                    'mayo-admin',
                    plugin_dir_url(__FILE__) . '../assets/js/dist/admin.bundle.js',
                    $deps,
                    '1.0',
                    true
                );
                
                wp_enqueue_style(
                    'mayo-admin',
                    plugin_dir_url(__FILE__) . '../assets/css/admin.css',
                    [],
                    '1.0'
                );
            }
        }
    }

    public static function set_custom_columns($columns) {
        return [
            'cb' => $columns['cb'],
            'title' => __('Event Name'),
            'event_type' => __('Type'),
            'event_datetime' => __('Date & Time'),
            'status' => __('Status'),
            'date' => $columns['date']
        ];
    }

    public static function render_custom_columns($column, $post_id) {
        switch ($column) {
            case 'event_type':
                echo get_post_meta($post_id, 'event_type', true);
                break;
            case 'event_datetime':
                $start_date = get_post_meta($post_id, 'event_start_date', true);
                $end_date = get_post_meta($post_id, 'event_end_date', true);
                $start_time = get_post_meta($post_id, 'event_start_time', true);
                $end_time = get_post_meta($post_id, 'event_end_time', true);
                
                // Format start date/time
                if ($start_date) {
                    $start_formatted = date('M j, Y', strtotime($start_date));
                    if ($start_time) {
                        $start_formatted .= ' ' . date('g:i A', strtotime($start_time));
                    }
                }
                
                // Format end date/time
                if ($end_date || $end_time) {
                    $end_formatted = '';
                    if ($end_date) {
                        $end_formatted = date('M j, Y', strtotime($end_date));
                    } else {
                        $end_formatted = $start_formatted ? date('M j, Y', strtotime($start_date)) : '';
                    }
                    if ($end_time) {
                        $end_formatted .= ' ' . date('g:i A', strtotime($end_time));
                    }
                    
                    if ($start_formatted && $end_formatted) {
                        echo "$start_formatted - $end_formatted";
                    } else {
                        echo $start_formatted ?: $end_formatted;
                    }
                } else {
                    echo $start_formatted ?? '';
                }
                break;
            case 'status':
                echo get_post_status($post_id);
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

        register_post_meta('mayo_event', 'flyer_id', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => '',
            'auth_callback' => function() { 
                return current_user_can('edit_posts'); 
            }
        ]);

        register_post_meta('mayo_event', 'flyer_url', [
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

    public static function render_shortcode_docs() {
        ?>
        <div class="wrap mayo-docs">
            <h1>Mayo Events Shortcodes</h1>
            
            <div class="card">
                <h2>Event List Shortcode</h2>
                <p>Use this shortcode to display a list of upcoming events:</p>
                <pre><code>[mayo_event_list]</code></pre>
                
                <h3>Optional Parameters</h3>
                <table class="widefat">
                    <tr>
                        <th>Parameter</th>
                        <th>Description</th>
                        <th>Default</th>
                        <th>Options</th>
                    </tr>
                    <tr>
                        <td>time_format</td>
                        <td>Format for displaying time</td>
                        <td>12hour</td>
                        <td>12hour, 24hour</td>
                    </tr>
                    <tr>
                        <td>per_page</td>
                        <td>Number of events to show per page</td>
                        <td>10</td>
                        <td>Any positive number</td>
                    </tr>
                    <tr>
                        <td>show_pagination</td>
                        <td>Whether to show pagination controls</td>
                        <td>true</td>
                        <td>true, false</td>
                    </tr>
                </table>
                
                <h3>Example with Parameters</h3>
                <pre><code>[mayo_event_list time_format="24hour" per_page="5" show_pagination="true"]</code></pre>
            </div>

            <div class="card">
                <h2>Event Submission Form Shortcode</h2>
                <p>Use this shortcode to display a form that allows users to submit events:</p>
                <pre><code>[mayo_event_form]</code></pre>
                
                <h3>Features</h3>
                <ul class="ul-disc">
                    <li>Event name and type selection</li>
                    <li>Date and time selection</li>
                    <li>Event description with rich text editor</li>
                    <li>Event flyer upload</li>
                    <li>Location details (name, address, additional info)</li>
                    <li>Category and tag selection</li>
                    <li>Recurring event patterns</li>
                </ul>

                <h3>Notes</h3>
                <ul class="ul-disc">
                    <li>Submitted events are saved as pending and require admin approval</li>
                    <li>Required fields are marked with an asterisk (*)</li>
                    <li>Images are automatically processed and stored in the media library</li>
                    <li>Form includes built-in validation and error handling</li>
                </ul>

                <h3>Example Usage</h3>
                <pre><code>[mayo_event_form]</code></pre>
            </div>
        </div>
        <?php
    }
}