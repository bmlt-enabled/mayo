<?php

namespace BmltEnabled\Mayo;

use DateTime;
use DateInterval;

class REST {
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
                add_post_meta($post_id, 'flyer_id', $attachment_id);
                add_post_meta($post_id, 'flyer_url', wp_get_attachment_url($attachment_id));
            }
        }

        // Add event metadata
        add_post_meta($post_id, 'event_type', sanitize_text_field($params['event_type']));
        add_post_meta($post_id, 'event_start_date', sanitize_text_field($params['event_start_date']));
        add_post_meta($post_id, 'event_end_date', sanitize_text_field($params['event_end_date']));
        add_post_meta($post_id, 'event_start_time', sanitize_text_field($params['event_start_time']));
        add_post_meta($post_id, 'event_end_time', sanitize_text_field($params['event_end_time']));
        add_post_meta($post_id, 'timezone', sanitize_text_field($params['timezone']));

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
        try {
            $posts = get_posts([
                'post_type' => 'mayo_event',
                'posts_per_page' => -1,
                'post_status' => 'publish',
            ]);

            $events = [];
            foreach ($posts as $post) {
                $recurring_pattern = get_post_meta($post->ID, 'recurring_pattern', true);
                $start_date = get_post_meta($post->ID, 'event_start_date', true);
                
                if ($recurring_pattern && $recurring_pattern['type'] !== 'none') {
                    $recurring_events = self::generate_recurring_events($post, $recurring_pattern, $start_date);
                    $events = array_merge($events, $recurring_events);
                } else {
                    $events[] = self::format_event($post);
                }
            }

            return new \WP_REST_Response($events);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'error' => $e->getMessage(),
                'details' => $e->getTraceAsString()
            ], 500);
        }
    }

    private static function generate_recurring_events($post, $pattern, $start_date) {
        $weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $events = [];
        $start = new DateTime($start_date);
        $end = $pattern['endDate'] ? new DateTime($pattern['endDate']) : (new DateTime($start_date))->modify('+1 year');
        
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
            return [
                'id' => $post->ID,
                'title' => ['rendered' => $post->post_title],
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
                    'flyer_url' => get_post_meta($post->ID, 'flyer_url', true),
                    'location_name' => get_post_meta($post->ID, 'location_name', true),
                    'location_address' => get_post_meta($post->ID, 'location_address', true),
                    'location_details' => get_post_meta($post->ID, 'location_details', true),
                ],
                'recurring' => false
            ];
        } catch (\Exception $e) {
            error_log('Error formatting event: ' . $e->getMessage());
            throw $e;
        }
    }
}