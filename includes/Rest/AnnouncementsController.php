<?php

namespace BmltEnabled\Mayo\Rest;

if ( ! defined( 'ABSPATH' ) ) exit;

use BmltEnabled\Mayo\Announcement;
use BmltEnabled\Mayo\Rest\Helpers\TaxonomyQuery;
use BmltEnabled\Mayo\Rest\Helpers\FileUpload;
use BmltEnabled\Mayo\Rest\Helpers\EmailNotification;

/**
 * REST API controller for announcement management
 */
class AnnouncementsController {

    /**
     * Register REST API routes for announcements
     */
    public static function register_routes() {
        // Get announcements with filtering
        register_rest_route('event-manager/v1', '/announcements', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_announcements'],
            'permission_callback' => '__return_true',
        ]);

        // Get single announcement by ID
        register_rest_route('event-manager/v1', '/announcement/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_announcement'],
            'permission_callback' => '__return_true',
        ]);

        // Get announcement by slug
        register_rest_route('event-manager/v1', '/announcement-by-slug/(?P<slug>[a-zA-Z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_announcement_by_slug'],
            'permission_callback' => '__return_true',
        ]);

        // Submit announcement (public form)
        register_rest_route('event-manager/v1', '/submit-announcement', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'submit_announcement'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Get announcements with optional filtering
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function get_announcements($request) {
        $params = $request->get_params();

        $priority = isset($params['priority']) ? sanitize_text_field($params['priority']) : '';
        $categories = isset($params['categories']) ? sanitize_text_field($params['categories']) : '';
        $categoryRelation = isset($params['category_relation']) ? strtoupper(sanitize_text_field($params['category_relation'])) : 'OR';
        $tags = isset($params['tags']) ? sanitize_text_field($params['tags']) : '';
        $linked_event = isset($params['linked_event']) ? intval($params['linked_event']) : 0;
        $active_only = !isset($params['active']) || $params['active'] !== 'false';
        $orderby = isset($params['orderby']) ? sanitize_text_field($params['orderby']) : 'date';
        $order = isset($params['order']) ? strtoupper(sanitize_text_field($params['order'])) : '';

        $today = current_time('Y-m-d');

        $args = [
            'post_type' => 'mayo_announcement',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ];

        $meta_query = [];

        // Filter by active display window
        if ($active_only) {
            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key' => 'display_start_date',
                    'value' => $today,
                    'compare' => '<=',
                    'type' => 'DATE'
                ],
                [
                    'key' => 'display_start_date',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => 'display_start_date',
                    'value' => '',
                    'compare' => '='
                ]
            ];
            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key' => 'display_end_date',
                    'value' => $today,
                    'compare' => '>=',
                    'type' => 'DATE'
                ],
                [
                    'key' => 'display_end_date',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => 'display_end_date',
                    'value' => '',
                    'compare' => '='
                ]
            ];
        }

        // Filter by priority
        if (!empty($priority)) {
            $meta_query[] = [
                'key' => 'priority',
                'value' => $priority,
                'compare' => '='
            ];
        }

        // Filter by linked event
        if ($linked_event > 0) {
            $meta_query[] = [
                'key' => 'linked_events',
                'value' => 'i:' . intval($linked_event) . ';',
                'compare' => 'LIKE'
            ];
        }

        if (!empty($meta_query)) {
            $meta_query['relation'] = 'AND';
            $args['meta_query'] = $meta_query;
        }

        // Handle taxonomy filters using helper
        $taxonomy_args = TaxonomyQuery::build_taxonomy_args($categories, $categoryRelation, $tags);
        $args = array_merge($args, $taxonomy_args);

        $posts = get_posts($args);

        $announcements = [];
        foreach ($posts as $post) {
            $announcements[] = self::format_announcement($post);
        }

        // Sort announcements
        $announcements = self::sort_announcements($announcements, $orderby, $order);

        return new \WP_REST_Response([
            'announcements' => $announcements,
            'total' => count($announcements)
        ]);
    }

    /**
     * Sort announcements by specified field and order
     *
     * @param array $announcements Array of announcements
     * @param string $orderby Sort field (date, title, created)
     * @param string $order Sort direction (ASC, DESC)
     * @return array Sorted announcements
     */
    private static function sort_announcements($announcements, $orderby, $order) {
        // Set smart defaults for order direction
        if (empty($order)) {
            $order = ($orderby === 'title') ? 'ASC' : 'DESC';
        }

        usort($announcements, function ($a, $b) use ($orderby, $order) {
            switch ($orderby) {
                case 'title':
                    $cmp = strcasecmp($a['title'], $b['title']);
                    break;
                case 'created':
                    $cmp = strcmp($a['created_date'] ?? '', $b['created_date'] ?? '');
                    break;
                case 'date':
                default:
                    $cmp = strcmp($a['display_start_date'] ?: '', $b['display_start_date'] ?: '');
                    break;
            }
            return $order === 'DESC' ? -$cmp : $cmp;
        });

        return $announcements;
    }

    /**
     * Get a single announcement by ID
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public static function get_announcement($request) {
        $id = intval($request['id']);
        $post = get_post($id);

        if (!$post || $post->post_type !== 'mayo_announcement') {
            return new \WP_Error('not_found', 'Announcement not found', ['status' => 404]);
        }

        return new \WP_REST_Response(self::format_announcement($post));
    }

    /**
     * Get announcement by slug
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public static function get_announcement_by_slug($request) {
        $slug = sanitize_title($request['slug']);

        $posts = get_posts([
            'post_type' => 'mayo_announcement',
            'name' => $slug,
            'post_status' => 'publish',
            'posts_per_page' => 1,
        ]);

        if (empty($posts)) {
            return new \WP_Error('not_found', 'Announcement not found', ['status' => 404]);
        }

        return new \WP_REST_Response(self::format_announcement($posts[0]));
    }

    /**
     * Submit a new announcement from the public form
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function submit_announcement($request) {
        $params = $request->get_params();

        // Create the post
        $post_data = [
            'post_title'   => sanitize_text_field($params['title']),
            'post_content' => sanitize_textarea_field($params['description'] ?? ''),
            'post_status'  => 'pending',
            'post_type'    => 'mayo_announcement'
        ];

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $post_id->get_error_message()
            ], 400);
        }

        // Add metadata
        if (!empty($params['service_body'])) {
            add_post_meta($post_id, 'service_body', sanitize_text_field($params['service_body']));
        }
        if (!empty($params['email'])) {
            add_post_meta($post_id, 'email', sanitize_email($params['email']));
        }
        if (!empty($params['contact_name'])) {
            add_post_meta($post_id, 'contact_name', sanitize_text_field($params['contact_name']));
        }
        if (!empty($params['start_date'])) {
            add_post_meta($post_id, 'display_start_date', sanitize_text_field($params['start_date']));
        }
        if (!empty($params['start_time'])) {
            add_post_meta($post_id, 'display_start_time', sanitize_text_field($params['start_time']));
        }
        if (!empty($params['end_date'])) {
            add_post_meta($post_id, 'display_end_date', sanitize_text_field($params['end_date']));
        }
        if (!empty($params['end_time'])) {
            add_post_meta($post_id, 'display_end_time', sanitize_text_field($params['end_time']));
        }

        // Handle categories and tags
        if (!empty($params['categories'])) {
            $categories_array = array_map('intval', explode(',', $params['categories']));
            wp_set_post_categories($post_id, $categories_array);
        }
        if (!empty($params['tags'])) {
            wp_set_post_tags($post_id, $params['tags']);
        }

        // Handle file uploads using helper
        FileUpload::process_uploads($post_id);

        // Send email notification
        self::send_submission_email($post_id, $params);

        return new \WP_REST_Response([
            'success' => true,
            'id' => $post_id,
            'message' => 'Announcement submitted successfully'
        ], 200);
    }

    /**
     * Send email notification for announcement submission
     *
     * @param int $post_id Post ID
     * @param array $params Submission parameters
     */
    private static function send_submission_email($post_id, $params) {
        $valid_emails = EmailNotification::get_notification_recipients_array();

        if (empty($valid_emails)) {
            return;
        }

        $site_name = get_bloginfo('name');
        $subject = sprintf('[%s] New Announcement Submission: %s', $site_name, sanitize_text_field($params['title']));

        $message = "A new announcement has been submitted and is pending review.\n\n";
        $message .= "Title: " . sanitize_text_field($params['title']) . "\n\n";

        // Dates
        if (!empty($params['start_date']) || !empty($params['end_date'])) {
            $start_date = !empty($params['start_date']) ? sanitize_text_field($params['start_date']) : 'Not set';
            $end_date = !empty($params['end_date']) ? sanitize_text_field($params['end_date']) : 'Not set';
            $message .= "Start Date: " . $start_date . "\n";
            $message .= "End Date: " . $end_date . "\n\n";
        }

        $message .= "Description:\n" . sanitize_textarea_field($params['description'] ?? '') . "\n\n";

        // Service body info
        if (!empty($params['service_body'])) {
            $service_body_id = sanitize_text_field($params['service_body']);
            $message .= "Service Body ID: " . $service_body_id . "\n";
        }

        // Contact info
        $message .= "\nSubmitted by:\n";
        $message .= "Name: " . sanitize_text_field($params['contact_name'] ?? 'Not provided') . "\n";
        $message .= "Email: " . sanitize_email($params['email'] ?? 'Not provided') . "\n\n";

        // Categories
        if (!empty($params['categories'])) {
            $category_ids = array_map('intval', explode(',', $params['categories']));
            $category_names = [];
            foreach ($category_ids as $cat_id) {
                $cat = get_category($cat_id);
                if ($cat) {
                    $category_names[] = $cat->name;
                }
            }
            if (!empty($category_names)) {
                $message .= "Categories: " . implode(', ', $category_names) . "\n";
            }
        }

        // Tags
        if (!empty($params['tags'])) {
            $message .= "Tags: " . sanitize_text_field($params['tags']) . "\n";
        }

        // Edit link
        $edit_link = admin_url('post.php?post=' . $post_id . '&action=edit');
        $message .= "\nReview and edit this announcement:\n" . $edit_link . "\n";

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        foreach ($valid_emails as $email) {
            wp_mail(trim($email), $subject, $message, $headers);
        }
    }

    /**
     * Format announcement data for API response
     *
     * @param \WP_Post $post
     * @return array
     */
    public static function format_announcement($post) {
        $linked_refs = Announcement::get_linked_event_refs($post->ID);
        $linked_event_data = [];

        foreach ($linked_refs as $ref) {
            $resolved = Announcement::resolve_event_ref($ref);
            if ($resolved) {
                // Handle both 'link' (from external API) and 'permalink' (from local)
                $permalink = $resolved['permalink'] ?? $resolved['link'] ?? '#';
                // Handle title - may be string or {rendered: "..."} object from WP REST API
                $title = $resolved['title'] ?? 'Unknown Event';
                if (is_array($title) && isset($title['rendered'])) {
                    $title = $title['rendered'];
                }
                $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
                // Handle start_date from meta object or direct field
                $start_date = $resolved['start_date'] ?? ($resolved['meta']['event_start_date'] ?? '');
                $linked_event_data[] = [
                    'id' => $resolved['id'],
                    'title' => $title,
                    'permalink' => $permalink,
                    'start_date' => $start_date,
                    'icon' => $resolved['icon'] ?? null,
                    'source' => $resolved['source'] ?? ['type' => 'local', 'id' => 'local', 'name' => 'Local'],
                ];
            } elseif ($ref['type'] === 'external' && !empty($ref['source_id'])) {
                // External event unavailable - include placeholder with source info
                $source = Announcement::get_external_source($ref['source_id']);
                $source_name = $source ? ($source['name'] ?? parse_url($source['url'], PHP_URL_HOST)) : 'External';
                $linked_event_data[] = [
                    'id' => $ref['id'],
                    'title' => 'Event details unavailable',
                    'permalink' => '#',
                    'start_date' => '',
                    'unavailable' => true,
                    'source' => [
                        'type' => 'external',
                        'id' => $ref['source_id'],
                        'name' => $source_name,
                    ],
                ];
            }
        }

        // Calculate is_active based on display dates
        $today = current_time('Y-m-d');
        $display_start_date = get_post_meta($post->ID, 'display_start_date', true);
        $display_end_date = get_post_meta($post->ID, 'display_end_date', true);

        $is_active = true;
        if ($display_start_date && $display_start_date > $today) {
            $is_active = false;
        }
        if ($display_end_date && $display_end_date < $today) {
            $is_active = false;
        }

        $permalink = get_permalink($post->ID);

        // Build edit link manually since get_edit_post_link may return null in REST context
        $edit_link = admin_url('post.php?post=' . $post->ID . '&action=edit');

        return [
            'id' => $post->ID,
            'title' => html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
            'content' => apply_filters('the_content', $post->post_content),
            'excerpt' => get_the_excerpt($post),
            'permalink' => $permalink,
            'link' => $permalink,
            'edit_link' => $edit_link,
            'display_start_date' => $display_start_date,
            'display_start_time' => get_post_meta($post->ID, 'display_start_time', true),
            'display_end_date' => $display_end_date,
            'display_end_time' => get_post_meta($post->ID, 'display_end_time', true),
            'priority' => get_post_meta($post->ID, 'priority', true) ?: 'normal',
            'service_body' => get_post_meta($post->ID, 'service_body', true) ?: '',
            'is_active' => $is_active,
            'linked_events' => $linked_event_data,
            'featured_image' => get_the_post_thumbnail_url($post->ID, 'medium'),
            'categories' => TaxonomyQuery::get_terms($post, 'category'),
            'tags' => TaxonomyQuery::get_terms($post, 'post_tag'),
            'created_date' => $post->post_date,
        ];
    }
}
