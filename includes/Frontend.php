<?php

namespace BmltEnabled\Mayo;

class Frontend {
    public static function init() {
        add_shortcode('mayo_event_form', [__CLASS__, 'render_event_form']);
        add_shortcode('mayo_event_list', [__CLASS__, 'render_event_list']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    public static function render_event_form() {
        return '<div id="mayo-event-form"></div>';
    }

    public static function render_event_list($atts = []) {
        $defaults = [
            'time_format' => '12hour', // or '24hour'
            'per_page' => 10,
            'show_pagination' => 'true',
            'categories' => '',  // Comma-separated category slugs
            'tags' => '',       // Comma-separated tag slugs
            'event_type' => ''  // Single event type (Service, Activity)
        ];
        $atts = shortcode_atts($defaults, $atts);
        
        wp_enqueue_script('mayo-public');
        wp_enqueue_style('mayo-public');

        // Pass attributes to JavaScript
        wp_localize_script('mayo-public', 'mayoEventSettings', [
            'timeFormat' => $atts['time_format'],
            'perPage' => intval($atts['per_page']),
            'showPagination' => $atts['show_pagination'] === 'true',
            'categories' => array_filter(explode(',', $atts['categories'])),
            'tags' => array_filter(explode(',', $atts['tags'])),
            'eventType' => $atts['event_type']
        ]);

        return sprintf(
            '<div id="mayo-event-list" data-time-format="%s"></div>',
            esc_attr($atts['time_format'])
        );
    }

    public static function enqueue_scripts() {
        $post = get_post();
        if ($post && (
            has_shortcode($post->post_content, 'mayo_event_form') || 
            has_shortcode($post->post_content, 'mayo_event_list')
        ) || (is_post_type_archive($post->post_type) || is_singular('mayo_event'))) {
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
