<?php

namespace BmltEnabled\Mayo;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class CalendarFeed {
    public static function init() {
        add_action('init', [__CLASS__, 'register_feed']);
    }

    public static function register_feed() {
        add_feed('mayo_events', [__CLASS__, 'generate_ics_feed']);
    }

    public static function generate_ics_feed() {
        // Get query parameters
        $eventType = isset($_GET['event_type']) ? sanitize_text_field($_GET['event_type']) : '';
        $serviceBody = isset($_GET['service_body']) ? sanitize_text_field($_GET['service_body']) : '';
        $relation = isset($_GET['relation']) ? sanitize_text_field($_GET['relation']) : 'AND';
        $categories = isset($_GET['categories']) ? sanitize_text_field($_GET['categories']) : '';
        $tags = isset($_GET['tags']) ? sanitize_text_field($_GET['tags']) : '';

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename=mayo_events.ics');

        $events = self::get_ics_items($eventType, $serviceBody, $relation, $categories, $tags);

        echo "BEGIN:VCALENDAR\r\n";
        echo "VERSION:2.0\r\n";
        echo "PRODID:-//Mayo Events Manager//EN\r\n";
        echo "CALSCALE:GREGORIAN\r\n";
        echo "METHOD:PUBLISH\r\n";
        echo "X-WR-CALNAME:" . self::escape_ical_text(get_bloginfo('name') . " - Events") . "\r\n";
        echo "X-WR-TIMEZONE:UTC\r\n";

        foreach ($events as $event) {
            echo "BEGIN:VEVENT\r\n";
            echo "UID:" . self::escape_ical_text($event['uid']) . "\r\n";
            echo "DTSTAMP:" . self::escape_ical_text($event['dtstamp']) . "\r\n";
            echo "DTSTART:" . self::escape_ical_text($event['dtstart']) . "\r\n";
            echo "DTEND:" . self::escape_ical_text($event['dtend']) . "\r\n";
            echo "SUMMARY:" . self::escape_ical_text($event['summary']) . "\r\n";
            if (!empty($event['location'])) {
                echo "LOCATION:" . self::escape_ical_text($event['location']) . "\r\n";
            }
            echo "DESCRIPTION:" . self::escape_ical_text($event['description']) . "\r\n";
            echo "URL:" . self::escape_ical_text($event['url']) . "\r\n";
            echo "END:VEVENT\r\n";
        }

        echo "END:VCALENDAR\r\n";
        exit;
    }

    private static function get_ics_items($eventType = '', $serviceBody = '', $relation = 'AND', $categories = '', $tags = '') {
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
            error_log('ICS Feed Error: ' . $events->get_error_message());
            return [];
        }

        $ics_items = [];
        foreach ($events as $event) {
            if (!is_object($event) || !isset($event->ID)) {
                continue;
            }

            try {
                // Get event meta
                $event_type = get_post_meta($event->ID, 'event_type', true);
                $start_date = get_post_meta($event->ID, 'event_start_date', true);
                $end_date = get_post_meta($event->ID, 'event_end_date', true) ?: $start_date;
                $start_time = get_post_meta($event->ID, 'event_start_time', true) ?: '00:00:00';
                $end_time = get_post_meta($event->ID, 'event_end_time', true) ?: '23:59:59';
                $location = get_post_meta($event->ID, 'location_name', true);
                $timezone = get_post_meta($event->ID, 'timezone', true) ?: 'UTC';

                // Format dates for iCal
                $start_datetime = new \DateTime($start_date . ' ' . $start_time, new \DateTimeZone($timezone));
                $end_datetime = new \DateTime($end_date . ' ' . $end_time, new \DateTimeZone($timezone));
                
                // Convert to UTC
                $start_datetime->setTimezone(new \DateTimeZone('UTC'));
                $end_datetime->setTimezone(new \DateTimeZone('UTC'));
                $now = new \DateTime('now', new \DateTimeZone('UTC'));

                // Build description
                $description = "Event Type: " . $event_type . "\n";
                if ($location) {
                    $description .= "Location: " . $location . "\n";
                }
                $description .= strip_tags($event->post_content);

                $ics_items[] = [
                    'uid' => $event->ID . '@' . parse_url(home_url(), PHP_URL_HOST),
                    'dtstamp' => $now->format('Ymd\THis\Z'),
                    'dtstart' => $start_datetime->format('Ymd\THis\Z'),
                    'dtend' => $end_datetime->format('Ymd\THis\Z'),
                    'summary' => html_entity_decode($event->post_title, ENT_QUOTES, 'UTF-8'),
                    'description' => $description,
                    'location' => $location,
                    'url' => get_permalink($event->ID)
                ];
            } catch (\Exception $e) {
                error_log('ICS Feed Event Processing Error: ' . $e->getMessage());
                continue;
            }
        }

        return $ics_items;
    }

    private static function escape_ical_text($text) {
        $text = str_replace(["\r\n", "\n", "\r"], "\\n", $text);
        $text = str_replace([",", ";", "\\"], ["\\,", "\\;", "\\\\"], $text);
        return $text;
    }
}

CalendarFeed::init(); 