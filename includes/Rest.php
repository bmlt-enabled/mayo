<?php
namespace BmltEnabled\Mayo;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use DateTime;
use DateInterval;
use Imagick;

class Rest {
    public static function init() {
        add_action('rest_api_init', function () {
            register_rest_route('event-manager/v1', '/submit-event', [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'bmltenabled_mayo_submit_event'],
                'permission_callback' => function() {
                    // Allow unauthenticated users to submit events
                    return true;
                },
            ]);

            register_rest_route('event-manager/v1', '/events', [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'bmltenabled_mayo_get_events'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('event-manager/v1', '/settings', [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'bmltenabled_mayo_get_settings'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('event-manager/v1', '/settings', [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'bmltenabled_mayo_update_settings'],
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                }
            ]);

            register_rest_route('event-manager/v1', '/event/(?P<slug>[a-zA-Z0-9-]+)', [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'bmltenabled_mayo_get_event_details'],
                'permission_callback' => '__return_true', // Adjust permissions as needed
            ]);
        });
    }

    public static function bmltenabled_mayo_submit_event($request) {
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

        // Add all event metadata first
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
            // Decode the JSON string if it's a string
            $recurring_pattern = is_string($params['recurring_pattern']) 
                ? json_decode($params['recurring_pattern'], true) 
                : $params['recurring_pattern'];
            
            // Ensure recurring_pattern is an array
            if (!is_array($recurring_pattern)) {
                $recurring_pattern = [];
            }
            
            // Sanitize the recurring pattern data
            $sanitized_pattern = [
                'type' => isset($recurring_pattern['type']) ? sanitize_text_field($recurring_pattern['type']) : 'none',
                'interval' => isset($recurring_pattern['interval']) ? intval($recurring_pattern['interval']) : 1,
                'endDate' => isset($recurring_pattern['endDate']) ? sanitize_text_field($recurring_pattern['endDate']) : null
            ];
            
            // Add type-specific fields
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

        // Handle file uploads
        $attachment_ids = [];
        if (!empty($_FILES)) {
            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }
            if (!function_exists('wp_generate_attachment_metadata')) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
            }
            if (!function_exists('wp_insert_attachment')) {
                require_once(ABSPATH . 'wp-admin/includes/media.php');
            }

            foreach ($_FILES as $file_key => $file) {
                // Skip empty files
                if (empty($file['name']) || $file['size'] <= 0) {
                    continue;
                }
                
                $uploaded_file = wp_handle_upload($file, array('test_form' => false));
                
                if (isset($uploaded_file['error'])) {
                    error_log('Upload error: ' . $uploaded_file['error']);
                    continue;
                }
                
                // Prepare attachment data
                $attachment = array(
                    'guid'           => $uploaded_file['url'],
                    'post_mime_type' => $uploaded_file['type'],
                    'post_title'     => sanitize_file_name(basename($uploaded_file['file'])),
                    'post_content'   => '',
                    'post_status'    => 'inherit'
                );
                
                // Insert attachment
                $attachment_id = wp_insert_attachment($attachment, $uploaded_file['file'], $post_id);
                if (!is_wp_error($attachment_id)) {
                    $attachment_data = wp_generate_attachment_metadata($attachment_id, $uploaded_file['file']);
                    wp_update_attachment_metadata($attachment_id, $attachment_data);
                    $attachment_ids[] = $attachment_id;

                    // Handle image files
                    if (strpos($uploaded_file['type'], 'image/') === 0) {
                        set_post_thumbnail($post_id, $attachment_id);
                    }
                }
            }
        }

        // Send email notification
        self::send_event_submission_email($post_id, $params);

        // Format response data
        $formatted_event = self::format_event($post_id);
        
        return new \WP_REST_Response($formatted_event, 200);
    }

    /**
     * Build email content for event notifications
     * 
     * @param array $params Event parameters
     * @param int $post_id Post ID
     * @param string $subject_template Subject template with %s placeholders
     * @param string $view_url URL for viewing the event
     * @return array Array with 'subject' and 'message' keys
     */
    public static function build_event_email_content($params, $subject_template, $view_url) {
        $settings = get_option('mayo_settings', []);
        
        // Get service body name
        $service_body_name = 'Unknown';
        $service_body_id = sanitize_text_field($params['service_body']);
        
        if ($service_body_id === '0') {
            $service_body_name = 'Unaffiliated';
        } elseif (!empty($service_body_id)) {
            $bmlt_root_server = $settings['bmlt_root_server'] ?? '';
            if (!empty($bmlt_root_server)) {
                $response = wp_remote_get($bmlt_root_server . '/client_interface/json/?switcher=GetServiceBodies');
                if (!is_wp_error($response)) {
                    $service_bodies = json_decode(wp_remote_retrieve_body($response), true);
                    if (is_array($service_bodies)) {
                        foreach ($service_bodies as $body) {
                            if ($body['id'] == $service_body_id) {
                                $service_body_name = $body['name'];
                                break;
                            }
                        }
                    }
                }
            }
        }
        
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
        $attachments_info = '';
        if (!empty($_FILES)) {
            $file_names = [];
            foreach ($_FILES as $file_key => $file) {
                if (!empty($file['name'])) {
                    $file_names[] = $file['name'];
                }
            }
            if (!empty($file_names)) {
                $attachments_info = "\nAttachments: " . implode(', ', $file_names);
            }
        }
        
        // Build subject and message using provided templates
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

    private static function send_event_submission_email($post_id, $params) {
        // Get notification email from settings
        $settings = get_option('mayo_settings', []);
        $notification_email = isset($settings['notification_email']) && !empty($settings['notification_email']) 
            ? $settings['notification_email'] 
            : get_option('admin_email'); // Fallback to admin email if not set
        
        // Process multiple email addresses
        $to = [];
        if (strpos($notification_email, ',') !== false || strpos($notification_email, ';') !== false) {
            // Split by comma or semicolon and trim each email
            $emails = preg_split('/[,;]/', $notification_email);
            foreach ($emails as $email) {
                $email = trim($email);
                if (is_email($email)) {
                    $to[] = $email;
                }
            }
        } else {
            // Single email
            $to = $notification_email;
        }
        
        // If no valid emails found, use admin email
        if (empty($to)) {
            $to = get_option('admin_email');
        }
        
        // Build email content using shared method
        $subject_template = 'New Event Submission: %s';
        $view_url = admin_url('post.php?post=' . $post_id . '&action=edit');
        
        $email_content = self::build_event_email_content($params, $subject_template, $view_url);
        
        wp_mail($to, $email_content['subject'], $email_content['message']);
    }

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
        $tags = isset($params['tags']) ? sanitize_text_field(wp_unslash($params['tags'])) : '';
        $timezone = isset($params['timezone']) ? urldecode(sanitize_text_field(wp_unslash($params['timezone']))) : wp_timezone_string();

        // Date range parameters for calendar view
        $range_start = isset($params['start_date']) ? sanitize_text_field(wp_unslash($params['start_date'])) : null;
        $range_end = isset($params['end_date']) ? sanitize_text_field(wp_unslash($params['end_date'])) : null;
        $has_date_range = !empty($range_start) && !empty($range_end);

        $today = new DateTime('now', new \DateTimeZone($timezone));
        $today->setTime(0, 0, 0); // Start of today
        $current_date = $today->format('Y-m-d');
        

        
        $events = [];

        // 1. Get all non-recurring events (we'll filter them later based on archive mode)
        $non_recurring_events = self::query_events($status, $eventType, $serviceBody, $relation, $categories, $tags, null);

        $events = array_merge($events, $non_recurring_events);

        // 2. Get all events with recurring patterns, regardless of start date
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

        // Add event type filter if specified
        if (!empty($eventType)) {
            $recurring_meta_query[] = [
                'key' => 'event_type',
                'value' => $eventType,
                'compare' => '='
            ];
        }

        // Add service body filter if specified
        if (!empty($serviceBody)) {
            $service_bodies = array_map('trim', explode(',', $serviceBody));
            $recurring_meta_query[] = [
                'key' => 'service_body',
                'value' => $service_bodies,
                'compare' => 'IN'
            ];
        }

        // Always set AND relation for this query
        $recurring_meta_query['relation'] = 'AND';

        $args = [
            'post_type' => 'mayo_event',
            'posts_per_page' => -1,
            'post_status' => $status,
            'meta_query' => $recurring_meta_query,
        ];

        // Merge in taxonomy args (handles both include and exclude with '-' prefix)
        $taxonomy_args = self::build_taxonomy_args($categories, $tags);
        $args = array_merge($args, $taxonomy_args);

        $recurring_posts = get_posts($args);

        // Process recurring posts and generate occurrences
        foreach ($recurring_posts as $post) {
            try {
                $recurring_pattern = get_post_meta($post->ID, 'recurring_pattern', true);
                $start_date = get_post_meta($post->ID, 'event_start_date', true);

                if (!$recurring_pattern || !$start_date || $recurring_pattern['type'] === 'none') {
                    continue;
                }

                $recurring_events = self::generate_recurring_events($post, $recurring_pattern);

                // Filter recurring events based on date range or archive mode
                // Prepare date range objects for filtering if needed
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

                        // Determine the event's end date
                        $end_date_str = isset($event['meta']['event_end_date']) && !empty($event['meta']['event_end_date'])
                            ? $event['meta']['event_end_date']
                            : $start_date_str;
                        $end_date = new DateTime($end_date_str);
                        $end_date->setTime(23, 59, 59);

                        // If date range is specified (calendar view), filter by that range
                        if ($has_date_range) {
                            // Include event if it overlaps with the range at all
                            return $start_date <= $filter_range_end && $end_date >= $filter_range_start;
                        }

                        if ($is_archive) {
                            // Archive mode: only show events that have completely ended
                            return $end_date < $today;
                        } else {
                            // Normal mode: show events that are ongoing or in the future
                            return $start_date >= $today || $end_date >= $today;
                        }
                    } catch (\Exception $e) {
                        error_log('Mayo Events API: Error parsing recurring event date: ' . $e->getMessage());
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
        
        // Apply date filtering based on archive mode or date range
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

                // Determine the event's end date (use end_date if available, otherwise use start_date)
                $end_date_str = isset($event['meta']['event_end_date']) && !empty($event['meta']['event_end_date'])
                    ? $event['meta']['event_end_date']
                    : $start_date_str;
                $end_date = new DateTime($end_date_str);
                $end_date->setTime(23, 59, 59);

                // If date range is specified (calendar view), filter by that range
                if ($has_date_range) {
                    // Include event if it overlaps with the range at all
                    // Event overlaps if: event_start <= range_end AND event_end >= range_start
                    return $start_date <= $range_end_dt && $end_date >= $range_start_dt;
                }

                if ($is_archive) {
                    // Archive mode: only show events that have completely ended (end date is in the past)
                    return $end_date < $today;
                } else {
                    // Normal mode: show events that are ongoing or in the future
                    // (start date is today or later, OR end date is today or later)
                    return $start_date >= $today || $end_date >= $today;
                }
            } catch (\Exception $e) {
                error_log('Mayo Events API: Error parsing event date: ' . $e->getMessage());
                return false;
            }
        });

        return $events;
    }
    
    /**
     * Helper method to parse categories/tags string and separate includes from excludes
     * Items prefixed with '-' are excluded, others are included
     */
    private static function parse_taxonomy_filter($filter_string) {
        if (empty($filter_string)) {
            return ['include' => '', 'exclude' => ''];
        }

        $items = array_map('trim', explode(',', $filter_string));
        $include = [];
        $exclude = [];

        foreach ($items as $item) {
            if (empty($item)) {
                continue;
            }
            if (strpos($item, '-') === 0) {
                // Remove the leading '-' and add to exclude list
                $exclude[] = substr($item, 1);
            } else {
                $include[] = $item;
            }
        }

        return [
            'include' => implode(',', $include),
            'exclude' => implode(',', $exclude)
        ];
    }

    /**
     * Helper method to build taxonomy query args for categories and tags
     * Handles both inclusion and exclusion (items prefixed with '-')
     */
    private static function build_taxonomy_args($categories, $tags) {
        $cat_filter = self::parse_taxonomy_filter($categories);
        $tag_filter = self::parse_taxonomy_filter($tags);

        $args = [];
        $tax_query = [];

        // Handle category inclusion
        if (!empty($cat_filter['include'])) {
            $args['category_name'] = $cat_filter['include'];
        }

        // Handle category exclusion via tax_query
        if (!empty($cat_filter['exclude'])) {
            $exclude_cat_slugs = array_map('trim', explode(',', $cat_filter['exclude']));
            $exclude_cat_ids = [];
            foreach ($exclude_cat_slugs as $slug) {
                $term = get_term_by('slug', $slug, 'category');
                if ($term) {
                    $exclude_cat_ids[] = $term->term_id;
                }
            }
            if (!empty($exclude_cat_ids)) {
                $tax_query[] = [
                    'taxonomy' => 'category',
                    'field' => 'term_id',
                    'terms' => $exclude_cat_ids,
                    'operator' => 'NOT IN'
                ];
            }
        }

        // Handle tag inclusion
        if (!empty($tag_filter['include'])) {
            $args['tag'] = $tag_filter['include'];
        }

        // Handle tag exclusion via tax_query
        if (!empty($tag_filter['exclude'])) {
            $exclude_tag_slugs = array_map('trim', explode(',', $tag_filter['exclude']));
            $exclude_tag_ids = [];
            foreach ($exclude_tag_slugs as $slug) {
                $term = get_term_by('slug', $slug, 'post_tag');
                if ($term) {
                    $exclude_tag_ids[] = $term->term_id;
                }
            }
            if (!empty($exclude_tag_ids)) {
                $tax_query[] = [
                    'taxonomy' => 'post_tag',
                    'field' => 'term_id',
                    'terms' => $exclude_tag_ids,
                    'operator' => 'NOT IN'
                ];
            }
        }

        // Add tax_query if we have any exclusions
        if (!empty($tax_query)) {
            $tax_query['relation'] = 'AND';
            $args['tax_query'] = $tax_query;
        }

        return $args;
    }

    /**
     * Helper method to query events with the given parameters
     */
    private static function query_events($status, $eventType, $serviceBody, $relation, $categories, $tags, $min_date = null) {
        $meta_query = [];

        // Handle event type
        if (!empty($eventType)) {
            $meta_query[] = [
                'key' => 'event_type',
                'value' => $eventType,
                'compare' => '='
            ];
        }

        // Handle service body
        if (!empty($serviceBody)) {
            $service_bodies = array_map('trim', explode(',', $serviceBody));
            $meta_query[] = [
                'key' => 'service_body',
                'value' => $service_bodies,
                'compare' => 'IN'
            ];
        }

        // Add date filter if specified
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

        // Build base args
        $args = [
            'post_type' => 'mayo_event',
            'posts_per_page' => -1,
            'post_status' => $status,
            'meta_query' => $meta_query,
        ];

        // Merge in taxonomy args (handles both include and exclude with '-' prefix)
        $taxonomy_args = self::build_taxonomy_args($categories, $tags);
        $args = array_merge($args, $taxonomy_args);

        // Get posts with error handling
        $posts = get_posts($args);
        $events = [];
        
        foreach ($posts as $post) {
            try {
                $events[] = self::format_event($post);
            } catch (\Exception $e) {
                error_log('Mayo Events API: Error formatting event: ' . $e->getMessage());
            }
        }
        
        // Additional filtering for multi-day events that might be missed by the initial query
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
                    
                    // Check if start date is in the future
                    if ($start_date >= $today) {
                        return true;
                    }
                    
                    // If start date is in the past, check if end date is in the future
                    if (isset($event['meta']['event_end_date']) && !empty($event['meta']['event_end_date'])) {
                        $end_date_str = $event['meta']['event_end_date'];
                        $end_date = new DateTime($end_date_str);
                        $end_date->setTime(23, 59, 59);
                        return $end_date >= $today;
                    }
                    
                    return false;
                } catch (\Exception $e) {
                    error_log('Mayo Events API: Error parsing event date: ' . $e->getMessage());
                    return false;
                }
            });
        }
        
        return $events;
    }

    private static function fetch_external_events($source) {
        try {
            // Build query parameters
            $params = [];
            if (!empty($source['event_type'])) $params['event_type'] = $source['event_type'];
            if (!empty($source['service_body'])) $params['service_body'] = $source['service_body'];
            if (!empty($source['categories'])) $params['categories'] = $source['categories'];
            if (!empty($source['tags'])) $params['tags'] = $source['tags'];
            
            // Pass archive and timezone parameters if they exist in the original request
            if (isset($_GET['archive'])) $params['archive'] = $_GET['archive'];
            if (isset($_GET['timezone'])) $params['timezone'] = $_GET['timezone'];

            $params['per_page'] = 100;

            // Build URL with parameters
            $url = add_query_arg($params, trailingslashit($source['url']) . 'wp-json/event-manager/v1/events');

            $response = wp_remote_get($url, [
                'timeout' => 15,
                'sslverify' => true
            ]);

            if (is_wp_error($response)) {
                error_log('External Events Error: ' . $response->get_error_message());
                return [];
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            // Handle both new (with pagination) and old response formats
            $events = isset($data['events']) ? $data['events'] : $data;

            if (!is_array($events)) return [];

            // Fetch service bodies from the external source
            $service_bodies = self::fetch_external_service_bodies($source);
            
            // Add source information to each event
            foreach ($events as &$event) {
                $event['external_source'] = [
                    'id' => $source['id'],
                    'url' => parse_url($source['url'], PHP_URL_HOST),
                    'name' => $source['name'] ?? parse_url($source['url'], PHP_URL_HOST),
                    'service_bodies' => $service_bodies
                ];
            }

            return $events;
        } catch (\Exception $e) {
            error_log('External Events Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch service bodies from an external source
     * 
     * @param array $source The external source configuration
     * @return array Array of service bodies
     */
    private static function fetch_external_service_bodies($source) {
        try {
            // Get BMLT root server from the external source
            $settings_url = trailingslashit($source['url']) . 'wp-json/event-manager/v1/settings';
            
            $settings_response = wp_remote_get($settings_url, [
                'timeout' => 15,
                'sslverify' => true
            ]);
            
            if (is_wp_error($settings_response)) {
                error_log('External Settings Error: ' . $settings_response->get_error_message());
                return [];
            }
            
            $settings_body = wp_remote_retrieve_body($settings_response);
            $settings = json_decode($settings_body, true);
            
            if (empty($settings['bmlt_root_server'])) {
                error_log('External source has no BMLT root server configured: ' . $source['url']);
                return [];
            }
            
            // Fetch service bodies from the BMLT root server
            $bmlt_url = add_query_arg('switcher', 'GetServiceBodies', trailingslashit($settings['bmlt_root_server']) . 'client_interface/json/');
            
            $bmlt_response = wp_remote_get($bmlt_url, [
                'timeout' => 15,
                'sslverify' => true
            ]);
            
            if (is_wp_error($bmlt_response)) {
                error_log('External BMLT Error: ' . $bmlt_response->get_error_message());
                return [];
            }
            
            $bmlt_body = wp_remote_retrieve_body($bmlt_response);
            $service_bodies = json_decode($bmlt_body, true);
            
            if (!is_array($service_bodies)) {
                error_log('Invalid service bodies response from external source: ' . $source['url']);
                return [];
            }
            
            return $service_bodies;
        } catch (\Exception $e) {
            error_log('External Service Bodies Error: ' . $e->getMessage());
            return [];
        }
    }

    public static function bmltenabled_mayo_get_events() {
        // Prevent any output that might interfere with headers
        $previous_error_reporting = error_reporting();
        error_reporting(E_ERROR | E_PARSE); // Only report serious errors during API calls
        
        $events = [];
        $local_events = [];
        
        // Pagination parameters
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $per_page = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 10;

        // Date range parameters for calendar view
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : null;
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : null;

        // Get source IDs from request
        $sourceIds = isset($_GET['source_ids']) ? 
            array_map('trim', array_filter(explode(',', $_GET['source_ids']))) : 
            [];
    
        // Get local events by default unless source_ids is explicitly set and doesn't include 'local'
        if (empty($sourceIds) || in_array('local', $sourceIds)) {
            $local_events = self::get_local_events($_GET);
        
            $events = array_merge($events, array_map(function($event) {
                $event['source'] = [
                    'id' => 'local',
                    'name' => 'Local Events',
                    'url' => get_site_url()
                ];
                return $event;
            }, $local_events));
        }

        // Get external events from specified sources
        if (!empty($sourceIds)) {
            $external_sources = get_option('mayo_external_sources', []);
            $enabled_sources = [];
            
            // Filter enabled sources that match the requested source IDs
            foreach ($external_sources as $source) {
                if (in_array($source['id'], $sourceIds) && $source['enabled']) {
                    $enabled_sources[] = $source;
                }
            }
            
            // If we have enabled sources, fetch events in parallel
            if (!empty($enabled_sources)) {
                foreach ($enabled_sources as $source) {
                    try {    
                        $source_events = self::fetch_external_events($source);
                        
                        if (!empty($source_events)) {
                            error_log('Received ' . count($source_events) . ' events from source: ' . $source['url']);
                            $events = array_merge($events, $source_events);
                        }
                    } catch (\Exception $e) {
                        error_log('Error fetching events from source ' . $source['url'] . ': ' . $e->getMessage());
                    }
                }

                if (!empty($external_events)) {
                    $events = array_merge($events, $external_events);
                }
            }
        }

        // Get sort order from request (default to ASC for backwards compatibility)
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
        if ($order !== 'ASC' && $order !== 'DESC') {
            $order = 'ASC'; // Fallback to ASC for invalid values
        }

        // Sort all events by date
        usort($events, function($a, $b) use ($order) {
            // Check if required meta fields exist
            if (!isset($a['meta']['event_start_date'])) {
                return 1; // Move items with missing dates to the end
            }
            if (!isset($b['meta']['event_start_date'])) {
                return -1; // Move items with missing dates to the end
            }

            $dateA = $a['meta']['event_start_date'] . ' ' . (isset($a['meta']['event_start_time']) ? $a['meta']['event_start_time'] : '00:00:00');
            $dateB = $b['meta']['event_start_date'] . ' ' . (isset($b['meta']['event_start_time']) ? $b['meta']['event_start_time'] : '00:00:00');

            // Handle invalid dates
            $timeA = strtotime($dateA);
            $timeB = strtotime($dateB);

            if ($timeA === false && $timeB === false) {
                return 0;
            } elseif ($timeA === false) {
                return 1;
            } elseif ($timeB === false) {
                return -1;
            }

            // Apply sort order
            if ($order === 'DESC') {
                return $timeB - $timeA; // Descending: latest first
            }
            return $timeA - $timeB; // Ascending: earliest first
        });
        
        // Apply pagination
        $total_events = count($events);
        $total_pages = ceil($total_events / $per_page);
        
        // Ensure page is within bounds
        $page = min($page, max(1, $total_pages));
        
        // Get the subset of events for the current page
        $offset = ($page - 1) * $per_page;
        $paginated_events = array_slice($events, $offset, $per_page);
        
        // Restore previous error reporting level
        error_reporting($previous_error_reporting);

        return new \WP_REST_Response([
            'events' => $paginated_events,
            'pagination' => [
                'total' => $total_events,
                'per_page' => $per_page,
                'current_page' => $page,
                'total_pages' => $total_pages
            ]
        ]);
    }

    private static function generate_recurring_events($post, $pattern) {
        $weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $events = [];
        $start = new DateTime(get_post_meta($post->ID, 'event_start_date', true));
        $end = !empty($pattern['endDate']) ? new DateTime($pattern['endDate']) : null;
        
        // Get skipped occurrences
        $skipped_occurrences = get_post_meta($post->ID, 'skipped_occurrences', true) ?: [];
        
        // Set a reasonable limit to prevent infinite loops (5 years worth of events)
        $max_events = 0;
        if ($pattern['type'] === 'daily') {
            $max_events = 365 * 5; // 5 years of daily events
        } elseif ($pattern['type'] === 'weekly') {
            $max_events = 52 * 5; // 5 years of weekly events
        } elseif ($pattern['type'] === 'monthly') {
            $max_events = 12 * 5; // 5 years of monthly events
        }
        
        if ($pattern['type'] === 'monthly') {
            // Start from the next interval month after the initial event
            $current = clone $start;
            $interval = $pattern['interval'];
            $current->modify('first day of +' . $interval . ' month');
            
            while (($end === null || $current <= $end) && count($events) < $max_events) {
                // Store the year and month for this iteration
                $current_year = (int)$current->format('Y');
                $current_month = (int)$current->format('m');
                
                if (isset($pattern['monthlyType']) && $pattern['monthlyType'] === 'date') {
                    // Specific date of month
                    $day = (int)$pattern['monthlyDate'];
                    
                    // Check if the day exists in current month
                    $days_in_month = (int)$current->format('t');
                    if ($day > $days_in_month) {
                        // Move to next interval month and continue
                        $current->modify('first day of +' . $interval . ' month');
                        continue;
                    }
                    
                    // Set to the specific day of the current month
                    $current->setDate($current_year, $current_month, $day);
                } else {
                    // Specific weekday (e.g., 2nd Thursday)
                    if (!isset($pattern['monthlyWeekday'])) {
                        // Move to next interval month
                        $current->modify('first day of +' . $interval . ' month');
                        continue;
                    }
                    
                    list($week, $weekday) = explode(',', $pattern['monthlyWeekday']);
                    $week = (int)$week;
                    $weekday = (int)$weekday;
                    
                    // Start from first day of current month for calculation
                    $current->setDate($current_year, $current_month, 1);
                    
                    if ($week > 0) {
                        // For nth weekday, we need to:
                        // 1. Go to the first day of the month
                        // 2. Find the first occurrence of the weekday
                        // 3. Add (week-1) weeks to get to the nth occurrence
                        $current->modify('first ' . $weekdays[$weekday] . ' of this month');
                        if ($week > 1) {
                            $current->modify('+' . ($week - 1) . ' weeks');
                        }
                    } else {
                        // Last occurrence
                        $current->modify('last ' . $weekdays[$weekday] . ' of this month');
                    }
                }
                
                if ($current <= $end || $end === null) {
                    // Check if this date is not in skipped occurrences
                    $current_date = $current->format('Y-m-d');
                    if (!in_array($current_date, $skipped_occurrences)) {
                        $events[] = self::format_recurring_event($post, clone $current);
                    }
                }
                
                // Move to next interval month - reset to first day to ensure proper advancement
                $current->setDate($current_year, $current_month, 1);
                $current->modify('+' . $interval . ' month');
            }
        } else {
            $interval = new DateInterval('P' . $pattern['interval'] . 
                ($pattern['type'] === 'daily' ? 'D' : 
                ($pattern['type'] === 'weekly' ? 'W' : 'M')));
            
            // Start from the next interval after the initial event
            $current = clone $start;
            $current->add($interval);
            
            while (($end === null || $current <= $end) && count($events) < $max_events) {
                if ($pattern['type'] === 'weekly' && !empty($pattern['weekdays'])) {
                    // For weekly pattern, we need to check each day in the interval
                    $interval_start = clone $current;
                    $interval_end = clone $current;
                    $interval_end->add($interval);
                    
                    // Check each day in the interval
                    while ($interval_start < $interval_end && count($events) < $max_events) {
                        $current_day = $interval_start->format('w'); // 0 (Sunday) to 6 (Saturday)
                        
                        if (in_array($current_day, $pattern['weekdays'])) {
                            // Check if this date is not in skipped occurrences
                            $current_date = $interval_start->format('Y-m-d');
                            if (!in_array($current_date, $skipped_occurrences)) {
                                $events[] = self::format_recurring_event($post, clone $interval_start);
                            }
                        }
                        
                        $interval_start->modify('+1 day');
                    }
                } else {
                    // For daily patterns, just add the event and move to next interval
                    // Check if this date is not in skipped occurrences
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

    private static function format_recurring_event($post, $date) {
        $event = self::format_event($post);
        $event['meta']['event_start_date'] = $date->format('Y-m-d');
        
        // Calculate the proper end date based on the original event's duration
        $original_start_date = get_post_meta($post->ID, 'event_start_date', true);
        $original_end_date = get_post_meta($post->ID, 'event_end_date', true);
        
        if ($original_start_date && $original_end_date) {
            // Calculate the duration between original start and end dates
            $original_start = new DateTime($original_start_date);
            $original_end = new DateTime($original_end_date);
            $duration = $original_start->diff($original_end);
            
            // Apply the same duration to the new start date
            $new_end_date = clone $date;
            $new_end_date->add($duration);
            $event['meta']['event_end_date'] = $new_end_date->format('Y-m-d');
        } else {
            // Fallback: if no end date is set, use the same date as start
            $event['meta']['event_end_date'] = $date->format('Y-m-d');
        }
        
        $event['recurring'] = true;
        return $event;
    }

    /**
     * Format event data for API response
     */
    public static function format_event($post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            return null;
        }
        
        $data = [
            'id' => $post->ID,
            'title' => [
                'rendered' => get_the_title($post)
            ],
            'content' => [
                'rendered' => apply_filters('the_content', $post->post_content)
            ],
            'link' => get_permalink($post),
            'meta' => [
                // Default values for required fields
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
        $data['categories'] = static::get_terms($post, 'category');
        $data['tags'] = static::get_terms($post, 'post_tag');
        
        return $data;
    }

    public static function bmltenabled_mayo_get_settings() {
        $settings = get_option('mayo_settings', []);
        $external_sources = get_option('mayo_external_sources', []);
        
        return new \WP_REST_Response([
            'bmlt_root_server' => $settings['bmlt_root_server'] ?? '',
            'notification_email' => $settings['notification_email'] ?? '',
            'default_service_bodies' => $settings['default_service_bodies'] ?? '',
            'external_sources' => $external_sources
        ]);
    }

    public static function bmltenabled_mayo_update_settings($request) {
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
        
        update_option('mayo_settings', $settings);
        
        return new \WP_REST_Response([
            'success' => true,
            'settings' => [
                'bmlt_root_server' => $settings['bmlt_root_server'] ?? '',
                'notification_email' => $settings['notification_email'] ?? '',
                'default_service_bodies' => $settings['default_service_bodies'] ?? '',
                'external_sources' => get_option('mayo_external_sources', [])
            ]
        ]);
    }

    public static function bmltenabled_mayo_get_event_details($request) {
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
     * Get terms for a post
     * 
     * @param WP_Post|int $post Post object or post ID
     * @param string $taxonomy Taxonomy name
     * @return array Array of term objects
     */
    private static function get_terms($post, $taxonomy) {
        $post_id = is_object($post) ? $post->ID : $post;
        
        $terms = wp_get_post_terms($post_id, $taxonomy, array(
            'fields' => 'all'
        ));

        if (is_wp_error($terms)) {
            return array();
        }

        return array_map(function($term) {
            return array(
                'id' => $term->term_id,
                'name' => html_entity_decode($term->name),
                'slug' => $term->slug,
                'link' => get_term_link($term)
            );
        }, $terms);
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

    // Add this new method to ensure nonce is available
    public static function enqueue_scripts() {
        // Add this to your plugin's enqueue_scripts action
        wp_localize_script('mayo-public', 'mayoApiSettings', [
            'root' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest')
        ]);
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

Rest::init();