<?php

namespace BmltEnabled\Mayo;

use DateTime;
use DateInterval;

class Rest {
    public static function init() {
        add_action('rest_api_init', function () {
            register_rest_route('event-manager/v1', '/submit-event', [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'submit_event'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('event-manager/v1', '/events', [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'get_events'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('event-manager/v1', '/settings', [
                [
                    'methods' => 'GET',
                    'callback' => [__CLASS__, 'get_settings'],
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods' => 'POST',
                    'callback' => [__CLASS__, 'update_settings'],
                    'permission_callback' => '__return_true',
                ],
            ]);

            register_rest_route('event-manager/v1', '/event/(?P<slug>[a-zA-Z0-9-]+)', [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'get_event_details'],
                'permission_callback' => '__return_true', // Adjust permissions as needed
            ]);
        });
    }

    public static function submit_event($request) {
        $params = $request->get_params();
        
        // Create the post
        $post_data = [
            'post_title'   => sanitize_text_field($params['event_name']),
            'post_content' => sanitize_textarea_field($params['description'] ?? ''),
            'post_status'  => 'pending',
            'post_type'    => 'mayo_event'
        ];
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $post_id->get_error_message()
            ], 400);
        }

        // Handle file upload
        if (!empty($_FILES['flyer'])) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            
            $attachment_id = media_handle_upload('flyer', $post_id);
            
            if (!is_wp_error($attachment_id)) {
                set_post_thumbnail($post_id, $attachment_id);
            }
        }

        // Add event metadata
        add_post_meta($post_id, 'event_type', sanitize_text_field($params['event_type']));
        add_post_meta($post_id, 'event_start_date', sanitize_text_field($params['event_start_date']));
        add_post_meta($post_id, 'event_end_date', sanitize_text_field($params['event_end_date']));
        add_post_meta($post_id, 'event_start_time', sanitize_text_field($params['event_start_time']));
        add_post_meta($post_id, 'event_end_time', sanitize_text_field($params['event_end_time']));
        add_post_meta($post_id, 'timezone', sanitize_text_field($params['timezone']));
        add_post_meta($post_id, 'service_body', sanitize_text_field($params['service_body']));

        // Add location metadata
        if (!empty($params['location_name'])) {
            add_post_meta($post_id, 'location_name', sanitize_text_field($params['location_name']));
        }
        if (!empty($params['location_address'])) {
            add_post_meta($post_id, 'location_address', sanitize_text_field($params['location_address']));
        }
        if (!empty($params['location_details'])) {
            add_post_meta($post_id, 'location_details', sanitize_text_field($params['location_details']));
        }

        // Handle categories and tags
        if (!empty($params['categories'])) {
            wp_set_post_categories($post_id, $params['categories']);
        }
        if (!empty($params['tags'])) {
            wp_set_post_tags($post_id, $params['tags']);
        }

        return new \WP_REST_Response([
            'success' => true,
            'post_id' => $post_id
        ], 200);
    }

    public static function get_events() {
        $is_archive = isset($_GET['archive']) && $_GET['archive'] === 'true';
        
        $posts = get_posts([
            'post_type' => 'mayo_event',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        $events = [];
        foreach ($posts as $post) {
            try {
                $recurring_pattern = get_post_meta($post->ID, 'recurring_pattern', true);
                
                if (!$is_archive && $recurring_pattern && $recurring_pattern['type'] !== 'none') {
                    $recurring_events = self::generate_recurring_events($post, $recurring_pattern);
                    $events = array_merge($events, $recurring_events);
                } else {
                    $events[] = self::format_event($post);
                }
            } catch (\Exception $e) {
                error_log('Error formatting event: ' . $e->getMessage());
                throw $e;
            }
        }

        return new \WP_REST_Response($events);
    }

    private static function generate_recurring_events($post, $pattern) {
        $weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $events = [];
        $start = new DateTime(get_post_meta($post->ID, 'event_start_date', true));
        $end = !empty($pattern['endDate']) ? new DateTime($pattern['endDate']) : (clone $start)->modify('+1 year');
        
        if ($pattern['type'] === 'monthly') {
            $current = clone $start;
            while ($current <= $end) {
                if ($pattern['monthlyType'] === 'date') {
                    // Specific date of month
                    $day = (int)$pattern['monthlyDate'];
                    $current->setDate($current->format('Y'), $current->format('m'), $day);
                } else {
                    // Specific weekday (e.g., 3rd Thursday)
                    list($week, $weekday) = explode(',', $pattern['monthlyWeekday']);
                    $week = (int)$week;
                    $weekday = (int)$weekday;
                    
                    // Calculate the date
                    $current->modify('first day of this month');
                    if ($week > 0) {
                        // First, second, third, fourth, fifth
                        $current->modify('+' . ($week - 1) . ' weeks');
                        $current->modify('next ' . $weekdays[$weekday]);
                    } else {
                        // Last occurrence
                        $current->modify('last ' . $weekdays[$weekday] . ' of this month');
                    }
                }
                
                if ($current >= $start && $current <= $end) {
                    $events[] = self::format_recurring_event($post, $current);
                }
                
                // Move to next month
                $current->modify('first day of next month');
            }
        } else {
            $interval = new DateInterval('P' . $pattern['interval'] . 
                ($pattern['type'] === 'daily' ? 'D' : 
                ($pattern['type'] === 'weekly' ? 'W' : 'M')));
            
            $current = clone $start;
            
            while ($current <= $end) {
                if ($pattern['type'] === 'weekly' && !empty($pattern['weekdays'])) {
                    // For weekly pattern, check if current day is in selected weekdays
                    if (in_array($current->format('w'), $pattern['weekdays'])) {
                        $events[] = self::format_recurring_event($post, $current);
                    }
                } else {
                    $events[] = self::format_recurring_event($post, $current);
                }
                
                $current->add($interval);
            }
        }
        
        return $events;
    }

    private static function format_recurring_event($post, $date) {
        $event = self::format_event($post);
        $event['meta']['event_start_date'] = $date->format('Y-m-d');
        $event['recurring'] = true;
        return $event;
    }

    private static function format_event($post) {
        try {
            $recurring_pattern = get_post_meta($post->ID, 'recurring_pattern', true);
            return [
                'id' => $post->ID,
                'title' => ['rendered' => $post->post_title],
                'featured_image' => get_the_post_thumbnail_url($post->ID, 'full'),
                'content' => ['rendered' => apply_filters('the_content', $post->post_content)],
                'link' => get_permalink($post->ID),
                'categories' => wp_get_post_categories($post->ID, ['fields' => 'all']),
                'tags' => wp_get_post_tags($post->ID, ['fields' => 'all']),
                'meta' => [
                    'event_type' => get_post_meta($post->ID, 'event_type', true),
                    'event_start_date' => get_post_meta($post->ID, 'event_start_date', true),
                    'event_end_date' => get_post_meta($post->ID, 'event_end_date', true),
                    'event_start_time' => get_post_meta($post->ID, 'event_start_time', true),
                    'event_end_time' => get_post_meta($post->ID, 'event_end_time', true),
                    'timezone' => get_post_meta($post->ID, 'timezone', true),
                    'location_name' => get_post_meta($post->ID, 'location_name', true),
                    'location_address' => get_post_meta($post->ID, 'location_address', true),
                    'location_details' => get_post_meta($post->ID, 'location_details', true),
                    'recurring_pattern' => $recurring_pattern ?: ['type' => 'none'],
                    'service_body' => get_post_meta($post->ID, 'service_body', true),
                ],
                'recurring' => false
            ];
        } catch (\Exception $e) {
            error_log('Error formatting event: ' . $e->getMessage());
            throw $e;
        }
    }

    public static function get_settings() {
        $settings = get_option('mayo_settings', []);
        return new \WP_REST_Response($settings);
    }

    public static function update_settings($request) {
        $params = $request->get_params();
        $settings = [];

        if (isset($params['bmlt_root_server'])) {
            $settings['bmlt_root_server'] = esc_url_raw(trim($params['bmlt_root_server']));
        }

        update_option('mayo_settings', $settings);
        return new \WP_REST_Response($settings);
    }

    public static function get_event_details($request) {
        $slug = $request['slug'];
        $query = new \WP_Query([
            'post_type' => 'mayo_event',
            'name' => $slug,
            'posts_per_page' => 1,
        ]);

        if ($query->have_posts()) {
            $query->the_post();
            $event = [
                'id' => get_the_ID(),
                'title' => ['rendered' => get_the_title()],
                'content' => ['rendered' => apply_filters('the_content', get_the_content())],
                'featured_image' => get_the_post_thumbnail_url(get_the_ID(), 'full'),
                'meta' => [
                    'event_type' => get_post_meta(get_the_ID(), 'event_type', true),
                    'event_start_date' => get_post_meta(get_the_ID(), 'event_start_date', true),
                    'event_end_date' => get_post_meta(get_the_ID(), 'event_end_date', true),
                    'event_start_time' => get_post_meta(get_the_ID(), 'event_start_time', true),
                    'event_end_time' => get_post_meta(get_the_ID(), 'event_end_time', true),
                    'timezone' => get_post_meta(get_the_ID(), 'timezone', true),
                    'location_name' => get_post_meta(get_the_ID(), 'location_name', true),
                    'location_address' => get_post_meta(get_the_ID(), 'location_address', true),
                    'location_details' => get_post_meta(get_the_ID(), 'location_details', true),
                    'recurring_pattern' => get_post_meta(get_the_ID(), 'recurring_pattern', true),
                    'service_body' => get_post_meta(get_the_ID(), 'service_body', true),
                ],
                'categories' => wp_get_post_categories(get_the_ID(), ['fields' => 'all']),
                'tags' => wp_get_post_tags(get_the_ID(), ['fields' => 'all']),
            ];
            wp_reset_postdata();
            return rest_ensure_response($event);
        }

        return new \WP_Error('no_event', 'Event not found', ['status' => 404]);
    }
}

Rest::init();