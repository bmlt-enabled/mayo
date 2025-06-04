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
        
        $subject = 'New Event Submission: ' . sanitize_text_field($params['event_name']);
        $message = sprintf(
            "A new event has been submitted:\n\nEvent Name: %s\nEvent Type: %s\nStart Date: %s\nEnd Date: %s\n\nView the event: %s",
            sanitize_text_field($params['event_name']),
            sanitize_text_field($params['event_type']),
            sanitize_text_field($params['event_start_date']),
            sanitize_text_field($params['event_end_date']),
            admin_url('post.php?post=' . $post_id . '&action=edit')
        );

        wp_mail($to, $subject, $message);
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
        $timezone = isset($params['timezone']) ? sanitize_text_field(wp_unslash($params['timezone'])) : 'America/New_York';

        $today = new DateTime('now', new \DateTimeZone($timezone));
        $today->setTime(0, 0, 0); // Start of today
        $current_date = $today->format('Y-m-d');
        
        $events = [];
        
        // If we're in archive mode, just get all events
        if ($is_archive) {
            $events = self::query_events($status, $eventType, $serviceBody, $relation, $categories, $tags, null);
        } else {
            // In normal mode, we'll fetch events in two steps:
            
            // 1. Get direct future events (non-recurring or with future start dates)
            $future_events = self::query_events($status, $eventType, $serviceBody, $relation, $categories, $tags, $current_date);
            
            $events = array_merge($events, $future_events);
            
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
                'category_name' => $categories,
                'tag' => $tags
            ];
            
            $recurring_posts = get_posts($args);
            
            // Process these recurring posts and add only future instances
            foreach ($recurring_posts as $post) {
                try {
                    $recurring_pattern = get_post_meta($post->ID, 'recurring_pattern', true);
                    $start_date = get_post_meta($post->ID, 'event_start_date', true);
                    
                    if (!$recurring_pattern || !$start_date || $recurring_pattern['type'] === 'none') {
                        continue;
                    }
                    
                    $recurring_events = self::generate_recurring_events($post, $recurring_pattern);
                    
                    // Filter to keep only future events
                    $future_recurring_events = array_filter($recurring_events, function($event) use ($today) {
                        if (!isset($event['meta']['event_start_date']) || empty($event['meta']['event_start_date'])) {
                            return false;
                        }
                        
                        try {
                            $event_date_str = $event['meta']['event_start_date'];
                            $event_date = new DateTime($event_date_str);
                            $event_date->setTime(0, 0, 0); // Set to beginning of day for comparison
                            return $event_date >= $today;
                        } catch (\Exception $e) {
                            error_log('Mayo Events API: Error parsing recurring event date: ' . $e->getMessage());
                            return false;
                        }
                    });
                    
                    if (count($future_recurring_events) > 0) {
                        $events = array_merge($events, $future_recurring_events);
                    }
                } catch (\Exception $e) {
                    error_log('Mayo Events API: Error processing recurring event: ' . $e->getMessage());
                }
            }
        }

        return $events;
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

        $args = [
            'post_type' => 'mayo_event',
            'posts_per_page' => -1,
            'post_status' => $status,
            'meta_query' => $meta_query,
            'category_name' => $categories,
            'tag' => $tags
        ];

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

    public static function bmltenabled_mayo_get_events($request) {
        // Prevent any output that might interfere with headers
        $previous_error_reporting = error_reporting();
        error_reporting(E_ERROR | E_PARSE); // Only report serious errors during API calls
        
        $events = [];
        $local_events = [];
        
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
                $external_events = self::fetch_external_events_parallel($enabled_sources);

                if (!empty($external_events)) {
                    $events = array_merge($events, $external_events);
                }
            }
        }

        // Sort all events by date
        usort($events, function($a, $b) {
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
            
            return $timeA - $timeB;
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
    
    /**
     * Fetch events from multiple external sources in parallel
     * 
     * @param array $sources Array of external source configurations
     * @return array Array of events from all sources
     */
    private static function fetch_external_events_parallel($sources) {
        $all_events = [];
        
        // If no sources to fetch from, return empty array
        if (empty($sources)) {
            return [];
        }
        
        // Use curl_multi for true parallel requests
        if (function_exists('curl_multi_init')) {
            $mh = curl_multi_init();
            $curl_handles = [];
            $source_map = [];
            
            // Prepare all curl handles
            foreach ($sources as $index => $source) {
                // Build query parameters
                $params = [];
                if (!empty($source['event_type'])) $params['event_type'] = $source['event_type'];
                if (!empty($source['service_body'])) $params['service_body'] = $source['service_body'];
                if (!empty($source['categories'])) $params['categories'] = $source['categories'];
                if (!empty($source['tags'])) $params['tags'] = $source['tags'];
                
                // Pass archive and timezone parameters if they exist in the original request
                if (isset($_GET['archive'])) $params['archive'] = $_GET['archive'];
                if (isset($_GET['timezone'])) $params['timezone'] = $_GET['timezone'];

                // Build URL with parameters
                $url = add_query_arg($params, trailingslashit($source['url']) . 'wp-json/event-manager/v1/events');
                
                // Create curl handle
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                
                // Add to multi handle
                curl_multi_add_handle($mh, $ch);
                
                // Store handle and source mapping
                $curl_handles[$index] = $ch;
                $source_map[$index] = $source;
            }
            
            // Execute the requests in parallel
            $running = null;
            do {
                curl_multi_exec($mh, $running);
                curl_multi_select($mh);
            } while ($running > 0);
            

            foreach ($curl_handles as $index => $ch) {
                $source = $source_map[$index];
                $response = curl_multi_getcontent($ch);
                
                if (empty($response)) {
                    error_log('External Events Error: Empty response from ' . $source['url']);
                    curl_multi_remove_handle($mh, $ch);
                    continue;
                }
                
                $data = json_decode($response, true);
                
                // Handle both new (with pagination) and old response formats
                $events = isset($data['events']) ? $data['events'] : $data;
                
                if (!is_array($events)) {
                    error_log('External Events Error: Invalid JSON response from ' . $source['url']);
                    curl_multi_remove_handle($mh, $ch);
                    continue;
                }
                
                $source_id = $source['id'];
                $service_bodies[$source_id] = self::fetch_external_service_bodies($source);
                
                // Add source information to each event
                foreach ($events as &$event) {
                    $event['external_source'] = [
                        'id' => $source['id'],
                        'url' => parse_url($source['url'], PHP_URL_HOST),
                        'name' => $source['name'] ?? parse_url($source['url'], PHP_URL_HOST),
                        'service_bodies' => $service_bodies[$source_id]
                    ];
                }
                
                // Add events to the collection
                $all_events = array_merge($all_events, $events);
                
                // Clean up
                curl_multi_remove_handle($mh, $ch);
            }
            
            // Close multi handle
            curl_multi_close($mh);
        } else {
            // Fallback to sequential requests if curl_multi is not available
            foreach ($sources as $source) {
                $external_events = self::fetch_external_events($source);
                if (!empty($external_events)) {
                    $all_events = array_merge($all_events, $external_events);
                }
            }
        }
        
        return $all_events;
    }

    /**
     * Convert a number to its ordinal representation
     * 
     * @param int $number The number to convert
     * @return string The ordinal representation (1st, 2nd, 3rd, etc.)
     */
    private static function ordinal($number) {
        $ends = array('th','st','nd','rd','th','th','th','th','th','th');
        if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
            return $number. 'th';
        } else {
            return $number. $ends[$number % 10];
        }
    }

    private static function generate_recurring_events($post, $pattern) {
        $weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $events = [];
        $start = new DateTime(get_post_meta($post->ID, 'event_start_date', true));
        $end = !empty($pattern['endDate']) ? new DateTime($pattern['endDate']) : null;
        
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
                if (isset($pattern['monthlyType']) && $pattern['monthlyType'] === 'date') {
                    // Specific date of month
                    $day = (int)$pattern['monthlyDate'];
                    
                    // Check if the day exists in current month
                    $days_in_month = (int)$current->format('t');
                    if ($day > $days_in_month) {
                        // Move to next interval month
                        $current->modify('first day of +' . $interval . ' month');
                        continue;
                    }
                    
                    $current->setDate($current->format('Y'), $current->format('m'), $day);
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
                    
                    // Calculate the date
                    $current->modify('first day of this month');
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
                    $events[] = self::format_recurring_event($post, $current);
                }
                
                // Move to next interval month
                $current->modify('first day of +' . $interval . ' month');
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
                            $events[] = self::format_recurring_event($post, clone $interval_start);
                        }
                        
                        $interval_start->modify('+1 day');
                    }
                } else {
                    // For daily patterns, just add the event and move to next interval
                    $events[] = self::format_recurring_event($post, $current);
                }
                
                $current->add($interval);
            }
        }
        
        return $events;
    }

    private static function format_recurring_event($post, $date) {
        $event = self::format_event($post);
        $event['meta']['event_start_date'] = $date->format('Y-m-d');
        $event['meta']['event_end_date'] = $date->format('Y-m-d'); // Set end date to match start date
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
                'timezone' => 'America/New_York',
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