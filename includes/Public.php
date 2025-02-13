<?php

namespace BmltEnabled\Mayo;

class PublicInterface {
    public static function init() {
        add_shortcode('event_form', [__CLASS__, 'render_event_form']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    public static function render_event_form() {
        return '<div id="event-form"></div>';
    }

    public static function enqueue_scripts() {
        wp_enqueue_script(
            'event-manager-public',
            plugin_dir_url(__FILE__) . '../assets/js/dist/public.bundle.js',
            ['wp-element'],
            '1.0',
            true
        );
    }
}
