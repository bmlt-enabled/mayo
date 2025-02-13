<?php

namespace BmltEnabled\Mayo;

class Admin {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    public static function add_menu() {
        add_menu_page(
            'Mayo',
            'Mayo',
            'manage_options',
            'mayo',
            [__CLASS__, 'render_admin_page']
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
}