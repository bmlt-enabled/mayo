<?php

namespace BmltEnabled\Mayo;

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
        if (!empty($params['recurring_schedule'])) {
            add_post_meta($post_id, 'recurring_schedule', sanitize_text_field($params['recurring_schedule']));
        }

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
            wp_set_post_terms($post_id, $params['categories'], 'mayo_event_category');
        }
        if (!empty($params['tags'])) {
            wp_set_post_terms($post_id, $params['tags'], 'mayo_event_tag');
        }

        return new \WP_REST_Response([
            'success' => true,
            'post_id' => $post_id
        ], 200);
    }

    public static function get_events() {
        $posts = get_posts([
            'post_type' => 'mayo_event',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);

        $events = [];
        foreach ($posts as $post) {
            $event = [
                'id' => $post->ID,
                'title' => ['rendered' => $post->post_title],
                'content' => ['rendered' => apply_filters('the_content', $post->post_content)],
                'link' => get_permalink($post->ID),
                'meta' => [
                    'event_type' => get_post_meta($post->ID, 'event_type', true),
                    'event_start_date' => get_post_meta($post->ID, 'event_start_date', true),
                    'event_end_date' => get_post_meta($post->ID, 'event_end_date', true),
                    'event_start_time' => get_post_meta($post->ID, 'event_start_time', true),
                    'event_end_time' => get_post_meta($post->ID, 'event_end_time', true),
                    'flyer_url' => get_post_meta($post->ID, 'flyer_url', true),
                    'recurring_schedule' => get_post_meta($post->ID, 'recurring_schedule', true),
                    'location_name' => get_post_meta($post->ID, 'location_name', true),
                    'location_address' => get_post_meta($post->ID, 'location_address', true),
                    'location_details' => get_post_meta($post->ID, 'location_details', true),
                ],
                'categories' => wp_get_post_terms($post->ID, 'mayo_event_category', ['fields' => 'all']),
                'tags' => wp_get_post_terms($post->ID, 'mayo_event_tag', ['fields' => 'all'])
            ];
            $events[] = $event;
        }

        return new \WP_REST_Response($events);
    }
}