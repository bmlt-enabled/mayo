<?php

namespace BmltEnabled\Mayo;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class RssFeed {
    public static function init() {
        add_action('init', [__CLASS__, 'register_feed']);
    }

    public static function register_feed() {
        add_feed('mayo_rss', [__CLASS__, 'generate_rss_feed']);
    }


    private static function get_rss_items_from_rest_api($eventType = '', $serviceBody = '', $sourceIds = '', $relation = 'AND', $categories = '', $tags = '', $per_page = 10) {
        // Store original $_GET to restore later
        $original_get = $_GET;
        
        try {
            // Set $_GET parameters that the Rest class expects
            $_GET['status'] = 'publish';
            $_GET['page'] = '1';
            
            if (!empty($eventType)) $_GET['event_type'] = $eventType;
            if (!empty($serviceBody)) $_GET['service_body'] = $serviceBody;
            if (!empty($sourceIds)) $_GET['source_ids'] = $sourceIds;
            if (!empty($relation)) $_GET['relation'] = $relation;
            if (!empty($categories)) $_GET['categories'] = $categories;
            if (!empty($tags)) $_GET['tags'] = $tags;
            if (!empty($per_page)) $_GET['per_page'] = $per_page;

            // Debug logging can be enabled here if needed
            // error_log('RSS Feed Debug - Calling REST API with $_GET: ' . print_r($_GET, true));

            // Call the REST function directly without HTTP
            $response = Rest::bmltenabled_mayo_get_events();
            
            // Restore original $_GET
            $_GET = $original_get;
            
            if (is_wp_error($response)) {
                error_log('RSS Feed REST API Error: ' . $response->get_error_message());
                return [];
            }

            $api_events = $response->get_data();
            if (empty($api_events) || !isset($api_events['events'])) {
                return [];
            }
        } catch (\Exception $e) {
            error_log('RSS Feed Exception calling REST function: ' . $e->getMessage());
            $_GET = $original_get; // Restore $_GET
            return [];
        }

        $rss_items = [];
        foreach ($api_events['events'] as $event) {
            try {
                // Convert API event to RSS format
                $title = is_array($event['title']) ? $event['title']['rendered'] : ($event['title'] ?? 'Untitled Event');
                $link = isset($event['id']) ? get_permalink($event['id']) : home_url();
                
                // For external events, we might not have a local permalink
                if (isset($event['source']) && $event['source']['id'] !== 'local') {
                    $link = $event['external_source']['url'] ?? home_url();
                }

                // Format dates
                $start_date = $event['meta']['event_start_date'] ?? '';
                $start_time = $event['meta']['event_start_time'] ?? '';
                $end_date = $event['meta']['event_end_date'] ?? $start_date;
                $end_time = $event['meta']['event_end_time'] ?? '';

                $pub_date = '';
                if ($start_date) {
                    try {
                        $datetime = new \DateTime($start_date . ' ' . ($start_time ?: '00:00:00'));
                        $pub_date = $datetime->format('D, d M Y H:i:s O');
                    } catch (\Exception $e) {
                        $pub_date = date('D, d M Y H:i:s O');
                    }
                } else {
                    $pub_date = date('D, d M Y H:i:s O');
                }

                // Build description
                $description_parts = [];
                if ($start_date === $end_date) {
                    $description_parts[] = date('l, M j, Y', strtotime($start_date));
                    if ($start_time && $end_time && $start_time !== '00:00:00' && $end_time !== '23:59:59') {
                        $description_parts[] = date('g:i A', strtotime($start_time)) . ' - ' . date('g:i A', strtotime($end_time));
                    } elseif ($start_time && $start_time !== '00:00:00') {
                        $description_parts[] = date('g:i A', strtotime($start_time));
                    }
                } else {
                    $description_parts[] = date('M j', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date));
                }

                $description = implode(' – ', $description_parts);

                // Build detailed content
                $content_parts = [];
                
                if (!empty($event['content'])) {
                    $content_text = is_array($event['content']) ? $event['content']['rendered'] : $event['content'];
                    $content_parts[] = '<p>' . wp_kses_post($content_text) . '</p>';
                }

                if (!empty($event['meta']['event_type'])) {
                    $content_parts[] = '<p><strong>Event Type:</strong> ' . esc_html($event['meta']['event_type']) . '</p>';
                }

                if (!empty($event['meta']['location_name']) || !empty($event['meta']['location_address'])) {
                    $location_text = '<p><strong>Location:</strong> ';
                    if (!empty($event['meta']['location_name'])) {
                        $location_text .= esc_html($event['meta']['location_name']);
                        if (!empty($event['meta']['location_address'])) {
                            $location_text .= '<br>' . esc_html($event['meta']['location_address']);
                        }
                    } elseif (!empty($event['meta']['location_address'])) {
                        $location_text .= esc_html($event['meta']['location_address']);
                    }
                    $location_text .= '</p>';
                    $content_parts[] = $location_text;
                }

                if (!empty($event['meta']['contact_name']) || !empty($event['meta']['email'])) {
                    $contact_text = '<p><strong>Contact:</strong> ';
                    if (!empty($event['meta']['contact_name'])) {
                        $contact_text .= esc_html($event['meta']['contact_name']);
                        if (!empty($event['meta']['email'])) {
                            $contact_text .= ' (' . esc_html($event['meta']['email']) . ')';
                        }
                    } elseif (!empty($event['meta']['email'])) {
                        $contact_text .= esc_html($event['meta']['email']);
                    }
                    $contact_text .= '</p>';
                    $content_parts[] = $contact_text;
                }

                // Source information
                if (isset($event['source'])) {
                    $source_text = '<p><strong>Source:</strong> ';
                    
                    // Check if this is an external source event
                    if (isset($event['external_source']) && !empty($event['external_source']['name'])) {
                        // This is an external event, use the external_source name
                        $source_text .= \esc_html($event['external_source']['name']);
                    } else {
                        // This is a local event
                        $source_text .= 'Local Event';
                    }
                    
                    $source_text .= '</p>';
                    $content_parts[] = $source_text;
                }

                $content = implode("\n", $content_parts);

                $rss_items[] = [
                    'title' => $title,
                    'link' => $link,
                    'pub_date' => $pub_date,
                    'description' => $description,
                    'content' => $content,
                    'categories' => []
                ];
            } catch (\Exception $e) {
                error_log('RSS Feed Event Processing Error: ' . $e->getMessage());
                continue;
            }
        }

        return $rss_items;
    }


    public static function generate_rss_feed() {
        // Get parameters from URL (for manual overrides) or detect from page shortcode
        $eventType = isset($_GET['event_type']) ? \sanitize_text_field($_GET['event_type']) : '';
        $serviceBody = isset($_GET['service_body']) ? \sanitize_text_field($_GET['service_body']) : '';
        $sourceIds = isset($_GET['source_ids']) ? \sanitize_text_field($_GET['source_ids']) : '';
        $relation = isset($_GET['relation']) ? \sanitize_text_field($_GET['relation']) : 'AND';
        $categories = isset($_GET['categories']) ? \sanitize_text_field($_GET['categories']) : '';
        $tags = isset($_GET['tags']) ? \sanitize_text_field($_GET['tags']) : '';
        $per_page = isset($_GET['per_page']) ? \sanitize_text_field($_GET['per_page']) : '';
        
        // If no URL parameters provided, try to get them from the current page's shortcode
        if (empty($eventType) && empty($serviceBody) && empty($sourceIds) && empty($categories) && empty($tags) && empty($per_page)) {
            $shortcode_params = self::get_shortcode_params_from_current_page();
            $eventType = $shortcode_params['event_type'] ?? '';
            $serviceBody = $shortcode_params['service_body'] ?? '';
            $sourceIds = $shortcode_params['source_ids'] ?? '';
            $relation = $shortcode_params['relation'] ?? 'AND';
            $categories = $shortcode_params['categories'] ?? '';
            $tags = $shortcode_params['tags'] ?? '';
            $per_page = $shortcode_params['per_page'] ?? '';
        }

        header('Content-Type: application/rss+xml; charset=utf-8');

        // For debugging - create simple RSS first
        try {
            $events = self::get_rss_items_from_rest_api($eventType, $serviceBody, $sourceIds, $relation, $categories, $tags, $per_page);
        } catch (\Exception $e) {
            error_log('RSS Feed Generation Error: ' . $e->getMessage());
            // Fallback to simple empty feed
            $events = [];
        }
        $site_name = \get_bloginfo('name');
        $site_url = \home_url();
        $site_description = \get_bloginfo('description');
        
        // Build descriptive feed description with active filters
        $feed_description = self::build_feed_description($site_name, $eventType, $serviceBody, $sourceIds, $relation, $categories, $tags, $per_page);

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/">' . "\n";
        echo '<channel>' . "\n";
        echo '<title>' . self::escape_xml_text($site_name . ' - Events') . '</title>' . "\n";
        echo '<link>' . self::escape_xml_text($site_url) . '</link>' . "\n";
        echo '<description>' . self::escape_xml_text($feed_description) . '</description>' . "\n";
        echo '<lastBuildDate>' . date('D, d M Y H:i:s O') . '</lastBuildDate>' . "\n";
        echo '<language>' . \get_locale() . '</language>' . "\n";
        echo '<generator>Mayo Events Manager</generator>' . "\n";

        foreach ($events as $event) {
            echo '<item>' . "\n";
            echo '<title>' . self::escape_xml_text($event['title']) . '</title>' . "\n";
            echo '<link>' . self::escape_xml_text($event['link']) . '</link>' . "\n";
            echo '<guid isPermaLink="true">' . self::escape_xml_text($event['link']) . '</guid>' . "\n";
            echo '<pubDate>' . $event['pub_date'] . '</pubDate>' . "\n";
            echo '<description><![CDATA[' . $event['description'] . ']]></description>' . "\n";
            echo '<content:encoded><![CDATA[' . $event['content'] . ']]></content:encoded>' . "\n";
            
            // Add categories if available
            if (!empty($event['categories'])) {
                foreach ($event['categories'] as $category) {
                    echo '<category>' . self::escape_xml_text($category) . '</category>' . "\n";
                }
            }
            
            echo '</item>' . "\n";
        }

        echo '</channel>' . "\n";
        echo '</rss>' . "\n";
        exit;
    }

    private static function build_feed_description($site_name, $eventType = '', $serviceBody = '', $sourceIds = '', $relation = 'AND', $categories = '', $tags = '', $per_page = 10) {
        $base_description = "Event listings from " . $site_name;
        $parameters = [];
        
        // Include ALL parameters that are being passed to the function
        // Default parameters (always included)
        // $parameters[] = "status=publish";
        // $parameters[] = "page=1";
        
        // Optional parameters (include if not empty)
        if (!empty($eventType)) {
            $parameters[] = "event_type=" . $eventType;
        }
        
        if (!empty($serviceBody)) {
            $parameters[] = "service_body=" . $serviceBody;
        }
        
        if (!empty($sourceIds)) {
            $parameters[] = "source_ids=" . $sourceIds;
        }
        
        if (!empty($relation) && $relation !== 'AND') {
            $parameters[] = "relation=" . $relation;
        }
        
        if (!empty($categories)) {
            $parameters[] = "categories=" . $categories;
        }
        
        if (!empty($tags)) {
            $parameters[] = "tags=" . $tags;
        }

        if ($per_page != 10) {
            $parameters[] = "per_page=" . $per_page;
        }
        
        // Build the complete description with all parameters
        if (count($parameters) > 3) { // More than just the default 3 parameters
            $parameter_text = implode(' | ', $parameters);
            return $base_description . " - Parameters: " . $parameter_text;
        }
        
        // If only default parameters, still show them
        $parameter_text = implode(' | ', $parameters);
        return $base_description . " - Parameters: " . $parameter_text;
    }

    private static function get_shortcode_params_from_current_page() {
        global $post;
        
        $params = [];
        
        // Try to get the current page/post
        if (!$post) {
            $post = \get_queried_object();
        }
        
        // If we have a post and it contains the mayo_event_list shortcode
        if ($post && isset($post->post_content) && \has_shortcode($post->post_content, 'mayo_event_list')) {
            // Use WordPress shortcode parsing to extract attributes
            $pattern = \get_shortcode_regex(['mayo_event_list']);
            if (preg_match_all('/' . $pattern . '/s', $post->post_content, $matches)) {
                foreach ($matches[0] as $index => $shortcode) {
                    if ($matches[2][$index] === 'mayo_event_list') {
                        $attrs = \shortcode_parse_atts($matches[3][$index]);
                        if ($attrs) {
                            $params = array_merge($params, $attrs);
                            break; // Use first shortcode found
                        }
                    }
                }
            }
        }
        
        return $params;
    }

    private static function escape_xml_text($text) {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}

RssFeed::init();