<?php

namespace BmltEnabled\Mayo;

if (!defined('ABSPATH')) exit;

class ExternalEvents {
    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function register_settings() {
        register_setting('mayo_settings', 'mayo_external_sources', [
            'type' => 'array',
            'default' => [],
            'sanitize_callback' => [__CLASS__, 'sanitize_sources']
        ]);
    }

    public static function sanitize_sources($sources) {
        if (!is_array($sources)) return [];
        
        return array_map(function($source) {
            return [
                'url' => esc_url_raw($source['url']),
                'event_type' => sanitize_text_field($source['event_type'] ?? ''),
                'service_body' => sanitize_text_field($source['service_body'] ?? ''),
                'categories' => sanitize_text_field($source['categories'] ?? ''),
                'tags' => sanitize_text_field($source['tags'] ?? ''),
                'enabled' => (bool) ($source['enabled'] ?? false)
            ];
        }, $sources);
    }

    public static function fetch_external_events() {
        $sources = get_option('mayo_external_sources', []);
        $external_events = [];

        foreach ($sources as $source) {
            if (!$source['enabled']) continue;

            // Build query parameters
            $params = [];
            if (!empty($source['event_type'])) $params['event_type'] = $source['event_type'];
            if (!empty($source['service_body'])) $params['service_body'] = $source['service_body'];
            if (!empty($source['categories'])) $params['categories'] = $source['categories'];
            if (!empty($source['tags'])) $params['tags'] = $source['tags'];

            // Build URL with parameters
            $url = add_query_arg($params, trailingslashit($source['url']) . 'wp-json/event-manager/v1/events');

            try {
                $response = wp_remote_get($url, [
                    'timeout' => 15,
                    'sslverify' => true
                ]);

                if (is_wp_error($response)) {
                    error_log('External Events Error: ' . $response->get_error_message());
                    continue;
                }

                $body = wp_remote_retrieve_body($response);
                $events = json_decode($body, true);

                if (!is_array($events)) continue;

                // Add source information to each event
                foreach ($events as &$event) {
                    $event['external_source'] = parse_url($source['url'], PHP_URL_HOST);
                }

                $external_events = array_merge($external_events, $events);
            } catch (\Exception $e) {
                error_log('External Events Error: ' . $e->getMessage());
                continue;
            }
        }

        return $external_events;
    }
}

ExternalEvents::init(); 