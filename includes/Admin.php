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
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
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
        // Only load admin bundle for mayo_event post type editing
        if ($hook === 'post.php' || $hook === 'post-new.php') {
            $screen = get_current_screen();
            if ($screen && $screen->post_type === 'mayo_event') {
                wp_enqueue_script(
                    'mayo-admin',
                    plugin_dir_url(__FILE__) . '../assets/js/dist/admin.bundle.js',
                    ['wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-block-editor'],
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
            'event_date' => __('Date'),
            'event_time' => __('Time'),
            'status' => __('Status'),
            'date' => $columns['date']
        ];
    }

    public static function render_custom_columns($column, $post_id) {
        switch ($column) {
            case 'event_type':
                echo get_post_meta($post_id, 'event_type', true);
                break;
            case 'event_date':
                echo get_post_meta($post_id, 'event_date', true);
                break;
            case 'event_time':
                $start = get_post_meta($post_id, 'event_start_time', true);
                $end = get_post_meta($post_id, 'event_end_time', true);
                echo "$start - $end";
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

        register_post_meta('mayo_event', 'event_date', [
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

        register_post_meta('mayo_event', 'recurring_schedule', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
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
    }

    public static function render_shortcode_docs() {
        ?>
        <div class="wrap">
            <h1>Mayo Event Manager Shortcodes</h1>
            
            <div class="card">
                <h2>Event Submission Form</h2>
                <p><code>[mayo_event_form]</code></p>
                <p>Displays a form that allows users to submit events for approval.</p>
                <h3>Usage:</h3>
                <pre><code>[mayo_event_form]</code></pre>
                <p>This shortcode has no additional parameters. Place it on any page where you want users to be able to submit events.</p>
            </div>

            <div class="card">
                <h2>Event List Display</h2>
                <p><code>[mayo_event_list]</code></p>
                <p>Displays a list of upcoming events in an accordion-style layout.</p>
                
                <h3>Parameters:</h3>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Values</th>
                            <th>Default</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>time_format</code></td>
                            <td><code>12hour</code> or <code>24hour</code></td>
                            <td><code>12hour</code></td>
                            <td>Controls how times are displayed (e.g., "2:30 PM" vs "14:30")</td>
                        </tr>
                    </tbody>
                </table>

                <h3>Examples:</h3>
                <pre><code># Default 12-hour time format
[mayo_event_list]

# Use 24-hour time format
[mayo_event_list time_format="24hour"]</code></pre>
            </div>

            <div class="card">
                <h2>Features</h2>
                <ul class="ul-disc">
                    <li>Events are automatically sorted by date</li>
                    <li>Past events are automatically filtered out</li>
                    <li>Expandable/collapsible event details</li>
                    <li>Location details with Google Maps integration</li>
                    <li>Event flyer image support</li>
                    <li>Mobile-responsive design</li>
                </ul>
            </div>
        </div>

        <style>
            .wrap .card {
                max-width: 800px;
                padding: 20px;
                margin-top: 20px;
            }
            .wrap code {
                background: #f0f0f1;
                padding: 2px 6px;
                border-radius: 3px;
            }
            .wrap pre {
                background: #f6f7f7;
                padding: 15px;
                border: 1px solid #ddd;
                border-radius: 4px;
                overflow-x: auto;
            }
            .wrap pre code {
                background: none;
                padding: 0;
            }
            .wrap .widefat {
                margin: 15px 0;
            }
            .wrap .widefat td,
            .wrap .widefat th {
                padding: 12px;
            }
            .wrap h2 {
                margin-top: 0;
            }
            .wrap .ul-disc {
                list-style: disc;
                margin-left: 20px;
            }
        </style>
        <?php
    }
}