<?php

namespace BmltEnabled\Mayo;

class Admin {
    public static function init() {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_action('init', [__CLASS__, 'register_taxonomies']);
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
        echo '<div id="mayo-admin"></div>';
    }

    public static function register_taxonomies() {
        // Register Event Categories
        register_taxonomy('mayo_event_category', 'mayo_event', [
            'labels' => [
                'name' => 'Event Categories',
                'singular_name' => 'Event Category',
                'search_items' => 'Search Categories',
                'all_items' => 'All Categories',
                'edit_item' => 'Edit Category',
                'update_item' => 'Update Category',
                'add_new_item' => 'Add New Category',
                'new_item_name' => 'New Category Name',
                'menu_name' => 'Categories',
            ],
            'hierarchical' => true, // Like WordPress categories
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'event-category'],
            'show_in_rest' => true, // Enable in Gutenberg
        ]);

        // Register Event Tags
        register_taxonomy('mayo_event_tag', 'mayo_event', [
            'labels' => [
                'name' => 'Event Tags',
                'singular_name' => 'Event Tag',
                'search_items' => 'Search Tags',
                'all_items' => 'All Tags',
                'edit_item' => 'Edit Tag',
                'update_item' => 'Update Tag',
                'add_new_item' => 'Add New Tag',
                'new_item_name' => 'New Tag Name',
                'menu_name' => 'Tags',
            ],
            'hierarchical' => false, // Like WordPress tags
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'event-tag'],
            'show_in_rest' => true, // Enable in Gutenberg
        ]);
    }
}