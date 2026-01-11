<?php

namespace BmltEnabled\Mayo;

if ( ! defined( 'ABSPATH' ) ) exit;

use BmltEnabled\Mayo\Rest\EventsController;
use BmltEnabled\Mayo\Rest\AnnouncementsController;
use BmltEnabled\Mayo\Rest\SubscribersController;
use BmltEnabled\Mayo\Rest\SettingsController;

/**
 * REST API orchestrator
 *
 * Delegates route registration to domain-specific controllers.
 * Provides backward compatibility wrappers for external callers.
 */
class Rest {

    /**
     * Initialize REST API
     */
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /**
     * Register all REST API routes by delegating to controllers
     */
    public static function register_routes() {
        EventsController::register_routes();
        AnnouncementsController::register_routes();
        SubscribersController::register_routes();
        SettingsController::register_routes();
    }

    // ========================================================================
    // Backward compatibility wrappers
    // These methods delegate to the appropriate controller for external callers
    // ========================================================================

    /**
     * Format event data for API response
     *
     * @deprecated Use EventsController::format_event() directly
     * @param int $post_id
     * @return array
     */
    public static function format_event($post_id) {
        return EventsController::format_event($post_id);
    }

    /**
     * Build email content for event notifications
     *
     * @deprecated Use EventsController::build_event_email_content() directly
     * @param array $params
     * @param string $subject_template
     * @param string $view_url
     * @return array
     */
    public static function build_event_email_content($params, $subject_template, $view_url) {
        return EventsController::build_event_email_content($params, $subject_template, $view_url);
    }

    /**
     * Get events (for RSS feed and other internal callers)
     *
     * @deprecated Use EventsController::get_events() directly
     * @param \WP_REST_Request|null $request
     * @return \WP_REST_Response
     */
    public static function bmltenabled_mayo_get_events($request = null) {
        if ($request === null) {
            $request = new \WP_REST_Request('GET', '/event-manager/v1/events');
        }
        return EventsController::get_events($request);
    }
}

Rest::init();
