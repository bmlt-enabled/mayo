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
                [
                    'methods' => 'GET',
                    'callback' => [__CLASS__, 'bmltenabled_mayo_get_settings'],
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods' => 'POST',
                    'callback' => [__CLASS__, 'bmltenabled_mayo_update_settings'],
                    'permission_callback' => function() {
                        return current_user_can( 'manage_options' );
                    }
                ],
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

                    // Handle PDF files
                    if ($uploaded_file['type'] === 'application/pdf') {
                        update_post_meta($post_id, 'event_pdf_url', $uploaded_file['url']);
                        update_post_meta($post_id, 'event_pdf_id', $attachment_id);
                    } 
                    // Handle image files
                    elseif (strpos($uploaded_file['type'], 'image/') === 0) {
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
        $to = get_option('admin_email'); // Send to the site admin email
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
            $nonce = wp_create_nonce('wp_rest');
            if (wp_verify_nonce($nonce, 'wp_rest')) {
                $archive = sanitize_text_field(wp_unslash($params['archive']));
                if ($archive === 'true') {
                    $is_archive = true;
                }
            }
        }

        $status = isset($params['status']) ? sanitize_text_field(wp_unslash($params['status'])) : 'publish';
        $eventType = isset($params['event_type']) ? sanitize_text_field(wp_unslash($params['event_type'])) : '';
        $serviceBody = isset($params['service_body']) ? sanitize_text_field(wp_unslash($params['service_body'])) : '';
        $relation = isset($params['relation']) ? sanitize_text_field(wp_unslash($params['relation'])) : 'AND';
        $categories = isset($params['categories']) ? sanitize_text_field(wp_unslash($params['categories'])) : '';
        $tags = isset($params['tags']) ? sanitize_text_field(wp_unslash($params['tags'])) : '';

        $meta_keys = [
            'event_type' => $eventType,
            'service_body' => $serviceBody
        ];

        $meta_query = [];

        foreach ($meta_keys as $key => $value) {
            if ($value != '') {
                $meta_query[] = [
                    'key' => $key,
                    'value' => $value,
                    'compare' => '='
                ];
            }
        }

        if (count($meta_query) > 0) {
            $meta_query['relation'] = $relation;
        }

        $posts = get_posts([
            'post_type' => 'mayo_event',
            'posts_per_page' => -1,
            'post_status' => $status,
            'meta_query' => $meta_query,
            'category_name' => $categories,
            'tag' => $tags
        ]);

        $events = [];
        foreach ($posts as $post) {
            try {
                $recurring_pattern = get_post_meta($post->ID, 'recurring_pattern', true);
                
                if (!$is_archive && $recurring_pattern && $recurring_pattern['type'] !== 'none') {
                    $recurring_events = self::generate_recurring_events($post, $recurring_pattern);
                    $events = array_merge($events, $recurring_events);
                } else {
                    $events[] = self::format_event($post);
                }
            } catch (\Exception $e) {
                throw $e;
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
            $events = json_decode($body, true);

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
        $events = [];
        $local_events = [];
        
        // Get source IDs from request
        $sourceIds = isset($_GET['source_ids']) ? 
            array_map('sanitize_text_field', explode(',', $_GET['source_ids'])) : 
            [];
    
        // Always get local events if no source IDs specified, or if 'local' is in the source IDs
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
            foreach ($external_sources as $source) {
                if (!in_array($source['id'], $sourceIds) || !$source['enabled']) {
                    continue;
                }

                $external_events = self::fetch_external_events($source);
                if (!empty($external_events)) {
                    $events = array_merge($events, $external_events);
                }
            }
        }

        // Sort all events by date
        usort($events, function($a, $b) {
            $dateA = $a['meta']['event_start_date'] . ' ' . ($a['meta']['event_start_time'] ?? '00:00:00');
            $dateB = $b['meta']['event_start_date'] . ' ' . ($b['meta']['event_start_time'] ?? '00:00:00');
            return strtotime($dateA) - strtotime($dateB);
        });

        return new \WP_REST_Response($events);
    }

    private static function generate_recurring_events($post, $pattern) {
        $weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $events = [];
        $start = new DateTime(get_post_meta($post->ID, 'event_start_date', true));
        $end = !empty($pattern['endDate']) ? new DateTime($pattern['endDate']) : (clone $start)->modify('+1 year');
        
        if ($pattern['type'] === 'monthly') {
            $current = clone $start;
            while ($current <= $end) {
                if ($pattern['monthlyType'] === 'date') {
                    // Specific date of month
                    $day = (int)$pattern['monthlyDate'];
                    $current->setDate($current->format('Y'), $current->format('m'), $day);
                } else {
                    // Specific weekday (e.g., 3rd Thursday)
                    list($week, $weekday) = explode(',', $pattern['monthlyWeekday']);
                    $week = (int)$week;
                    $weekday = (int)$weekday;
                    
                    // Calculate the date
                    $current->modify('first day of this month');
                    if ($week > 0) {
                        // First, second, third, fourth, fifth
                        $current->modify('+' . ($week - 1) . ' weeks');
                        $current->modify('next ' . $weekdays[$weekday]);
                    } else {
                        // Last occurrence
                        $current->modify('last ' . $weekdays[$weekday] . ' of this month');
                    }
                }
                
                if ($current >= $start && $current <= $end) {
                    $events[] = self::format_recurring_event($post, $current);
                }
                
                // Move to next month
                $current->modify('first day of next month');
            }
        } else {
            $interval = new DateInterval('P' . $pattern['interval'] . 
                ($pattern['type'] === 'daily' ? 'D' : 
                ($pattern['type'] === 'weekly' ? 'W' : 'M')));
            
            $current = clone $start;
            
            while ($current <= $end) {
                if ($pattern['type'] === 'weekly' && !empty($pattern['weekdays'])) {
                    // For weekly pattern, check if current day is in selected weekdays
                    if (in_array($current->format('w'), $pattern['weekdays'])) {
                        $events[] = self::format_recurring_event($post, $current);
                    }
                } else {
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
            'meta' => []
        ];
        
        // Get featured image
        if (has_post_thumbnail($post)) {
            $data['featured_image'] = get_the_post_thumbnail_url($post, 'large');
        }
        
        // Explicitly get and add PDF data
        $event_pdf_url = get_post_meta($post->ID, 'event_pdf_url', true);
        $event_pdf_id = get_post_meta($post->ID, 'event_pdf_id', true);

        if ($event_pdf_url) {
            $data['meta']['event_pdf_url'] = $event_pdf_url;
            $data['meta']['event_pdf_id'] = $event_pdf_id;
        }

        // Add debugging
        error_log('Event PDF data for post ' . $post->ID . ': ' . json_encode([
            'url' => $event_pdf_url,
            'id' => $event_pdf_id
        ]));
        
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
            'event_pdf_url',  // Add these to ensure they're included
            'event_pdf_id'
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
        $settings = [];
        $external_sources = [];

        // Validate HTTPS URL
        if (isset($params['bmlt_root_server'])) {
            $url = esc_url_raw(trim($params['bmlt_root_server']));
            
            // Check if URL uses HTTPS
            if (!empty($url) && strpos($url, 'https://') !== 0) {
                return new \WP_Error(
                    'invalid_url_protocol',
                    __('BMLT Root Server URL must use HTTPS protocol.'),
                    ['status' => 400]
                );
            }
            
            $settings['bmlt_root_server'] = $url;
        }

        if (isset($params['external_sources']) && is_array($params['external_sources'])) {
            foreach ($params['external_sources'] as $source) {
                if (empty($source['url'])) continue;
                
                // Debug the event_type value
                error_log('External source event_type: ' . (isset($source['event_type']) ? $source['event_type'] : 'not set'));
                
                // Keep existing ID or generate new readable one
                $id = !empty($source['id']) ? sanitize_text_field($source['id']) : self::generate_readable_id();
                
                $external_sources[] = [
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
        }

        update_option('mayo_settings', $settings);
        update_option('mayo_external_sources', $external_sources);
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Settings updated successfully',
            'settings' => [
                'bmlt_root_server' => $settings['bmlt_root_server'] ?? '',
                'external_sources' => $external_sources
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
                'name' => $term->name,
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
}

Rest::init();