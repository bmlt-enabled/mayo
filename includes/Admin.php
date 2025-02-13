<?php

namespace BmltEnabled\Mayo;

class Admin {
    public static function init() {
        add_action('init', [__CLASS__, 'register_post_type']);
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
            'supports' => ['title', 'editor', 'thumbnail'],
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
    }

    public static function render_admin_page() {
        echo '<div id="mayo-admin"></div>';
    }

    public static function enqueue_scripts() {
        wp_enqueue_script(
            'mayo-admin',
            plugin_dir_url(__FILE__) . '../assets/js/dist/admin.bundle.js',
            ['wp-element'],
            '1.0',
            true
        );
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
}