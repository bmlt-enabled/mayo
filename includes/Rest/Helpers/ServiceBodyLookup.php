<?php

namespace BmltEnabled\Mayo\Rest\Helpers;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Helper class for BMLT service body lookups
 *
 * Centralizes service body name resolution from BMLT root server.
 */
class ServiceBodyLookup {

    /**
     * Cache for service bodies to avoid repeated API calls
     *
     * @var array|null
     */
    private static $cache = null;

    /**
     * Get service body name by ID from BMLT
     *
     * @param string $service_body_id Service body ID
     * @return string Service body name or 'Unknown'
     */
    public static function get_name($service_body_id) {
        if ($service_body_id === '0') {
            return 'Unaffiliated';
        }

        if (empty($service_body_id)) {
            return 'Unknown';
        }

        $all_bodies = self::get_all();

        foreach ($all_bodies as $body) {
            if ($body['id'] == $service_body_id) {
                return $body['name'];
            }
        }

        return 'Unknown';
    }

    /**
     * Get all service bodies from BMLT
     *
     * @return array Array of service body data with 'id' and 'name' keys
     */
    public static function get_all() {
        // Return cached result if available
        if (self::$cache !== null) {
            return self::$cache;
        }

        $settings = get_option('mayo_settings', []);
        $bmlt_root_server = $settings['bmlt_root_server'] ?? '';

        if (empty($bmlt_root_server)) {
            self::$cache = [];
            return self::$cache;
        }

        $response = wp_remote_get($bmlt_root_server . '/client_interface/json/?switcher=GetServiceBodies', [
            'timeout' => 15,
            'sslverify' => true
        ]);

        if (is_wp_error($response)) {
            error_log('ServiceBodyLookup Error: ' . $response->get_error_message());
            self::$cache = [];
            return self::$cache;
        }

        $service_bodies = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($service_bodies)) {
            self::$cache = [];
            return self::$cache;
        }

        self::$cache = $service_bodies;
        return self::$cache;
    }

    /**
     * Get service bodies filtered by IDs
     *
     * @param array $ids Service body IDs to include
     * @return array Array of service body data matching the IDs
     */
    public static function get_by_ids($ids) {
        if (empty($ids)) {
            return [];
        }

        $all_bodies = self::get_all();
        $result = [];

        foreach ($all_bodies as $body) {
            if (in_array($body['id'], $ids)) {
                $result[] = [
                    'id' => $body['id'],
                    'name' => $body['name']
                ];
            }
        }

        return $result;
    }

    /**
     * Get all service bodies as an ID => name map
     *
     * @return array Associative array of service body ID => name
     */
    public static function get_all_as_map() {
        $all_bodies = self::get_all();
        $map = [];

        foreach ($all_bodies as $body) {
            if (isset($body['id']) && isset($body['name'])) {
                $map[(string)$body['id']] = $body['name'];
            }
        }

        return $map;
    }

    /**
     * Clear the service body cache
     *
     * Useful for testing or when settings change.
     */
    public static function clear_cache() {
        self::$cache = null;
    }
}
