<?php

namespace BmltEnabled\Mayo;

class REST {
    public static function init() {
        add_action('rest_api_init', function () {
            register_rest_route('event-manager/v1', '/submit-event', [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'submit_event'],
            ]);
        });
    }

    public static function submit_event($request) {
        $params = $request->get_json_params();
        // Process event submission
        return new \WP_REST_Response(['success' => true], 200);
    }
}