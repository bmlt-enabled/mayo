<?php

namespace BmltEnabled\Mayo\Rest;

if ( ! defined( 'ABSPATH' ) ) exit;

use DateTime;
use DateInterval;
use BmltEnabled\Mayo\Announcement;
use BmltEnabled\Mayo\Rest\Helpers\TaxonomyQuery;
use BmltEnabled\Mayo\Rest\Helpers\FileUpload;
use BmltEnabled\Mayo\Rest\Helpers\EmailNotification;
use BmltEnabled\Mayo\Rest\Helpers\ServiceBodyLookup;

/**
 * REST API controller for events
 */
class EventsController {

    /**
     * Register REST API routes for events
     */
    public static function register_routes() {
        register_rest_route('event-manager/v1', '/submit-event', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'submit_event'],
            'permission_callback' => function() {
                return true;
            },
        ]);

        register_rest_route('event-manager/v1', '/events', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_events'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('event-manager/v1', '/event/(?P<slug>[a-zA-Z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_event_details'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('event-manager/v1', '/events/search', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'search_events'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('event-manager/v1', '/events/search-all', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'search_all_events'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route('event-manager/v1', '/events/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_event_by_id'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Submit a new event
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function submit_event($request) {
        $params = $request->get_params();

        // Create the post first
        $post_data = [
            'post_title'   => sanitize_text_field($params['event_name']),
            'post_content' => sanitize_textarea_field($params['description'] ?? ''),
            'post_status'  => 'pending',
            'post_type'    => 'mayo_event'
        ];

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $post_id->get_error_message()
            ], 400);
        }

        // Add all event metadata
        add_post_meta($post_id, 'event_type', sanitize_text_field($params['event_type']));
        add_post_meta($post_id, 'event_start_date', sanitize_text_field($params['event_start_date']));
        add_post_meta($post_id, 'event_end_date', sanitize_text_field($params['event_end_date']));
        add_post_meta($post_id, 'event_start_time', sanitize_text_field($params['event_start_time']));
        add_post_meta($post_id, 'event_end_time', sanitize_text_field($params['event_end_time']));
        add_post_meta($post_id, 'timezone', sanitize_text_field($params['timezone']));
        add_post_meta($post_id, 'service_body', sanitize_text_field($params['service_body']));
        add_post_meta($post_id, 'email', sanitize_email($params['email']));
        add_post_meta($post_id, 'contact_name', sanitize_text_field($params['contact_name']));

        // Add recurring pattern metadata if provided
        if (!empty($params['recurring_pattern'])) {
            $recurring_pattern = is_string($params['recurring_pattern'])
                ? json_decode($params['recurring_pattern'], true)
                : $params['recurring_pattern'];

            if (!is_array($recurring_pattern)) {
                $recurring_pattern = [];
            }

            $sanitized_pattern = [
                'type' => isset($recurring_pattern['type']) ? sanitize_text_field($recurring_pattern['type']) : 'none',
                'interval' => isset($recurring_pattern['interval']) ? intval($recurring_pattern['interval']) : 1,
                'endDate' => isset($recurring_pattern['endDate']) ? sanitize_text_field($recurring_pattern['endDate']) : null
            ];

            if (isset($recurring_pattern['type']) && $recurring_pattern['type'] === 'weekly' && !empty($recurring_pattern['weekdays']) && is_array($recurring_pattern['weekdays'])) {
                $sanitized_pattern['weekdays'] = array_map('intval', $recurring_pattern['weekdays']);
            } elseif (isset($recurring_pattern['type']) && $recurring_pattern['type'] === 'monthly') {
                $sanitized_pattern['monthlyType'] = isset($recurring_pattern['monthlyType']) ? sanitize_text_field($recurring_pattern['monthlyType']) : 'date';
                if (isset($recurring_pattern['monthlyType']) && $recurring_pattern['monthlyType'] === 'date' && isset($recurring_pattern['monthlyDate'])) {
                    $sanitized_pattern['monthlyDate'] = intval($recurring_pattern['monthlyDate']);
                } elseif (isset($recurring_pattern['monthlyWeekday'])) {
                    $sanitized_pattern['monthlyWeekday'] = sanitize_text_field($recurring_pattern['monthlyWeekday']);
                }
            }

            add_post_meta($post_id, 'recurring_pattern', $sanitized_pattern);
        }

        // Add location metadata
        if (!empty($params['location_name'])) {
            add_post_meta($post_id, 'location_name', sanitize_text_field($params['location_name']));
        }
        if (!empty($params['location_address'])) {
            add_post_meta($post_id, 'location_address', sanitize_text_field($params['location_address']));
        }
        if (!empty($params['location_details'])) {
            add_post_meta($post_id, 'location_details', sanitize_text_field($params['location_details']));
        }

        // Handle categories and tags
        if (!empty($params['categories'])) {
            $categories_array = explode(',', $params['categories']);
            wp_set_post_categories($post_id, $categories_array);
        }
        if (!empty($params['tags'])) {
            wp_set_post_tags($post_id, $params['tags']);
        }

        // Handle file uploads using helper
        FileUpload::process_uploads($post_id);

        // Send email notification
        self::send_event_submission_email($post_id, $params);

        // Format response data
        $formatted_event = self::format_event($post_id);

        return new \WP_REST_Response($formatted_event, 200);
    }

    /**
     * Get events list with filtering
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function get_events() {
        $previous_error_reporting = error_reporting();
        error_reporting(E_ERROR | E_PARSE);

        $events = [];
        $sources = [];

        // Pagination parameters
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $per_page = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 10;

        // Get source IDs from request
        $sourceIds = isset($_GET['source_ids']) ?
            array_map('trim', array_filter(explode(',', $_GET['source_ids']))) :
            [];

        // Get local events by default unless source_ids is explicitly set and doesn't include 'local'
        if (empty($sourceIds) || in_array('local', $sourceIds)) {
            $local_events = self::get_local_events($_GET);

            // Add local source to sources array
            $sources['local'] = [
                'id' => 'local',
                'name' => 'Local Events',
                'url' => get_site_url()
            ];

            $events = array_merge($events, array_map(function($event) {
                $event['source_id'] = 'local';
                return $event;
            }, $local_events));
        }

        // Get external events from specified sources
        if (!empty($sourceIds)) {
            $external_sources = get_option('mayo_external_sources', []);
            $enabled_sources = [];

            foreach ($external_sources as $source) {
                if (in_array($source['id'], $sourceIds) && $source['enabled']) {
                    $enabled_sources[] = $source;
                }
            }

            if (!empty($enabled_sources)) {
                foreach ($enabled_sources as $source) {
                    try {
                        $result = self::fetch_external_events($source);

                        if (!empty($result['events'])) {
                            // Filter external events by request tags (client-side filtering)
                            // This ensures external events match the requested tag filters
                            $filtered_events = $result['events'];
                            if (isset($_GET['tags'])) {
                                $filtered_events = self::filter_external_events_by_tags(
                                    $result['events'],
                                    sanitize_text_field($_GET['tags'])
                                );
                            }
                            $events = array_merge($events, $filtered_events);
                        }

                        if (!empty($result['source'])) {
                            $sources[$source['id']] = $result['source'];
                        }
                    } catch (\Exception $e) {
                        error_log('Error fetching events from source ' . $source['url'] . ': ' . $e->getMessage());
                    }
                }
            }
        }

        // Get sort order from request
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
        if ($order !== 'ASC' && $order !== 'DESC') {
            $order = 'ASC';
        }

        // Sort all events by date
        usort($events, function($a, $b) use ($order) {
            if (!isset($a['meta']['event_start_date'])) {
                return 1;
            }
            if (!isset($b['meta']['event_start_date'])) {
                return -1;
            }

            $dateA = $a['meta']['event_start_date'] . ' ' . (isset($a['meta']['event_start_time']) ? $a['meta']['event_start_time'] : '00:00:00');
            $dateB = $b['meta']['event_start_date'] . ' ' . (isset($b['meta']['event_start_time']) ? $b['meta']['event_start_time'] : '00:00:00');

            $timeA = strtotime($dateA);
            $timeB = strtotime($dateB);

            if ($timeA === false && $timeB === false) {
                return 0;
            } elseif ($timeA === false) {
                return 1;
            } elseif ($timeB === false) {
                return -1;
            }

            if ($order === 'DESC') {
                return $timeB - $timeA;
            }
            return $timeA - $timeB;
        });

        // Apply pagination
        $total_events = count($events);
        $total_pages = ceil($total_events / $per_page);
        $page = min($page, max(1, $total_pages));
        $offset = ($page - 1) * $per_page;
        $paginated_events = array_slice($events, $offset, $per_page);

        error_reporting($previous_error_reporting);

        return new \WP_REST_Response([
            'events' => $paginated_events,
            'sources' => array_values($sources),
            'pagination' => [
                'total' => $total_events,
                'per_page' => $per_page,
                'current_page' => $page,
                'total_pages' => $total_pages
            ]
        ]);
    }

    /**
     * Get event details by slug
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public static function get_event_details($request) {
        $slug = $request['slug'];
        $query = new \WP_Query([
            'post_type' => 'mayo_event',
            'name' => $slug,
            'posts_per_page' => 1,
        ]);

        if ($query->have_posts()) {
            $query->the_post();
            $event = self::format_event(get_post());
            wp_reset_postdata();
            return rest_ensure_response($event);
        }

        return new \WP_Error('no_event', 'Event not found', ['status' => 404]);
    }

    /**
     * Search events (local only)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function search_events($request) {
        $search = isset($request['search']) ? sanitize_text_field($request['search']) : '';
        $limit = isset($request['limit']) ? intval($request['limit']) : 20;
        $include = isset($request['include']) ? sanitize_text_field($request['include']) : '';

        $args = [
            'post_type' => 'mayo_event',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'meta_value',
            'meta_key' => 'event_start_date',
            'order' => 'ASC',
        ];

        if (!empty($include)) {
            $ids = array_map('intval', explode(',', $include));
            $args['post__in'] = $ids;
            $args['posts_per_page'] = count($ids);
        } elseif (!empty($search)) {
            $args['s'] = $search;
        }

        $posts = get_posts($args);

        $events = [];
        foreach ($posts as $post) {
            $events[] = [
                'id' => $post->ID,
                'title' => html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
                'start_date' => get_post_meta($post->ID, 'event_start_date', true),
                'permalink' => get_permalink($post->ID),
                'edit_link' => get_edit_post_link($post->ID, 'raw'),
            ];
        }

        return new \WP_REST_Response([
            'events' => $events
        ]);
    }

    /**
     * Search all events (local + external)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function search_all_events($request) {
        $search = isset($request['search']) ? sanitize_text_field($request['search']) : '';
        $per_page = isset($request['per_page']) ? intval($request['per_page']) : 20;
        $page = isset($request['page']) ? max(1, intval($request['page'])) : 1;
        $hide_past = isset($request['hide_past']) ? filter_var($request['hide_past'], FILTER_VALIDATE_BOOLEAN) : true;

        if (isset($request['limit']) && !isset($request['per_page'])) {
            $per_page = intval($request['limit']);
        }

        $today = wp_date('Y-m-d');
        $all_events = [];

        // Search local events
        $local_args = [
            'post_type' => 'mayo_event',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'meta_key' => 'event_start_date',
            'order' => 'ASC',
        ];

        if (!empty($search)) {
            $local_args['s'] = $search;
        }

        if ($hide_past) {
            $local_args['meta_query'] = [
                [
                    'key' => 'event_start_date',
                    'value' => $today,
                    'compare' => '>=',
                    'type' => 'DATE',
                ],
            ];
        }

        $local_posts = get_posts($local_args);

        foreach ($local_posts as $post) {
            $all_events[] = [
                'id' => $post->ID,
                'title' => html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
                'slug' => $post->post_name,
                'start_date' => get_post_meta($post->ID, 'event_start_date', true),
                'permalink' => get_permalink($post->ID),
                'source' => [
                    'type' => 'local',
                    'id' => 'local',
                    'name' => 'Local',
                ],
            ];
        }

        // Search external sources
        $external_sources = get_option('mayo_external_sources', []);

        foreach ($external_sources as $source) {
            if (empty($source['enabled']) || empty($source['url'])) {
                continue;
            }

            try {
                $params = ['per_page' => 100];
                if (!empty($search)) {
                    $params['search'] = $search;
                }

                $url = add_query_arg($params, trailingslashit($source['url']) . 'wp-json/event-manager/v1/events');

                $response = wp_remote_get($url, [
                    'timeout' => 10,
                    'sslverify' => true
                ]);

                if (is_wp_error($response)) {
                    continue;
                }

                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);

                $events = isset($data['events']) ? $data['events'] : $data;

                if (!is_array($events)) {
                    continue;
                }

                $source_name = $source['name'] ?? parse_url($source['url'], PHP_URL_HOST);
                $source_host = parse_url($source['url'], PHP_URL_HOST);

                foreach ($events as $event) {
                    $title = $event['title'] ?? 'Untitled Event';
                    if (is_array($title) && isset($title['rendered'])) {
                        $title = $title['rendered'];
                    }
                    $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');

                    if (!empty($search)) {
                        if (stripos($title, $search) === false) {
                            continue;
                        }
                    }

                    $start_date = $event['meta']['event_start_date'] ?? ($event['start_date'] ?? '');

                    if ($hide_past && !empty($start_date) && $start_date < $today) {
                        continue;
                    }

                    $permalink = $event['link'] ?? $event['permalink'] ?? (trailingslashit($source['url']) . 'event/' . ($event['slug'] ?? $event['id']));

                    $all_events[] = [
                        'id' => $event['id'] ?? 0,
                        'title' => $title,
                        'slug' => $event['slug'] ?? '',
                        'start_date' => $start_date,
                        'permalink' => $permalink,
                        'source' => [
                            'type' => 'external',
                            'id' => $source['id'],
                            'name' => $source_name,
                            'url' => $source_host,
                        ],
                    ];
                }
            } catch (\Exception $e) {
                error_log('Error searching external source ' . $source['url'] . ': ' . $e->getMessage());
                continue;
            }
        }

        // Sort all events by start_date
        usort($all_events, function($a, $b) {
            $dateA = $a['start_date'] ?? '';
            $dateB = $b['start_date'] ?? '';
            return strcmp($dateA, $dateB);
        });

        // Calculate pagination
        $total = count($all_events);
        $total_pages = ceil($total / $per_page);
        $offset = ($page - 1) * $per_page;

        $paginated_events = array_slice($all_events, $offset, $per_page);

        return new \WP_REST_Response([
            'events' => $paginated_events,
            'total' => $total,
            'total_pages' => $total_pages,
            'page' => $page,
            'per_page' => $per_page,
        ]);
    }

    /**
     * Get event by ID
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public static function get_event_by_id($request) {
        $id = intval($request['id']);
        $post = get_post($id);

        if (!$post || $post->post_type !== 'mayo_event' || $post->post_status !== 'publish') {
            return new \WP_Error('not_found', 'Event not found', ['status' => 404]);
        }

        return new \WP_REST_Response([
            'id' => $post->ID,
            'title' => html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
            'start_date' => get_post_meta($post->ID, 'event_start_date', true),
            'end_date' => get_post_meta($post->ID, 'event_end_date', true),
            'start_time' => get_post_meta($post->ID, 'event_start_time', true),
            'end_time' => get_post_meta($post->ID, 'event_end_time', true),
            'permalink' => get_permalink($post->ID),
            'edit_link' => get_edit_post_link($post->ID, 'raw'),
        ]);
    }

    /**
     * Format event data for API response
     *
     * @param int|\WP_Post $post_id Post ID or post object
     * @return array|null
     */
    public static function format_event($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return null;
        }

        $data = [
            'id' => $post->ID,
            'title' => [
                'rendered' => html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8')
            ],
            'content' => [
                'rendered' => apply_filters('the_content', $post->post_content)
            ],
            'link' => get_permalink($post),
            'meta' => [
                'event_start_date' => '',
                'event_end_date' => '',
                'event_start_time' => '',
                'event_end_time' => '',
                'timezone' => '',
                'event_type' => '',
                'service_body' => '',
                'location_name' => '',
                'location_address' => '',
                'location_details' => '',
            ]
        ];

        // Get featured image
        if (has_post_thumbnail($post)) {
            $data['featured_image'] = get_the_post_thumbnail_url($post, 'large');
        }

        // Get event meta fields
        $meta_fields = [
            'event_type',
            'event_start_date',
            'event_end_date',
            'event_start_time',
            'event_end_time',
            'timezone',
            'recurring_pattern',
            'location_name',
            'location_address',
            'location_details',
            'service_body',
        ];

        foreach ($meta_fields as $field) {
            $value = get_post_meta($post->ID, $field, true);
            if ($value) {
                $data['meta'][$field] = $value;
            }
        }

        // Get categories and tags
        $data['categories'] = TaxonomyQuery::get_terms($post, 'category');
        $data['tags'] = TaxonomyQuery::get_terms($post, 'post_tag');

        // Get linked announcements
        $data['linked_announcements'] = Announcement::get_announcements_for_event($post->ID);

        return $data;
    }

    /**
     * Build email content for event notifications
     *
     * @param array $params Event parameters
     * @param string $subject_template Subject template with %s placeholders
     * @param string $view_url URL for viewing the event
     * @return array Array with 'subject' and 'message' keys
     */
    public static function build_event_email_content($params, $subject_template, $view_url) {
        // Get service body name using helper
        $service_body_id = sanitize_text_field($params['service_body']);
        $service_body_name = ServiceBodyLookup::get_name($service_body_id);

        // Format recurring pattern information
        $recurring_info = '';
        if (!empty($params['recurring_pattern'])) {
            $recurring_pattern = is_string($params['recurring_pattern'])
                ? json_decode($params['recurring_pattern'], true)
                : $params['recurring_pattern'];

            if (is_array($recurring_pattern) && isset($recurring_pattern['type']) && $recurring_pattern['type'] !== 'none') {
                $recurring_info = "\nRecurring Pattern: ";
                switch ($recurring_pattern['type']) {
                    case 'daily':
                        $recurring_info .= "Daily";
                        if (isset($recurring_pattern['interval']) && $recurring_pattern['interval'] > 1) {
                            $recurring_info .= " (every " . $recurring_pattern['interval'] . " days)";
                        }
                        break;
                    case 'weekly':
                        $recurring_info .= "Weekly";
                        if (isset($recurring_pattern['interval']) && $recurring_pattern['interval'] > 1) {
                            $recurring_info .= " (every " . $recurring_pattern['interval'] . " weeks)";
                        }
                        if (!empty($recurring_pattern['weekdays'])) {
                            $weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                            $selected_days = array_map(function($day) use ($weekdays) {
                                return $weekdays[$day] ?? '';
                            }, $recurring_pattern['weekdays']);
                            $recurring_info .= " on " . implode(', ', array_filter($selected_days));
                        }
                        break;
                    case 'monthly':
                        $recurring_info .= "Monthly";
                        if (isset($recurring_pattern['interval']) && $recurring_pattern['interval'] > 1) {
                            $recurring_info .= " (every " . $recurring_pattern['interval'] . " months)";
                        }
                        if (isset($recurring_pattern['monthlyType']) && $recurring_pattern['monthlyType'] === 'date' && isset($recurring_pattern['monthlyDate'])) {
                            $recurring_info .= " on day " . $recurring_pattern['monthlyDate'];
                        } elseif (isset($recurring_pattern['monthlyWeekday'])) {
                            $recurring_info .= " on " . $recurring_pattern['monthlyWeekday'];
                        }
                        break;
                }

                if (!empty($recurring_pattern['endDate'])) {
                    $recurring_info .= " until " . $recurring_pattern['endDate'];
                }
            }
        }

        // Format location information
        $location_info = '';
        if (!empty($params['location_name']) || !empty($params['location_address']) || !empty($params['location_details'])) {
            $location_info = "\nLocation:";
            if (!empty($params['location_name'])) {
                $location_info .= "\n  Name: " . sanitize_text_field($params['location_name']);
            }
            if (!empty($params['location_address'])) {
                $location_info .= "\n  Address: " . sanitize_text_field($params['location_address']);
            }
            if (!empty($params['location_details'])) {
                $location_info .= "\n  Details: " . sanitize_text_field($params['location_details']);
            }
        }

        // Format categories and tags
        $categories_info = '';
        if (!empty($params['categories'])) {
            $categories_info = "\nCategories: " . sanitize_text_field($params['categories']);
        }

        $tags_info = '';
        if (!empty($params['tags'])) {
            $tags_info = "\nTags: " . sanitize_text_field($params['tags']);
        }

        // Check for file attachments
        $file_names = FileUpload::get_uploaded_file_names();
        $attachments_info = '';
        if (!empty($file_names)) {
            $attachments_info = "\nAttachments: " . implode(', ', $file_names);
        }

        // Build subject and message
        $subject = sprintf($subject_template, sanitize_text_field($params['event_name']));

        $message_template = "Event Name: %s\n" .
            "Event Type: %s\n" .
            "Service Body: %s (ID: %s)\n" .
            "Start Date: %s\n" .
            "Start Time: %s\n" .
            "End Date: %s\n" .
            "End Time: %s\n" .
            "Timezone: %s\n" .
            "Contact Name: %s\n" .
            "Contact Email: %s%s%s%s%s%s\n\n" .
            "Description:\n%s\n\n" .
            "View the event: %s";

        $message = sprintf(
            $message_template,
            sanitize_text_field($params['event_name']),
            sanitize_text_field($params['event_type']),
            $service_body_name,
            $service_body_id,
            sanitize_text_field($params['event_start_date']),
            sanitize_text_field($params['event_start_time']),
            sanitize_text_field($params['event_end_date']),
            sanitize_text_field($params['event_end_time']),
            sanitize_text_field($params['timezone']),
            sanitize_text_field($params['contact_name']),
            sanitize_email($params['email']),
            $recurring_info,
            $location_info,
            $categories_info,
            $tags_info,
            $attachments_info,
            sanitize_textarea_field($params['description'] ?? ''),
            $view_url
        );

        return [
            'subject' => $subject,
            'message' => $message
        ];
    }

    /**
     * Send event submission email notification
     *
     * @param int $post_id Post ID
     * @param array $params Event parameters
     */
    private static function send_event_submission_email($post_id, $params) {
        $to = EmailNotification::get_notification_recipients();

        $subject_template = 'New Event Submission: %s';
        $view_url = admin_url('post.php?post=' . $post_id . '&action=edit');

        $email_content = self::build_event_email_content($params, $subject_template, $view_url);

        wp_mail($to, $email_content['subject'], $email_content['message']);
    }

    /**
     * Get local events with filtering
     *
     * @param array $params Query parameters
     * @return array
     */
    private static function get_local_events($params) {
        $is_archive = false;

        if (isset($params['archive'])) {
            $archive = sanitize_text_field(wp_unslash($params['archive']));
            if ($archive === 'true') {
                $is_archive = true;
            }
        }

        $status = isset($params['status']) ? sanitize_text_field(wp_unslash($params['status'])) : 'publish';
        $eventType = isset($params['event_type']) ? sanitize_text_field(wp_unslash($params['event_type'])) : '';
        $serviceBody = isset($params['service_body']) ? sanitize_text_field(wp_unslash($params['service_body'])) : '';
        $relation = isset($params['relation']) ? sanitize_text_field(wp_unslash($params['relation'])) : 'AND';
        $categories = isset($params['categories']) ? sanitize_text_field(wp_unslash($params['categories'])) : '';
        $categoryRelation = isset($params['category_relation']) ? strtoupper(sanitize_text_field(wp_unslash($params['category_relation']))) : 'OR';
        $tags = isset($params['tags']) ? sanitize_text_field(wp_unslash($params['tags'])) : '';
        $timezone = isset($params['timezone']) ? urldecode(sanitize_text_field(wp_unslash($params['timezone']))) : wp_timezone_string();

        $range_start = isset($params['start_date']) ? sanitize_text_field(wp_unslash($params['start_date'])) : null;
        $range_end = isset($params['end_date']) ? sanitize_text_field(wp_unslash($params['end_date'])) : null;
        $has_date_range = !empty($range_start) && !empty($range_end);

        $today = new DateTime('now', new \DateTimeZone($timezone));
        $today->setTime(0, 0, 0);

        $events = [];

        // Get non-recurring events
        $non_recurring_events = self::query_events($status, $eventType, $serviceBody, $relation, $categories, $categoryRelation, $tags, null);
        $events = array_merge($events, $non_recurring_events);

        // Get recurring events
        $recurring_meta_query = [
            [
                'key' => 'recurring_pattern',
                'compare' => 'EXISTS'
            ],
            [
                'key' => 'recurring_pattern',
                'value' => 'none',
                'compare' => '!='
            ]
        ];

        if (!empty($eventType)) {
            $recurring_meta_query[] = [
                'key' => 'event_type',
                'value' => $eventType,
                'compare' => '='
            ];
        }

        if (!empty($serviceBody)) {
            $service_bodies = array_map('trim', explode(',', $serviceBody));
            $recurring_meta_query[] = [
                'key' => 'service_body',
                'value' => $service_bodies,
                'compare' => 'IN'
            ];
        }

        $recurring_meta_query['relation'] = 'AND';

        $args = [
            'post_type' => 'mayo_event',
            'posts_per_page' => -1,
            'post_status' => $status,
            'meta_query' => $recurring_meta_query,
        ];

        $taxonomy_args = TaxonomyQuery::build_taxonomy_args($categories, $categoryRelation, $tags);
        $args = array_merge($args, $taxonomy_args);

        $recurring_posts = get_posts($args);

        foreach ($recurring_posts as $post) {
            try {
                $recurring_pattern = get_post_meta($post->ID, 'recurring_pattern', true);
                $start_date = get_post_meta($post->ID, 'event_start_date', true);

                if (!$recurring_pattern || !$start_date || $recurring_pattern['type'] === 'none') {
                    continue;
                }

                $recurring_events = self::generate_recurring_events($post, $recurring_pattern);

                $filter_range_start = $has_date_range ? new DateTime($range_start) : null;
                $filter_range_end = $has_date_range ? new DateTime($range_end) : null;
                if ($filter_range_start) $filter_range_start->setTime(0, 0, 0);
                if ($filter_range_end) $filter_range_end->setTime(23, 59, 59);

                $filtered_recurring_events = array_filter($recurring_events, function($event) use ($today, $is_archive, $has_date_range, $filter_range_start, $filter_range_end) {
                    if (!isset($event['meta']['event_start_date']) || empty($event['meta']['event_start_date'])) {
                        return false;
                    }

                    try {
                        $start_date_str = $event['meta']['event_start_date'];
                        $start_date = new DateTime($start_date_str);
                        $start_date->setTime(0, 0, 0);

                        $end_date_str = isset($event['meta']['event_end_date']) && !empty($event['meta']['event_end_date'])
                            ? $event['meta']['event_end_date']
                            : $start_date_str;
                        $end_date = new DateTime($end_date_str);
                        $end_date->setTime(23, 59, 59);

                        if ($has_date_range) {
                            return $start_date <= $filter_range_end && $end_date >= $filter_range_start;
                        }

                        if ($is_archive) {
                            return $end_date < $today;
                        } else {
                            return $start_date >= $today || $end_date >= $today;
                        }
                    } catch (\Exception $e) {
                        return false;
                    }
                });

                if (count($filtered_recurring_events) > 0) {
                    $events = array_merge($events, $filtered_recurring_events);
                }
            } catch (\Exception $e) {
                error_log('Mayo Events API: Error processing recurring event: ' . $e->getMessage());
            }
        }

        // Apply date filtering
        $range_start_dt = $has_date_range ? new DateTime($range_start) : null;
        $range_end_dt = $has_date_range ? new DateTime($range_end) : null;
        if ($range_start_dt) $range_start_dt->setTime(0, 0, 0);
        if ($range_end_dt) $range_end_dt->setTime(23, 59, 59);

        $events = array_filter($events, function($event) use ($today, $is_archive, $has_date_range, $range_start_dt, $range_end_dt) {
            if (!isset($event['meta']['event_start_date']) || empty($event['meta']['event_start_date'])) {
                return false;
            }

            try {
                $start_date_str = $event['meta']['event_start_date'];
                $start_date = new DateTime($start_date_str);
                $start_date->setTime(0, 0, 0);

                $end_date_str = isset($event['meta']['event_end_date']) && !empty($event['meta']['event_end_date'])
                    ? $event['meta']['event_end_date']
                    : $start_date_str;
                $end_date = new DateTime($end_date_str);
                $end_date->setTime(23, 59, 59);

                if ($has_date_range) {
                    return $start_date <= $range_end_dt && $end_date >= $range_start_dt;
                }

                if ($is_archive) {
                    return $end_date < $today;
                } else {
                    return $start_date >= $today || $end_date >= $today;
                }
            } catch (\Exception $e) {
                return false;
            }
        });

        return $events;
    }

    /**
     * Query events with given parameters
     *
     * @param string $status Post status
     * @param string $eventType Event type filter
     * @param string $serviceBody Service body filter
     * @param string $relation Meta query relation
     * @param string $categories Category filter
     * @param string $categoryRelation Category relation
     * @param string $tags Tags filter
     * @param string|null $min_date Minimum date filter
     * @return array
     */
    private static function query_events($status, $eventType, $serviceBody, $relation, $categories, $categoryRelation, $tags, $min_date = null) {
        $meta_query = [];

        if (!empty($eventType)) {
            $meta_query[] = [
                'key' => 'event_type',
                'value' => $eventType,
                'compare' => '='
            ];
        }

        if (!empty($serviceBody)) {
            $service_bodies = array_map('trim', explode(',', $serviceBody));
            $meta_query[] = [
                'key' => 'service_body',
                'value' => $service_bodies,
                'compare' => 'IN'
            ];
        }

        if (!is_null($min_date)) {
            $meta_query[] = [
                'key' => 'event_start_date',
                'value' => $min_date,
                'compare' => '>=',
                'type' => 'DATE'
            ];
        }

        if (count($meta_query) > 0) {
            $meta_query['relation'] = $relation;
        }

        $args = [
            'post_type' => 'mayo_event',
            'posts_per_page' => -1,
            'post_status' => $status,
            'meta_query' => $meta_query,
        ];

        $taxonomy_args = TaxonomyQuery::build_taxonomy_args($categories, $categoryRelation, $tags);
        $args = array_merge($args, $taxonomy_args);

        $posts = get_posts($args);
        $events = [];

        foreach ($posts as $post) {
            try {
                $events[] = self::format_event($post);
            } catch (\Exception $e) {
                error_log('Mayo Events API: Error formatting event: ' . $e->getMessage());
            }
        }

        if (!is_null($min_date)) {
            $today = new DateTime($min_date);
            $today->setTime(0, 0, 0);

            $events = array_filter($events, function($event) use ($today) {
                if (!isset($event['meta']['event_start_date']) || empty($event['meta']['event_start_date'])) {
                    return false;
                }

                try {
                    $start_date_str = $event['meta']['event_start_date'];
                    $start_date = new DateTime($start_date_str);
                    $start_date->setTime(0, 0, 0);

                    if ($start_date >= $today) {
                        return true;
                    }

                    if (isset($event['meta']['event_end_date']) && !empty($event['meta']['event_end_date'])) {
                        $end_date_str = $event['meta']['event_end_date'];
                        $end_date = new DateTime($end_date_str);
                        $end_date->setTime(23, 59, 59);
                        return $end_date >= $today;
                    }

                    return false;
                } catch (\Exception $e) {
                    return false;
                }
            });
        }

        return $events;
    }

    /**
     * Fetch events from external source
     *
     * @param array $source External source configuration
     * @return array Array with 'events' and 'source' keys
     */
    private static function fetch_external_events($source) {
        try {
            $params = [];
            if (!empty($source['event_type'])) $params['event_type'] = $source['event_type'];
            if (!empty($source['service_body'])) $params['service_body'] = $source['service_body'];
            if (!empty($source['categories'])) $params['categories'] = $source['categories'];
            if (!empty($source['tags'])) $params['tags'] = $source['tags'];

            if (isset($_GET['archive'])) $params['archive'] = $_GET['archive'];
            if (isset($_GET['timezone'])) $params['timezone'] = $_GET['timezone'];
            if (isset($_GET['tags'])) $params['tags'] = sanitize_text_field($_GET['tags']);

            $params['per_page'] = 100;

            $url = add_query_arg($params, trailingslashit($source['url']) . 'wp-json/event-manager/v1/events');

            $response = wp_remote_get($url, [
                'timeout' => 15,
                'sslverify' => true
            ]);

            if (is_wp_error($response)) {
                error_log('External Events Error: ' . $response->get_error_message());
                return ['events' => [], 'source' => null];
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            $events = isset($data['events']) ? $data['events'] : $data;

            if (!is_array($events)) return ['events' => [], 'source' => null];

            // Fetch service bodies once for this source
            $service_bodies = self::fetch_external_service_bodies($source);

            // Build source info (with service bodies at source level, not per-event)
            $source_info = [
                'id' => $source['id'],
                'url' => parse_url($source['url'], PHP_URL_HOST),
                'name' => $source['name'] ?? parse_url($source['url'], PHP_URL_HOST),
                'service_bodies' => $service_bodies
            ];

            // Events only reference source by ID (no duplication of service bodies)
            foreach ($events as &$event) {
                $event['source_id'] = $source['id'];
            }

            return [
                'events' => $events,
                'source' => $source_info
            ];
        } catch (\Exception $e) {
            error_log('External Events Error: ' . $e->getMessage());
            return ['events' => [], 'source' => null];
        }
    }

    /**
     * Fetch service bodies from external source
     *
     * @param array $source External source configuration
     * @return array
     */
    private static function fetch_external_service_bodies($source) {
        try {
            $settings_url = trailingslashit($source['url']) . 'wp-json/event-manager/v1/settings';

            $settings_response = wp_remote_get($settings_url, [
                'timeout' => 15,
                'sslverify' => true
            ]);

            if (is_wp_error($settings_response)) {
                return [];
            }

            $settings_body = wp_remote_retrieve_body($settings_response);
            $settings = json_decode($settings_body, true);

            if (empty($settings['bmlt_root_server'])) {
                return [];
            }

            $bmlt_url = add_query_arg('switcher', 'GetServiceBodies', trailingslashit($settings['bmlt_root_server']) . 'client_interface/json/');

            $bmlt_response = wp_remote_get($bmlt_url, [
                'timeout' => 15,
                'sslverify' => true
            ]);

            if (is_wp_error($bmlt_response)) {
                return [];
            }

            $bmlt_body = wp_remote_retrieve_body($bmlt_response);
            $service_bodies = json_decode($bmlt_body, true);

            if (!is_array($service_bodies)) {
                return [];
            }

            return $service_bodies;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Filter external events by tags (client-side filtering)
     *
     * This is needed because external sources may not properly filter by tags,
     * so we filter the returned events client-side to match the requested tags.
     *
     * @param array $events Events array from external source
     * @param string $tags_filter Comma-separated tag slugs (prefix with '-' to exclude)
     * @return array Filtered events
     */
    private static function filter_external_events_by_tags($events, $tags_filter) {
        if (empty($tags_filter) || empty($events)) {
            return $events;
        }

        $tag_filter = Helpers\TaxonomyQuery::parse_taxonomy_filter($tags_filter);
        $include_tags = !empty($tag_filter['include']) ? array_map('trim', explode(',', $tag_filter['include'])) : [];
        $exclude_tags = !empty($tag_filter['exclude']) ? array_map('trim', explode(',', $tag_filter['exclude'])) : [];

        return array_filter($events, function($event) use ($include_tags, $exclude_tags) {
            // Get event tag slugs
            $event_tags = isset($event['tags']) ? array_column($event['tags'], 'slug') : [];

            // Check exclusions first - if event has any excluded tag, filter it out
            if (!empty($exclude_tags)) {
                foreach ($exclude_tags as $exclude_tag) {
                    if (in_array($exclude_tag, $event_tags)) {
                        return false;
                    }
                }
            }

            // Check inclusions - event must have at least one of the included tags
            if (!empty($include_tags)) {
                foreach ($include_tags as $include_tag) {
                    if (in_array($include_tag, $event_tags)) {
                        return true;
                    }
                }
                return false;
            }

            return true;
        });
    }

    /**
     * Generate recurring event instances
     *
     * @param \WP_Post $post Post object
     * @param array $pattern Recurring pattern
     * @return array
     */
    private static function generate_recurring_events($post, $pattern) {
        $weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $events = [];
        $start = new DateTime(get_post_meta($post->ID, 'event_start_date', true));
        $end = !empty($pattern['endDate']) ? new DateTime($pattern['endDate']) : null;

        $skipped_occurrences = get_post_meta($post->ID, 'skipped_occurrences', true) ?: [];

        $max_events = 0;
        if ($pattern['type'] === 'daily') {
            $max_events = 365 * 5;
        } elseif ($pattern['type'] === 'weekly') {
            $max_events = 52 * 5;
        } elseif ($pattern['type'] === 'monthly') {
            $max_events = 12 * 5;
        }

        if ($pattern['type'] === 'monthly') {
            $current = clone $start;
            $interval = $pattern['interval'];
            $current->modify('first day of +' . $interval . ' month');

            while (($end === null || $current <= $end) && count($events) < $max_events) {
                $current_year = (int)$current->format('Y');
                $current_month = (int)$current->format('m');

                if (isset($pattern['monthlyType']) && $pattern['monthlyType'] === 'date') {
                    $day = (int)$pattern['monthlyDate'];
                    $days_in_month = (int)$current->format('t');
                    if ($day > $days_in_month) {
                        $current->modify('first day of +' . $interval . ' month');
                        continue;
                    }
                    $current->setDate($current_year, $current_month, $day);
                } else {
                    if (!isset($pattern['monthlyWeekday'])) {
                        $current->modify('first day of +' . $interval . ' month');
                        continue;
                    }

                    list($week, $weekday) = explode(',', $pattern['monthlyWeekday']);
                    $week = (int)$week;
                    $weekday = (int)$weekday;

                    $current->setDate($current_year, $current_month, 1);

                    if ($week > 0) {
                        $current->modify('first ' . $weekdays[$weekday] . ' of this month');
                        if ($week > 1) {
                            $current->modify('+' . ($week - 1) . ' weeks');
                        }
                    } else {
                        $current->modify('last ' . $weekdays[$weekday] . ' of this month');
                    }
                }

                if ($current <= $end || $end === null) {
                    $current_date = $current->format('Y-m-d');
                    if (!in_array($current_date, $skipped_occurrences)) {
                        $events[] = self::format_recurring_event($post, clone $current);
                    }
                }

                $current->setDate($current_year, $current_month, 1);
                $current->modify('+' . $interval . ' month');
            }
        } else {
            $interval = new DateInterval('P' . $pattern['interval'] .
                ($pattern['type'] === 'daily' ? 'D' :
                    ($pattern['type'] === 'weekly' ? 'W' : 'M')));

            $current = clone $start;
            $current->add($interval);

            while (($end === null || $current <= $end) && count($events) < $max_events) {
                if ($pattern['type'] === 'weekly' && !empty($pattern['weekdays'])) {
                    $interval_start = clone $current;
                    $interval_end = clone $current;
                    $interval_end->add($interval);

                    while ($interval_start < $interval_end && count($events) < $max_events) {
                        $current_day = $interval_start->format('w');

                        if (in_array($current_day, $pattern['weekdays'])) {
                            $current_date = $interval_start->format('Y-m-d');
                            if (!in_array($current_date, $skipped_occurrences)) {
                                $events[] = self::format_recurring_event($post, clone $interval_start);
                            }
                        }

                        $interval_start->modify('+1 day');
                    }
                } else {
                    $current_date = $current->format('Y-m-d');
                    if (!in_array($current_date, $skipped_occurrences)) {
                        $events[] = self::format_recurring_event($post, $current);
                    }
                }

                $current->add($interval);
            }
        }

        return $events;
    }

    /**
     * Format a recurring event instance
     *
     * @param \WP_Post $post Post object
     * @param DateTime $date Event date
     * @return array
     */
    private static function format_recurring_event($post, $date) {
        $event = self::format_event($post);
        $event['meta']['event_start_date'] = $date->format('Y-m-d');

        $original_start_date = get_post_meta($post->ID, 'event_start_date', true);
        $original_end_date = get_post_meta($post->ID, 'event_end_date', true);

        if ($original_start_date && $original_end_date) {
            $original_start = new DateTime($original_start_date);
            $original_end = new DateTime($original_end_date);
            $duration = $original_start->diff($original_end);

            $new_end_date = clone $date;
            $new_end_date->add($duration);
            $event['meta']['event_end_date'] = $new_end_date->format('Y-m-d');
        } else {
            $event['meta']['event_end_date'] = $date->format('Y-m-d');
        }

        $event['recurring'] = true;
        return $event;
    }
}