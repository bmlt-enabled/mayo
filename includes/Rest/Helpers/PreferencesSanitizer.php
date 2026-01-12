<?php

namespace BmltEnabled\Mayo\Rest\Helpers;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Helper class for sanitizing subscriber preferences
 *
 * Centralizes preference validation and sanitization for subscription endpoints.
 */
class PreferencesSanitizer {

    /**
     * Sanitize subscription preferences
     *
     * @param array|null $preferences Raw preferences from request
     * @return array|\WP_Error Sanitized preferences array or WP_Error if invalid
     */
    public static function sanitize($preferences) {
        // If null or not array, return error
        if ($preferences === null || !is_array($preferences)) {
            return new \WP_Error(
                'invalid_preferences',
                'Preferences must be an object.',
                ['status' => 400]
            );
        }

        $clean_preferences = [
            'categories' => [],
            'tags' => [],
            'service_bodies' => []
        ];

        if (isset($preferences['categories']) && is_array($preferences['categories'])) {
            $clean_preferences['categories'] = array_map('intval', $preferences['categories']);
        }

        if (isset($preferences['tags']) && is_array($preferences['tags'])) {
            $clean_preferences['tags'] = array_map('intval', $preferences['tags']);
        }

        if (isset($preferences['service_bodies']) && is_array($preferences['service_bodies'])) {
            $clean_preferences['service_bodies'] = array_map('sanitize_text_field', $preferences['service_bodies']);
        }

        return $clean_preferences;
    }

    /**
     * Validate that at least one preference is selected
     *
     * @param array $preferences Sanitized preferences array
     * @return bool True if at least one preference is selected
     */
    public static function has_selections($preferences) {
        if (!is_array($preferences)) {
            return false;
        }

        $total = count($preferences['categories'] ?? []) +
                 count($preferences['tags'] ?? []) +
                 count($preferences['service_bodies'] ?? []);

        return $total > 0;
    }

    /**
     * Sanitize and validate preferences, returning error response if invalid
     *
     * Combined method for common validation pattern.
     *
     * @param array|null $preferences Raw preferences from request
     * @return array|\WP_REST_Response Sanitized preferences or error response
     */
    public static function sanitize_and_validate($preferences) {
        $clean_preferences = self::sanitize($preferences);

        if (is_wp_error($clean_preferences)) {
            return new \WP_REST_Response([
                'success' => false,
                'code' => $clean_preferences->get_error_code(),
                'message' => $clean_preferences->get_error_message()
            ], $clean_preferences->get_error_data()['status'] ?? 400);
        }

        if (!self::has_selections($clean_preferences)) {
            return new \WP_REST_Response([
                'success' => false,
                'code' => 'no_preferences',
                'message' => 'Please select at least one category, tag, or service body.'
            ], 400);
        }

        return $clean_preferences;
    }
}
