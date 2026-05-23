<?php

namespace BmltEnabled\Mayo\Rest\Helpers;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Validates that a BMLT root server URL is well-formed and reachable.
 *
 * Performs cheap checks first (format, scheme) before an outbound handshake
 * against the BMLT semantic endpoint to confirm the URL actually points at a
 * BMLT root server.
 */
class RootServerValidator {

    /**
     * Timeout, in seconds, for the outbound reachability handshake.
     *
     * Kept shorter than the read-path lookup in ServiceBodyLookup (15s) so a
     * slow host can't hang a settings save for long.
     */
    const TIMEOUT = 10;

    /**
     * Validate and normalize a BMLT root server URL.
     *
     * @param string $url The candidate root server URL.
     * @return string|\WP_Error Normalized URL (no trailing slash) on success,
     *                          or a WP_Error describing why validation failed.
     */
    public static function validate( $url ) {
        $url = is_string( $url ) ? trim( $url ) : '';

        if ( $url === '' ) {
            return new \WP_Error(
                'invalid_root_server_url',
                __( 'Please enter a BMLT root server URL.', 'mayo-events-manager' ),
                [ 'status' => 400 ]
            );
        }

        // 1. Format — must be a valid absolute URL.
        $normalized = untrailingslashit( esc_url_raw( $url ) );

        if ( empty( $normalized ) || ! wp_http_validate_url( $normalized ) ) {
            return new \WP_Error(
                'invalid_root_server_url',
                sprintf(
                    /* translators: %s: the URL the user entered. */
                    __( '"%s" is not a valid URL.', 'mayo-events-manager' ),
                    $url
                ),
                [ 'status' => 400 ]
            );
        }

        // 2. Scheme — require https.
        $scheme = strtolower( (string) wp_parse_url( $normalized, PHP_URL_SCHEME ) );
        if ( $scheme !== 'https' ) {
            return new \WP_Error(
                'invalid_root_server_url',
                sprintf(
                    /* translators: %s: the URL the user entered. */
                    __( 'The BMLT root server URL must start with "https://" (got "%s").', 'mayo-events-manager' ),
                    $url
                ),
                [ 'status' => 400 ]
            );
        }

        // 3. Reachability — handshake against the BMLT semantic endpoint.
        $response = wp_remote_get(
            $normalized . '/client_interface/json/?switcher=GetServerInfo',
            [
                'timeout'   => self::TIMEOUT,
                'sslverify' => true,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return new \WP_Error(
                'root_server_unreachable',
                sprintf(
                    /* translators: %s: the BMLT root server URL. */
                    __( 'Could not reach a BMLT root server at %s', 'mayo-events-manager' ),
                    $normalized
                ),
                [ 'status' => 400 ]
            );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new \WP_Error(
                'root_server_unreachable',
                sprintf(
                    /* translators: 1: the BMLT root server URL, 2: the HTTP status code returned. */
                    __( 'Could not reach a BMLT root server at %1$s (HTTP %2$d).', 'mayo-events-manager' ),
                    $normalized,
                    $code
                ),
                [ 'status' => 400 ]
            );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! self::looks_like_server_info( $data ) ) {
            return new \WP_Error(
                'not_a_bmlt_root_server',
                sprintf(
                    /* translators: %s: the BMLT root server URL. */
                    __( 'The URL %s did not respond like a BMLT root server.', 'mayo-events-manager' ),
                    $normalized
                ),
                [ 'status' => 400 ]
            );
        }

        return $normalized;
    }

    /**
     * Determine whether a decoded GetServerInfo response looks like a BMLT
     * root server. GetServerInfo returns a single-element list of objects
     * carrying a `version` field, e.g. [ { "version": "3.0.0", ... } ].
     *
     * @param mixed $data Decoded JSON response body.
     * @return bool
     */
    private static function looks_like_server_info( $data ) {
        if ( ! is_array( $data ) ) {
            return false;
        }

        if ( isset( $data['version'] ) ) {
            return true;
        }

        return isset( $data[0] ) && is_array( $data[0] ) && isset( $data[0]['version'] );
    }
}
