<?php

namespace BmltEnabled\Mayo;

class PublicInterface {
    public static function init() {
        add_shortcode('mayo_event_form', [__CLASS__, 'render_event_form']);
        add_shortcode('mayo_event_list', [__CLASS__, 'render_event_list']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    public static function render_event_form() {
        return '<div id="mayo-event-form"></div>';
    }

    public static function render_event_list() {
        return '<div id="mayo-event-list"></div>';
    }

    public static function enqueue_scripts() {
        $post = get_post();
        if ($post && (
            has_shortcode($post->post_content, 'mayo_event_form') || 
            has_shortcode($post->post_content, 'mayo_event_list')
        )) {
            wp_enqueue_script(
                'mayo-public',
                plugin_dir_url(__FILE__) . '../assets/js/dist/public.bundle.js',
                [
                    'wp-element',
                    'wp-components',
                    'wp-i18n'
                ],
                '1.0',
                true
            );

            // Enqueue WordPress components styles
            wp_enqueue_style(
                'wp-components'
            );

            wp_enqueue_style(
                'mayo-public',
                plugin_dir_url(__FILE__) . '../assets/css/public.css',
                ['wp-components'],
                '1.0'
            );
        }
    }
}
