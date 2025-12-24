<?php

namespace BmltEnabled\Mayo;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Frontend {
    public static function init() {
        add_shortcode('mayo_event_form', [__CLASS__, 'render_event_form']);
        add_shortcode('mayo_event_list', [__CLASS__, 'render_event_list']);
        add_shortcode('mayo_announcement', [__CLASS__, 'render_announcement']);
        add_shortcode('mayo_subscribe', [__CLASS__, 'render_subscribe_form']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        
        // Register the script early
        add_action('init', function() {
            wp_register_script(
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
        });
    }


    public static function render_event_form($atts = []) {
        $defaults = [
            'additional_required_fields' => '',
            'categories' => '',
            'tags' => '',
            'default_service_bodies' => ''
        ];
        $atts = shortcode_atts($defaults, $atts);
        
        // Create unique settings for this instance
        static $instance = 0;
        $instance++;
        
        $settings_key = "mayoEventFormSettings_$instance";
        wp_localize_script('mayo-public', $settings_key, [
            'additionalRequiredFields' => $atts['additional_required_fields'],
            'defaultServiceBodies' => $atts['default_service_bodies']
        ]);
        
        return sprintf(
            '<div id="mayo-event-form" data-settings="%s" data-categories="%s" data-tags="%s"></div>',
            esc_attr($settings_key),
            esc_attr($atts['categories']),
            esc_attr($atts['tags'])
        );
    }

    public static function render_event_list($atts = [], $content = null, $tag = '') {
        static $instance = 0;
        $instance++;
        
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
            'infinite_scroll' => 'true',
            'autoexpand' => 'false',
            'categories' => '',  // Comma-separated category slugs
            'tags' => '',       // Comma-separated tag slugs
            'event_type' => '',  // Single event type (Service, Activity)
            'status' => 'publish',  // Single event status (publish, pending)
            'service_body' => '',  // Comma-separated service body IDs
            'source_ids' => '',  // Comma-separated source IDs
            'order' => 'ASC',  // Sort order: ASC (ascending, earliest first) or DESC (descending, latest first)
            'view' => 'list',  // Default view: 'list' or 'calendar'
        ];
        $atts = shortcode_atts($defaults, $atts);

        wp_enqueue_script('mayo-public');
        wp_enqueue_style('mayo-public');

        // Create unique settings for this instance
        $settings_key = "mayoEventSettings_$instance";
        wp_localize_script('mayo-public', $settings_key, [
            'timeFormat' => $atts['time_format'],
            'perPage' => intval($atts['per_page']),
            'infiniteScroll' => $atts['infinite_scroll'] === 'true',
            'autoexpand' => $atts['autoexpand'] === 'true',
            'categories' => $atts['categories'],
            'tags' => $atts['tags'],
            'eventType' => $atts['event_type'],
            'status' => $atts['status'],
            'serviceBody' => $atts['service_body'],
            'sourceIds' => $atts['source_ids'],
            'order' => strtoupper($atts['order']),
            'defaultView' => $atts['view'],
        ]);

        return sprintf(
            '<div id="mayo-event-list-%d" data-instance="%d"%s></div>',
            $instance,
            $instance,
            $is_widget ? ' class="mayo-widget-list"' : ''
        );
    }

    public static function render_announcement($atts = []) {
        static $instance = 0;
        $instance++;

        $defaults = [
            'mode' => 'banner',           // 'banner' or 'modal'
            'categories' => '',           // Comma-separated category slugs
            'tags' => '',                 // Comma-separated tag slugs
            'priority' => '',             // Filter by priority (low/normal/high/urgent)
            'show_linked_events' => 'false', // Show linked event titles
            'time_format' => '12hour',
            'background_color' => '',     // Custom background color (hex)
            'text_color' => '',           // Custom text color (hex)
        ];
        $atts = shortcode_atts($defaults, $atts);

        wp_enqueue_script('mayo-public');
        wp_enqueue_style('mayo-public');

        // Create unique settings for this instance
        $settings_key = "mayoAnnouncementSettings_$instance";
        wp_localize_script('mayo-public', $settings_key, [
            'mode' => $atts['mode'],
            'categories' => $atts['categories'],
            'tags' => $atts['tags'],
            'priority' => $atts['priority'],
            'showLinkedEvents' => $atts['show_linked_events'] === 'true',
            'timeFormat' => $atts['time_format'],
            'backgroundColor' => $atts['background_color'],
            'textColor' => $atts['text_color'],
        ]);

        return sprintf(
            '<div class="mayo-announcement-container" data-instance="%d"></div>',
            $instance
        );
    }

    public static function render_subscribe_form($atts = []) {
        static $instance = 0;
        $instance++;

        wp_enqueue_script('mayo-public');
        wp_enqueue_style('mayo-public');

        return sprintf(
            '<div class="mayo-subscribe-container" data-instance="%d"></div>',
            $instance
        );
    }

    public static function enqueue_scripts() {
        $shortcode_on_widgets = self::is_shortcode_present_in_widgets('mayo_event_list') ||
                                self::is_shortcode_present_in_widgets('mayo_announcement');

        $post = get_post();
        $should_enqueue = false;

        // Check if we should enqueue scripts
        if ($post && (
            has_shortcode($post->post_content, 'mayo_event_form') ||
            has_shortcode($post->post_content, 'mayo_event_list') ||
            has_shortcode($post->post_content, 'mayo_announcement') ||
            has_shortcode($post->post_content, 'mayo_subscribe')
        )) {
            $should_enqueue = true;
        } elseif (is_post_type_archive('mayo_event') || is_singular('mayo_event')) {
            $should_enqueue = true;
        } elseif ($shortcode_on_widgets) {
            $should_enqueue = true;
        }
        
        if ($should_enqueue) {
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
