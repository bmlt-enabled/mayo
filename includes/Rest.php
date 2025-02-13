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
        });
    }

    public static function submit_event($request) {
        $params = $request->get_json_params();
        
        // Create the post
        $post_data = [
            'post_title'   => sanitize_text_field($params['event_name']),
            'post_content' => sanitize_textarea_field($params['description'] ?? ''),
            'post_status'  => 'pending', // Requires admin approval
            'post_type'    => 'mayo_event'
        ];
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $post_id->get_error_message()
            ], 400);
        }

        // Add event metadata
        add_post_meta($post_id, 'event_type', sanitize_text_field($params['event_type']));
        add_post_meta($post_id, 'event_date', sanitize_text_field($params['event_date']));
        add_post_meta($post_id, 'event_start_time', sanitize_text_field($params['event_start_time']));
        add_post_meta($post_id, 'event_end_time', sanitize_text_field($params['event_end_time']));
        if (!empty($params['recurring_schedule'])) {
            add_post_meta($post_id, 'recurring_schedule', sanitize_text_field($params['recurring_schedule']));
        }
        if (!empty($params['flyer_url'])) {
            add_post_meta($post_id, 'flyer_url', esc_url_raw($params['flyer_url']));
        }

        return new \WP_REST_Response([
            'success' => true,
            'post_id' => $post_id
        ], 200);
    }
}