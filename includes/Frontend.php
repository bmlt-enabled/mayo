<?php

namespace BmltEnabled\Mayo;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Frontend {
    public static function init() {
        add_shortcode('mayo_event_form', [__CLASS__, 'render_event_form']);
        add_shortcode('mayo_event_list', [__CLASS__, 'render_event_list']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }


    public static function render_event_form($atts = []) {
        $defaults = [
            'additional_required_fields' => ''
        ];
        $atts = shortcode_atts($defaults, $atts);
        
        // Create unique settings for this instance
        static $instance = 0;
        $instance++;
        
        $settings_key = "mayoEventFormSettings_$instance";
        wp_localize_script('mayo-public', $settings_key, [
            'additionalRequiredFields' => $atts['additional_required_fields']
        ]);
        
        return sprintf(
            '<div id="mayo-event-form" data-settings="%s"></div>',
            esc_attr($settings_key)
        );
    }

    public static function render_event_list($atts = []) {
        $defaults = [
            'widget' => false,
            'per_page' => 10,
            'show_pagination' => true,
            'time_format' => '12hour',
            'event_type' => '',
            'service_body' => '',
            'relation' => 'AND',
            'categories' => '',
            'tags' => '',
            'source_ids' => ''
        ];

        $settings = wp_parse_args($atts, $defaults);
        
        // Create a unique ID for this instance
        $settings_id = 'mayo_event_list_settings_' . wp_rand();
        
        // Add the settings to window object
        wp_localize_script('mayo-public', $settings_id, $settings);
        
        return sprintf(
            '<div id="mayo-event-list" class="mayo-event-list" data-settings="%s"></div>',
            esc_attr($settings_id)
        );
    }

    public static function enqueue_scripts() {
        $shortcode_on_widgets = self::is_shortcode_present_in_widgets('mayo_event_list');

        $post = get_post();
        if ($post && (
            has_shortcode($post->post_content, 'mayo_event_form') || 
            has_shortcode($post->post_content, 'mayo_event_list')
        ) || (is_post_type_archive($post->post_type) || is_singular('mayo_event')) || $shortcode_on_widgets) {
            wp_enqueue_script(
                'mayo-public',
                plugin_dir_url(__FILE__) . '../assets/js/dist/public.bundle.js',
                [
                    'wp-element',
                    'wp-components',
                    'wp-i18n',
                    'wp-api-fetch'
                ],
                '1.0',
                true
            );

            wp_enqueue_style('wp-components');
            wp_enqueue_style(
                'mayo-public',
                plugin_dir_url(__FILE__) . '../assets/css/public.css',
                ['wp-components'],
                '1.0'
            );
            wp_enqueue_style('dashicons');
        }

        wp_localize_script('mayo-public', 'mayoApiSettings', [
            'root' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest')
        ]);
    }

    private static function is_shortcode_present_in_widgets($shortcode) {
        global $wp_registered_sidebars, $wp_registered_widgets;

        // Loop through each sidebar
        foreach ($wp_registered_sidebars as $sidebar_id => $sidebar) {
            // Check if the sidebar is active
            if (is_active_sidebar($sidebar_id)) {
                // Get the widgets in this sidebar
                $sidebars_widgets = wp_get_sidebars_widgets();
                if (isset($sidebars_widgets[$sidebar_id])) {
                    foreach ($sidebars_widgets[$sidebar_id] as $widget_id) {
                        if (isset($wp_registered_widgets[$widget_id])) {
                            // Attempt to get the widget's content
                            $widget_instance = get_option('widget_' . $wp_registered_widgets[$widget_id]['callback'][0]->id_base);
                                
                            foreach ($widget_instance as $instance) {
                                if (is_array($instance) && isset($instance['content']) && has_shortcode($instance['content'], $shortcode)) {
                                    return true;
                                }
                            }
                        }
                    }
                }
            }
        }

        return false;
    }
}
