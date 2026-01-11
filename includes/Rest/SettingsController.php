<?php

namespace BmltEnabled\Mayo\Rest;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * REST API controller for plugin settings
 */
class SettingsController {

    /**
     * Register REST API routes for settings
     */
    public static function register_routes() {
        register_rest_route('event-manager/v1', '/settings', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_settings'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('event-manager/v1', '/settings', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'update_settings'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }

    /**
     * Get plugin settings
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function get_settings($request) {
        $settings = get_option('mayo_settings', []);
        $external_sources = get_option('mayo_external_sources', []);

        return new \WP_REST_Response([
            'bmlt_root_server' => $settings['bmlt_root_server'] ?? '',
            'notification_email' => $settings['notification_email'] ?? '',
            'default_service_bodies' => $settings['default_service_bodies'] ?? '',
            'external_sources' => $external_sources,
            'subscription_categories' => $settings['subscription_categories'] ?? [],
            'subscription_tags' => $settings['subscription_tags'] ?? [],
            'subscription_service_bodies' => $settings['subscription_service_bodies'] ?? [],
            'subscription_new_option_behavior' => $settings['subscription_new_option_behavior'] ?? 'opt_in'
        ]);
    }

    /**
     * Update plugin settings
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public static function update_settings($request) {
        if (!current_user_can('manage_options')) {
            return new \WP_Error(
                'rest_forbidden',
                __('Sorry, you are not allowed to update settings.'),
                ['status' => 401]
            );
        }

        $params = $request->get_params();
        $settings = get_option('mayo_settings', []);

        // Update BMLT root server
        if (isset($params['bmlt_root_server'])) {
            $settings['bmlt_root_server'] = sanitize_text_field($params['bmlt_root_server']);
        }

        // Update notification email
        if (isset($params['notification_email'])) {
            // Sanitize the email list
            $email_list = sanitize_text_field($params['notification_email']);

            // Validate each email in the list
            if (!empty($email_list)) {
                $emails = preg_split('/[,;]/', $email_list);
                $valid_emails = [];

                foreach ($emails as $email) {
                    $email = trim($email);
                    if (is_email($email)) {
                        $valid_emails[] = $email;
                    }
                }

                // Join valid emails with commas
                $settings['notification_email'] = implode(', ', $valid_emails);
            } else {
                $settings['notification_email'] = '';
            }
        }

        // Update default service bodies
        if (isset($params['default_service_bodies'])) {
            $service_bodies = sanitize_text_field($params['default_service_bodies']);
            $settings['default_service_bodies'] = $service_bodies;
        }

        // Update external sources
        if (isset($params['external_sources']) && is_array($params['external_sources'])) {
            $external_sources = self::sanitize_sources($params['external_sources']);
            update_option('mayo_external_sources', $external_sources);
        }

        // Update subscription settings
        if (isset($params['subscription_categories']) && is_array($params['subscription_categories'])) {
            $settings['subscription_categories'] = array_map('intval', $params['subscription_categories']);
        }

        if (isset($params['subscription_tags']) && is_array($params['subscription_tags'])) {
            $settings['subscription_tags'] = array_map('intval', $params['subscription_tags']);
        }

        if (isset($params['subscription_service_bodies']) && is_array($params['subscription_service_bodies'])) {
            $settings['subscription_service_bodies'] = array_map('sanitize_text_field', $params['subscription_service_bodies']);
        }

        if (isset($params['subscription_new_option_behavior'])) {
            $behavior = sanitize_text_field($params['subscription_new_option_behavior']);
            if (in_array($behavior, ['opt_in', 'auto_include'])) {
                $settings['subscription_new_option_behavior'] = $behavior;
            }
        }

        update_option('mayo_settings', $settings);

        return new \WP_REST_Response([
            'success' => true,
            'settings' => [
                'bmlt_root_server' => $settings['bmlt_root_server'] ?? '',
                'notification_email' => $settings['notification_email'] ?? '',
                'default_service_bodies' => $settings['default_service_bodies'] ?? '',
                'external_sources' => get_option('mayo_external_sources', []),
                'subscription_categories' => $settings['subscription_categories'] ?? [],
                'subscription_tags' => $settings['subscription_tags'] ?? [],
                'subscription_service_bodies' => $settings['subscription_service_bodies'] ?? [],
                'subscription_new_option_behavior' => $settings['subscription_new_option_behavior'] ?? 'opt_in'
            ]
        ]);
    }

    /**
     * Generate a readable ID for external sources
     *
     * @return string A readable ID
     */
    private static function generate_readable_id() {
        $prefix = 'source_';
        $random = substr(md5(uniqid(rand(), true)), 0, 8);
        return $prefix . $random;
    }

    /**
     * Sanitize external sources
     *
     * @param array $sources Array of external sources
     * @return array Sanitized external sources
     */
    private static function sanitize_sources($sources) {
        $sanitized_sources = [];

        foreach ($sources as $source) {
            if (empty($source['url'])) continue;

            // Keep existing ID or generate new readable one
            $id = !empty($source['id']) ? sanitize_text_field($source['id']) : self::generate_readable_id();

            $sanitized_sources[] = [
                'id' => $id,
                'url' => esc_url_raw(trim($source['url'])),
                'name' => sanitize_text_field($source['name'] ?? ''),
                'event_type' => sanitize_text_field($source['event_type'] ?? ''),
                'service_body' => sanitize_text_field($source['service_body'] ?? ''),
                'categories' => sanitize_text_field($source['categories'] ?? ''),
                'tags' => sanitize_text_field($source['tags'] ?? ''),
                'enabled' => (bool) ($source['enabled'] ?? false)
            ];
        }

        return $sanitized_sources;
    }
}
