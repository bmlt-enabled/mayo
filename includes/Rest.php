<?php
namespace BmltEnabled\Mayo;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use DateTime;
use DateInterval;

class Rest {
    public static function init() {
        add_action('rest_api_init', function () {
            register_rest_route('event-manager/v1', '/submit-event', [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'bmltenabled_mayo_submit_event'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('event-manager/v1', '/events', [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'bmltenabled_mayo_get_events'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('event-manager/v1', '/settings', [
                [
                    'methods' => 'GET',
                    'callback' => [__CLASS__, 'bmltenabled_mayo_get_settings'],
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods' => 'POST',
                    'callback' => [__CLASS__, 'bmltenabled_mayo_update_settings'],
                    'permission_callback' => function() {
                        return current_user_can( 'manage_options' );
                    }
                ],
            ]);

            register_rest_route('event-manager/v1', '/event/(?P<slug>[a-zA-Z0-9-]+)', [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'bmltenabled_mayo_get_event_details'],
                'permission_callback' => '__return_true', // Adjust permissions as needed
            ]);
        });
    }

    public static function bmltenabled_mayo_submit_event($request) {
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
        $nonce = wp_create_nonce('wp_rest');
        if (!empty($_FILES['flyer']) && wp_verify_nonce($nonce, 'wp_rest')) {
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
        add_post_meta($post_id, 'email', sanitize_email($params['email']));

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
            $categories_array = explode(',', $params['categories']);
            wp_set_post_categories($post_id, $categories_array);
        }
        if (!empty($params['tags'])) {
            wp_set_post_tags($post_id, $params['tags']);
        }

        // Send email notification
        self::send_event_submission_email($post_id, $params);

        return new \WP_REST_Response([
            'success' => true,
            'post_id' => $post_id
        ], 200);
    }

    private static function send_event_submission_email($post_id, $params) {
        $to = get_option('admin_email'); // Send to the site admin email
        $subject = 'New Event Submission: ' . sanitize_text_field($params['event_name']);
        $message = sprintf(
            "A new event has been submitted:\n\nEvent Name: %s\nEvent Type: %s\nStart Date: %s\nEnd Date: %s\n\nView the event: %s",
            sanitize_text_field($params['event_name']),
            sanitize_text_field($params['event_type']),
            sanitize_text_field($params['event_start_date']),
            sanitize_text_field($params['event_end_date']),
            admin_url('post.php?post=' . $post_id . '&action=edit')
        );

        wp_mail($to, $subject, $message);
    }

    public static function bmltenabled_mayo_get_events($request) {
        $is_archive = false;
    
        if (isset($_GET['archive'])) {
            $nonce = wp_create_nonce('wp_rest');
            if (wp_verify_nonce($nonce, 'wp_rest')) {
                $archive = sanitize_text_field(wp_unslash($_GET['archive']));
                if ($archive === 'true') {
                    $is_archive = true;
                }
            }
        }

        $status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : 'publish';
        $eventType = isset($_GET['event_type']) ? sanitize_text_field(wp_unslash($_GET['event_type'])) : '';
        $serviceBody = isset($_GET['service_body']) ? sanitize_text_field(wp_unslash($_GET['service_body'])) : '';
        $relation = isset($_GET['relation']) ? sanitize_text_field(wp_unslash($_GET['relation'])) : 'AND';
        $categories = isset($_GET['categories']) ? sanitize_text_field(wp_unslash($_GET['categories'])) : '';
        $tags = isset($_GET['tags']) ? sanitize_text_field(wp_unslash($_GET['tags'])) : '';

        $meta_keys = [
            'event_type' => $eventType,
            'service_body' => $serviceBody
        ];

        $meta_query = [];

        foreach ($meta_keys as $key => $value) {
            if ($value != '') {
                $meta_query[] = [
                    'key' => $key,
                    'value' => $value,
                    'compare' => '='
                ];
            }
        }

        if (count($meta_query) > 0) {
            $meta_query['relation'] = $relation;
        }

        $posts = get_posts([
            'post_type' => 'mayo_event',
            'posts_per_page' => -1,
            'post_status' => $status,
            'meta_query' => $meta_query,
            'category_name' => $categories,
            'tag' => $tags
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
                    'recurring_pattern' => get_post_meta($post->ID, 'recurring_pattern', true),
                    'service_body' => get_post_meta($post->ID, 'service_body', true),
                ],
                'recurring' => false
            ];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public static function bmltenabled_mayo_get_settings() {
        $settings = get_option('mayo_settings', []);
        return new \WP_REST_Response($settings);
    }

    public static function bmltenabled_mayo_update_settings($request) {
        // Check permissions again (this is already checked by permission_callback but adding for security)
        if (!current_user_can('manage_options')) {
            return new \WP_Error(
                'rest_forbidden',
                __('Sorry, you are not allowed to update settings.'),
                ['status' => 401]
            );
        }
        
        $params = $request->get_params();
        $settings = [];

        // Accept either bmlt_root_server or rootserver parameter for backward compatibility
        if (isset($params['bmlt_root_server'])) {
            $settings['bmlt_root_server'] = esc_url_raw(trim($params['bmlt_root_server']));
        } elseif (isset($params['rootserver'])) {
            $settings['bmlt_root_server'] = esc_url_raw(trim($params['rootserver']));
        }

        update_option('mayo_settings', $settings);
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Settings updated successfully',
            'settings' => $settings
        ]);
    }

    public static function bmltenabled_mayo_get_event_details($request) {
        $slug = $request['slug'];
        $query = new \WP_Query([
            'post_type' => 'mayo_event',
            'name' => $slug,
            'posts_per_page' => 1,
        ]);

        if ($query->have_posts()) {
            $query->the_post();
            $event = self::format_event(get_post());
            wp_reset_postdata();
            return rest_ensure_response($event);
        }

        return new \WP_Error('no_event', 'Event not found', ['status' => 404]);
    }
}

Rest::init();