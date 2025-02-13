<?php

namespace BmltEnabled\Mayo;

class PublicInterface {
    public static function init() {
        add_shortcode('mayo_event_form', [__CLASS__, 'render_event_form']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    public static function render_event_form() {
        return '<div id="mayo-event-form"></div>';
    }

    public static function enqueue_scripts() {
        if (has_shortcode(get_post()->post_content, 'mayo_event_form')) {
            wp_enqueue_script(
                'mayo-public',
                plugin_dir_url(__FILE__) . '../assets/js/dist/public.bundle.js',
                ['wp-element'],
                '1.0',
                true
            );

            wp_enqueue_style(
                'mayo-public',
                plugin_dir_url(__FILE__) . '../assets/css/public.css',
                [],
                '1.0'
            );
        }
    }
}
