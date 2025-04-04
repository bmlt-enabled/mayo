<?php

namespace BmltEnabled\Mayo;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class RssFeed {
    public static function init() {
        add_action('init', [__CLASS__, 'register_feed']);
    }

    public static function register_feed() {
        add_feed('mayo_events', [__CLASS__, 'generate_rss_feed']);
    }

    public static function generate_rss_feed() {
        // Get query parameters
        $eventType = isset($_GET['event_type']) ? sanitize_text_field($_GET['event_type']) : '';
        $serviceBody = isset($_GET['service_body']) ? sanitize_text_field($_GET['service_body']) : '';
        $relation = isset($_GET['relation']) ? sanitize_text_field($_GET['relation']) : 'AND';
        $categories = isset($_GET['categories']) ? sanitize_text_field($_GET['categories']) : '';
        $tags = isset($_GET['tags']) ? sanitize_text_field($_GET['tags']) : '';

        header('Content-Type: application/rss+xml; charset=' . get_option('blog_charset'), true);

        $rss_items = self::get_rss_items($eventType, $serviceBody, $relation, $categories, $tags);

        echo '<?xml version="1.0" encoding="' . esc_html(get_option('blog_charset')) . '"?' . '>';
        ?>
        <rss version="2.0">
            <channel>
                <title><?php echo esc_html(get_bloginfo('name')); ?> - Events</title>
                <link><?php echo esc_url(get_bloginfo('url')); ?></link>
                <description><?php echo esc_html(get_bloginfo('description')); ?></description>
                <language><?php echo esc_html(get_option('rss_language')); ?></language>
                <pubDate><?php echo esc_html(mysql2date('D, d M Y H:i:s +0000', get_lastpostmodified('GMT'), false)); ?></pubDate>
                <lastBuildDate><?php echo esc_html(mysql2date('D, d M Y H:i:s +0000', get_lastpostmodified('GMT'), false)); ?></lastBuildDate>
                <generator>WordPress</generator>
                <?php foreach ($rss_items as $item): ?>
                    <item>
                        <title><?php echo esc_html($item['title']); ?></title>
                        <link><?php echo esc_url($item['link']); ?></link>
                        <description><![CDATA[<?php echo wp_kses_post($item['description']); ?>]]></description>
                        <pubDate><?php echo esc_html(mysql2date('D, d M Y H:i:s +0000', $item['pubDate'], false)); ?></pubDate>
                        <guid><?php echo esc_url($item['link']); ?></guid>
                    </item>
                <?php endforeach; ?>
            </channel>
        </rss>
        <?php
    }

    private static function get_rss_items($eventType = '', $serviceBody = '', $relation = 'AND', $categories = '', $tags = '') {
        $meta_query = [];
        
        // Only add meta queries for non-empty values
        if (!empty($eventType)) {
            $meta_query[] = [
                'key' => 'event_type',
                'value' => $eventType,
                'compare' => '='
            ];
        }
        
        if (!empty($serviceBody)) {
            $meta_query[] = [
                'key' => 'service_body',
                'value' => $serviceBody,
                'compare' => '='
            ];
        }

        $args = [
            'post_type' => 'mayo_event',
            'posts_per_page' => 100,
            'post_status' => 'publish',
            'suppress_filters' => false // Add this to ensure all filters are applied
        ];

        // Only add meta_query if we have conditions
        if (!empty($meta_query)) {
            if (count($meta_query) > 1) {
                $meta_query['relation'] = $relation;
            }
            $args['meta_query'] = $meta_query;
        }

        // Add taxonomy queries only if they exist
        if (!empty($categories)) {
            $args['category_name'] = $categories;
        }
        
        if (!empty($tags)) {
            $args['tag'] = $tags;
        }

        // Get current date for filtering
        $now = current_time('Y-m-d');

        // Add meta query for future events
        $date_query = [
            'key' => 'event_start_date',
            'value' => $now,
            'compare' => '>=',
            'type' => 'DATE'
        ];

        // Add date query to existing meta query
        if (!empty($meta_query)) {
            $meta_query[] = $date_query;
        } else {
            $meta_query = [$date_query];
        }

        $args['meta_query'] = $meta_query;

        // Get posts with error handling
        $events = get_posts($args);
        if (is_wp_error($events)) {
            error_log('RSS Feed Error: ' . $events->get_error_message());
            return [];
        }

        $rss_items = [];
        foreach ($events as $event) {
            if (!is_object($event) || !isset($event->ID)) {
                continue;
            }

            try {
                // Get additional event meta for description
                $event_type = get_post_meta($event->ID, 'event_type', true);
                $start_date = get_post_meta($event->ID, 'event_start_date', true);
                $end_date = get_post_meta($event->ID, 'event_end_date', true);
                $start_time = get_post_meta($event->ID, 'event_start_time', true);
                $end_time = get_post_meta($event->ID, 'event_end_time', true);
                $location = get_post_meta($event->ID, 'location_name', true);
                $timezone = get_post_meta($event->ID, 'timezone', true);

                // Build enhanced description
                $description = "<p><strong>Event Type:</strong> " . esc_html($event_type) . "</p>";
                
                // Format date and time
                $description .= "<p><strong>Date:</strong> " . esc_html($start_date);
                if ($end_date && $end_date !== $start_date) {
                    $description .= " to " . esc_html($end_date);
                }
                $description .= "</p>";
                
                if ($start_time) {
                    $description .= "<p><strong>Time:</strong> " . esc_html($start_time);
                    if ($end_time) {
                        $description .= " - " . esc_html($end_time);
                    }
                    if ($timezone) {
                        $description .= " (" . esc_html($timezone) . ")";
                    }
                    $description .= "</p>";
                }
                
                if ($location) {
                    $description .= "<p><strong>Location:</strong> " . esc_html($location) . "</p>";
                }
                
                $description .= "<div>" . apply_filters('the_content', $event->post_content) . "</div>";

                $rss_items[] = [
                    'title' => $event->post_title,
                    'link' => get_permalink($event->ID),
                    'description' => $description,
                    'pubDate' => $event->post_date_gmt,
                ];
            } catch (\Exception $e) {
                error_log('RSS Feed Event Processing Error: ' . $e->getMessage());
                continue;
            }
        }

        return $rss_items;
    }
}

RssFeed::init(); 