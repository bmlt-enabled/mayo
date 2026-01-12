<?php

namespace BmltEnabled\Mayo\Rest;

if ( ! defined( 'ABSPATH' ) ) exit;

use BmltEnabled\Mayo\Subscriber;
use BmltEnabled\Mayo\Rest\Helpers\PreferencesSanitizer;
use BmltEnabled\Mayo\Rest\Helpers\ServiceBodyLookup;

/**
 * REST API controller for subscriber management
 */
class SubscribersController {

    /**
     * Register REST API routes for subscribers
     */
    public static function register_routes() {
        // Subscription endpoint
        register_rest_route('event-manager/v1', '/subscribe', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'subscribe'],
            'permission_callback' => '__return_true',
        ]);

        // Get subscription options (categories, tags, service bodies enabled by admin)
        register_rest_route('event-manager/v1', '/subscription-options', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_subscription_options'],
            'permission_callback' => '__return_true',
        ]);

        // Get subscriber preferences by token
        register_rest_route('event-manager/v1', '/subscriber/(?P<token>[a-fA-F0-9]+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_subscriber'],
            'permission_callback' => '__return_true',
        ]);

        // Update subscriber preferences
        register_rest_route('event-manager/v1', '/subscriber/(?P<token>[a-fA-F0-9]+)', [
            'methods' => 'PUT',
            'callback' => [__CLASS__, 'update_subscriber'],
            'permission_callback' => '__return_true',
        ]);

        // Get all subscribers (admin only)
        register_rest_route('event-manager/v1', '/subscribers', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_all_subscribers'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        // Count matching subscribers (for announcement editor)
        register_rest_route('event-manager/v1', '/subscribers/count', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'count_matching_subscribers'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        // Update subscriber by ID (admin only)
        register_rest_route('event-manager/v1', '/subscribers/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [__CLASS__, 'admin_update_subscriber'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        // Delete subscriber by ID (admin only)
        register_rest_route('event-manager/v1', '/subscribers/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [__CLASS__, 'admin_delete_subscriber'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * Handle subscription requests
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function subscribe($request) {
        $params = $request->get_params();

        $email = isset($params['email']) ? sanitize_email($params['email']) : '';

        if (empty($email)) {
            return new \WP_REST_Response([
                'success' => false,
                'code' => 'missing_email',
                'message' => 'Email address is required.'
            ], 400);
        }

        // Get preferences if provided
        $preferences = isset($params['preferences']) ? $params['preferences'] : null;

        // Validate preferences structure if provided
        if ($preferences !== null) {
            $clean_preferences = PreferencesSanitizer::sanitize($preferences);

            if (is_wp_error($clean_preferences)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'code' => $clean_preferences->get_error_code(),
                    'message' => $clean_preferences->get_error_message()
                ], 400);
            }

            if (!PreferencesSanitizer::has_selections($clean_preferences)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'code' => 'no_preferences',
                    'message' => 'Please select at least one category, tag, or service body.'
                ], 400);
            }

            $preferences = $clean_preferences;
        }

        $result = Subscriber::subscribe($email, $preferences);

        $status_code = $result['success'] ? 200 : 400;

        return new \WP_REST_Response($result, $status_code);
    }

    /**
     * Get subscription options (categories, tags, service bodies enabled by admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function get_subscription_options($request) {
        $settings = get_option('mayo_settings', []);

        // Get enabled IDs from settings
        $enabled_categories = $settings['subscription_categories'] ?? [];
        $enabled_tags = $settings['subscription_tags'] ?? [];
        $enabled_service_bodies = $settings['subscription_service_bodies'] ?? [];

        // Fetch category details
        $categories = [];
        if (!empty($enabled_categories)) {
            $terms = get_terms([
                'taxonomy' => 'category',
                'include' => $enabled_categories,
                'hide_empty' => false
            ]);
            if (!is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $categories[] = [
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug
                    ];
                }
            }
        }

        // Fetch tag details
        $tags = [];
        if (!empty($enabled_tags)) {
            $terms = get_terms([
                'taxonomy' => 'post_tag',
                'include' => $enabled_tags,
                'hide_empty' => false
            ]);
            if (!is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $tags[] = [
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug
                    ];
                }
            }
        }

        // Fetch service body details from BMLT using helper
        $service_bodies = [];
        if (!empty($enabled_service_bodies)) {
            $service_bodies = ServiceBodyLookup::get_by_ids($enabled_service_bodies);
        }

        return new \WP_REST_Response([
            'categories' => $categories,
            'tags' => $tags,
            'service_bodies' => $service_bodies
        ], 200);
    }

    /**
     * Get subscriber data by token
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function get_subscriber($request) {
        $token = $request->get_param('token');

        $subscriber = Subscriber::get_by_token($token);

        if (!$subscriber) {
            return new \WP_REST_Response([
                'success' => false,
                'code' => 'not_found',
                'message' => 'Subscriber not found.'
            ], 404);
        }

        // Parse preferences
        $preferences = null;
        if (!empty($subscriber->preferences)) {
            $preferences = json_decode($subscriber->preferences, true);
        }

        return new \WP_REST_Response([
            'email' => $subscriber->email,
            'status' => $subscriber->status,
            'preferences' => $preferences
        ], 200);
    }

    /**
     * Update subscriber preferences
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function update_subscriber($request) {
        $token = $request->get_param('token');
        $params = $request->get_params();

        $subscriber = Subscriber::get_by_token($token);

        if (!$subscriber) {
            return new \WP_REST_Response([
                'success' => false,
                'code' => 'not_found',
                'message' => 'Subscriber not found.'
            ], 404);
        }

        if ($subscriber->status !== 'active') {
            return new \WP_REST_Response([
                'success' => false,
                'code' => 'not_active',
                'message' => 'Subscription is not active.'
            ], 400);
        }

        // Get and validate preferences
        $preferences = isset($params['preferences']) ? $params['preferences'] : null;

        if ($preferences === null || !is_array($preferences)) {
            return new \WP_REST_Response([
                'success' => false,
                'code' => 'invalid_preferences',
                'message' => 'Preferences must be provided.'
            ], 400);
        }

        $clean_preferences = PreferencesSanitizer::sanitize($preferences);

        if (is_wp_error($clean_preferences)) {
            return new \WP_REST_Response([
                'success' => false,
                'code' => $clean_preferences->get_error_code(),
                'message' => $clean_preferences->get_error_message()
            ], 400);
        }

        if (!PreferencesSanitizer::has_selections($clean_preferences)) {
            return new \WP_REST_Response([
                'success' => false,
                'code' => 'no_preferences',
                'message' => 'Please select at least one category, tag, or service body.'
            ], 400);
        }

        $result = Subscriber::update_preferences($token, $clean_preferences);

        if ($result) {
            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Preferences updated successfully.'
            ], 200);
        } else {
            return new \WP_REST_Response([
                'success' => false,
                'code' => 'update_failed',
                'message' => 'Failed to update preferences.'
            ], 500);
        }
    }

    /**
     * Get all subscribers (admin only)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function get_all_subscribers($request) {
        $subscribers = Subscriber::get_all_subscribers();

        // Cache for category/tag name lookups
        $category_names = [];
        $tag_names = [];

        // Fetch all categories and tags for lookups
        $all_cats = get_terms(['taxonomy' => 'category', 'hide_empty' => false]);
        if (!is_wp_error($all_cats)) {
            foreach ($all_cats as $cat) {
                $category_names[$cat->term_id] = $cat->name;
            }
        }

        $all_tags = get_terms(['taxonomy' => 'post_tag', 'hide_empty' => false]);
        if (!is_wp_error($all_tags)) {
            foreach ($all_tags as $tag) {
                $tag_names[$tag->term_id] = $tag->name;
            }
        }

        // Get service body names using helper
        $service_body_names = ServiceBodyLookup::get_all_as_map();

        // Format subscriber data
        $formatted = [];
        foreach ($subscribers as $subscriber) {
            $prefs = null;
            $prefs_display = [];

            if (!empty($subscriber->preferences)) {
                $prefs = json_decode($subscriber->preferences, true);

                if (is_array($prefs)) {
                    // Resolve category names
                    if (!empty($prefs['categories'])) {
                        $cat_names = [];
                        foreach ($prefs['categories'] as $cat_id) {
                            $cat_names[] = $category_names[$cat_id] ?? "Category $cat_id";
                        }
                        if (!empty($cat_names)) {
                            $prefs_display['categories'] = $cat_names;
                        }
                    }

                    // Resolve tag names
                    if (!empty($prefs['tags'])) {
                        $tg_names = [];
                        foreach ($prefs['tags'] as $tag_id) {
                            $tg_names[] = $tag_names[$tag_id] ?? "Tag $tag_id";
                        }
                        if (!empty($tg_names)) {
                            $prefs_display['tags'] = $tg_names;
                        }
                    }

                    // Resolve service body names
                    if (!empty($prefs['service_bodies'])) {
                        $sb_names = [];
                        foreach ($prefs['service_bodies'] as $sb_id) {
                            $sb_names[] = $service_body_names[(string) $sb_id]
                                ?? "Service Body $sb_id";
                        }
                        if (!empty($sb_names)) {
                            $prefs_display['service_bodies'] = $sb_names;
                        }
                    }
                }
            }

            $formatted[] = [
                'id' => $subscriber->id,
                'email' => $subscriber->email,
                'status' => $subscriber->status,
                'created_at' => $subscriber->created_at,
                'confirmed_at' => $subscriber->confirmed_at,
                'preferences' => $prefs,
                'preferences_display' => $prefs_display
            ];
        }

        return new \WP_REST_Response($formatted, 200);
    }

    /**
     * Count subscribers matching announcement criteria
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function count_matching_subscribers($request) {
        $params = $request->get_json_params();

        $announcement_data = [
            'categories' => $params['categories'] ?? [],
            'tags' => $params['tags'] ?? [],
            'service_body' => $params['service_body'] ?? null,
        ];

        $matching = Subscriber::get_matching_with_reasons($announcement_data);

        // Return count and list of subscribers with reasons
        $subscribers = array_map(function ($item) {
            return [
                'email' => $item['subscriber']->email,
                'reason' => $item['reason']
            ];
        }, $matching);

        return new \WP_REST_Response([
            'count' => count($matching),
            'subscribers' => $subscribers
        ], 200);
    }

    /**
     * Update subscriber by ID (admin only)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function admin_update_subscriber($request) {
        $id = intval($request->get_param('id'));
        $params = $request->get_json_params();

        $updated = false;

        // Update status if provided
        if (isset($params['status'])) {
            $valid_statuses = ['active', 'pending', 'unsubscribed'];
            if (in_array($params['status'], $valid_statuses, true)) {
                $updated = Subscriber::update_status($id, $params['status']) || $updated;
            }
        }

        // Update preferences if provided
        if (isset($params['preferences'])) {
            $updated = Subscriber::update_preferences_by_id($id, $params['preferences']) || $updated;
        }

        return new \WP_REST_Response([
            'success' => true,
            'updated' => $updated
        ], 200);
    }

    /**
     * Delete subscriber by ID (admin only)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function admin_delete_subscriber($request) {
        $id = intval($request->get_param('id'));

        $deleted = Subscriber::delete($id);

        if (!$deleted) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Subscriber not found or could not be deleted'
            ], 404);
        }

        return new \WP_REST_Response([
            'success' => true
        ], 200);
    }
}
