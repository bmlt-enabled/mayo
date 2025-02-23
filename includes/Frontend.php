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

    public static function render_event_list($atts = [], $content = null, $tag = '') {
        // Get the current filter being applied
        $current_filter = current_filter();
        
        // Check if we're in a widget context
        $is_widget = (
            $current_filter === 'widget_text' || 
            $current_filter === 'widget_text_content' ||
            $current_filter === 'widget_block_content' ||
            $current_filter === 'widget_custom_html_content' ||
            doing_filter('dynamic_sidebar')
        );

        $defaults = [
            'time_format' => '12hour', // or '24hour'
            'per_page' => 10,
            'show_pagination' => 'true',
            'categories' => '',  // Comma-separated category slugs
            'tags' => '',       // Comma-separated tag slugs
            'event_type' => '',  // Single event type (Service, Activity)
            'status' => 'publish',  // Single event status (publish, pending)
            'service_body' => '',  // Comma-separated service body IDs
        ];
        $atts = shortcode_atts($defaults, $atts);
        
        wp_enqueue_script('mayo-public');
        wp_enqueue_style('mayo-public');

        // Pass attributes to JavaScript
        wp_localize_script('mayo-public', 'mayoEventSettings', [
            'timeFormat' => $atts['time_format'],
            'perPage' => intval($atts['per_page']),
            'showPagination' => $atts['show_pagination'] === 'true',
            'categories' => $atts['categories'],
            'tags' => $atts['tags'],
            'eventType' => $atts['event_type'],
            'status' => $atts['status'],
            'serviceBody' => $atts['service_body'],
        ]);

        return '<div id="mayo-event-list"' . ($is_widget ? ' class="mayo-widget-list"' : '') . '></div>';  
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
