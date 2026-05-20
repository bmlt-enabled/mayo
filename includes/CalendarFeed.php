<?php

namespace BmltEnabled\Mayo;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class CalendarFeed {
    const MAX_EVENTS = 500;
    const CACHE_MAX_AGE = 900;

    public static function init() {
        add_action('init', [__CLASS__, 'register_feed']);
    }

    public static function register_feed() {
        add_feed('mayo_events', [__CLASS__, 'generate_ics_feed']);
    }

    public static function generate_ics_feed() {
        $eventType   = isset($_GET['event_type'])   ? sanitize_text_field(wp_unslash($_GET['event_type']))   : '';
        $serviceBody = isset($_GET['service_body']) ? sanitize_text_field(wp_unslash($_GET['service_body'])) : '';
        $relation    = isset($_GET['relation'])     ? sanitize_text_field(wp_unslash($_GET['relation']))     : 'AND';
        $categories  = isset($_GET['categories'])   ? sanitize_text_field(wp_unslash($_GET['categories']))   : '';
        $tags        = isset($_GET['tags'])         ? sanitize_text_field(wp_unslash($_GET['tags']))         : '';

        $posts = self::query_events($eventType, $serviceBody, $relation, $categories, $tags);

        // Last-Modified derived from MAX(post_modified_gmt). Lets polling clients (Google) issue 304.
        $last_modified_ts = 0;
        foreach ($posts as $p) {
            $ts = strtotime($p->post_modified_gmt);
            if ($ts && $ts > $last_modified_ts) {
                $last_modified_ts = $ts;
            }
        }
        if ($last_modified_ts === 0) {
            $last_modified_ts = time();
        }
        $last_modified_str = gmdate('D, d M Y H:i:s', $last_modified_ts) . ' GMT';

        if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $since = strtotime(wp_unslash($_SERVER['HTTP_IF_MODIFIED_SINCE']));
            if ($since !== false && $since >= $last_modified_ts) {
                status_header(304);
                header('Last-Modified: ' . $last_modified_str);
                header('Cache-Control: public, max-age=' . self::CACHE_MAX_AGE);
                exit;
            }
        }

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename=mayo_events.ics');
        header('Cache-Control: public, max-age=' . self::CACHE_MAX_AGE);
        header('Last-Modified: ' . $last_modified_str);

        $host = parse_url(home_url(), PHP_URL_HOST) ?: 'localhost';
        $vevents = [];
        $timezones_used = [];

        foreach ($posts as $post) {
            if (!is_object($post) || !isset($post->ID)) {
                continue;
            }
            try {
                $built = self::build_vevent($post, $host);
                if ($built === null) continue;
                $vevents[] = $built['ics'];
                if (!empty($built['tzid'])) {
                    $timezones_used[$built['tzid']] = true;
                }
            } catch (\Exception $e) {
                error_log('Mayo ICS Feed Event Processing Error: ' . $e->getMessage());
                continue;
            }
        }

        echo "BEGIN:VCALENDAR\r\n";
        echo "VERSION:2.0\r\n";
        echo "PRODID:-//Mayo Events Manager//EN\r\n";
        echo "CALSCALE:GREGORIAN\r\n";
        echo "METHOD:PUBLISH\r\n";
        echo "X-WR-CALNAME:" . self::escape_ical_text(get_bloginfo('name') . " - Events") . "\r\n";
        if (count($timezones_used) === 1) {
            $only_tz = array_keys($timezones_used)[0];
            echo "X-WR-TIMEZONE:" . self::escape_ical_text($only_tz) . "\r\n";
        }

        foreach (array_keys($timezones_used) as $tzid) {
            echo self::build_vtimezone($tzid);
        }

        foreach ($vevents as $vevent) {
            echo $vevent;
        }

        echo "END:VCALENDAR\r\n";
        exit;
    }

    private static function query_events($eventType, $serviceBody, $relation, $categories, $tags) {
        $facet_query = [];
        if (!empty($eventType)) {
            $facet_query[] = [
                'key'     => 'event_type',
                'value'   => $eventType,
                'compare' => '=',
            ];
        }
        if (!empty($serviceBody)) {
            $facet_query[] = [
                'key'     => 'service_body',
                'value'   => $serviceBody,
                'compare' => '=',
            ];
        }
        if (count($facet_query) > 1) {
            $facet_query['relation'] = $relation;
        }

        // Include non-recurring events whose start date is today or later, plus any event
        // with a recurring_pattern meta (so series with past start dates still appear and
        // calendar clients can render RRULE-projected future instances).
        $now = current_time('Y-m-d');
        $date_or_recurring = [
            'relation' => 'OR',
            [
                'key'     => 'event_start_date',
                'value'   => $now,
                'compare' => '>=',
                'type'    => 'DATE',
            ],
            [
                'key'     => 'recurring_pattern',
                'compare' => 'EXISTS',
            ],
        ];

        $combined_meta_query = ['relation' => 'AND'];
        if (!empty($facet_query)) {
            if (count($facet_query) === 1) {
                // Single-clause facet: flatten into the top-level AND group.
                // Wrapping a one-element array as a nested meta_query group
                // confuses WP_Meta_Query on some sites and yields zero matches.
                $combined_meta_query[] = $facet_query[0];
            } else {
                $facet_query['relation'] = $relation;
                $combined_meta_query[] = $facet_query;
            }
        }
        $combined_meta_query[] = $date_or_recurring;

        $args = [
            'post_type'        => 'mayo_event',
            'posts_per_page'   => self::MAX_EVENTS,
            'post_status'      => 'publish',
            'suppress_filters' => false,
            'meta_query'       => $combined_meta_query,
        ];

        if (!empty($categories)) {
            $args['category_name'] = $categories;
        }
        if (!empty($tags)) {
            $args['tag'] = $tags;
        }

        $events = get_posts($args);
        if (is_wp_error($events)) {
            error_log('Mayo ICS Feed Error: ' . $events->get_error_message());
            return [];
        }
        return $events;
    }

    /**
     * Build VEVENT(s) for a single post.
     * @return array{ics:string,tzid:string}|null
     */
    private static function build_vevent($post, $host) {
        $event_type   = get_post_meta($post->ID, 'event_type', true);
        $start_date   = get_post_meta($post->ID, 'event_start_date', true);
        if (!$start_date) return null;
        $end_date     = get_post_meta($post->ID, 'event_end_date', true) ?: $start_date;
        $start_time   = get_post_meta($post->ID, 'event_start_time', true) ?: '00:00:00';
        $end_time     = get_post_meta($post->ID, 'event_end_time', true) ?: '23:59:59';
        $location     = get_post_meta($post->ID, 'location_name', true);
        $location_addr = get_post_meta($post->ID, 'location_address', true);
        $tzid         = get_post_meta($post->ID, 'timezone', true) ?: 'UTC';

        try {
            $tz = new \DateTimeZone($tzid);
        } catch (\Exception $e) {
            $tzid = 'UTC';
            $tz = new \DateTimeZone('UTC');
        }

        $start_dt = new \DateTime($start_date . ' ' . $start_time, $tz);
        $end_dt   = new \DateTime($end_date   . ' ' . $end_time,   $tz);

        $pattern             = get_post_meta($post->ID, 'recurring_pattern', true);
        $skipped_occurrences = get_post_meta($post->ID, 'skipped_occurrences', true) ?: [];

        // recurring_pattern EXISTS matches even {type:'none'} rows, so filter past
        // non-recurring events out here. Real recurring series with past start dates
        // stay in — the RRULE controls which occurrences clients render.
        $is_real_recurrence = is_array($pattern)
            && isset($pattern['type'])
            && $pattern['type'] !== 'none';
        if (!$is_real_recurrence && $start_date < current_time('Y-m-d')) {
            return null;
        }

        $rrule = null;
        $exdates = [];
        $expand_fallback = false;
        if (is_array($pattern) && isset($pattern['type']) && $pattern['type'] !== 'none') {
            $rrule = self::build_rrule($pattern, $tz);
            if ($rrule !== null) {
                foreach ($skipped_occurrences as $skip) {
                    try {
                        $skip_dt = new \DateTime($skip . ' ' . $start_time, $tz);
                        $exdates[] = $skip_dt->format('Ymd\THis');
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            } else {
                $expand_fallback = true;
            }
        }

        $description = '';
        if ($event_type) {
            $description .= "Event Type: " . $event_type . "\n";
        }
        if ($location) {
            $description .= "Location: " . $location . "\n";
        }
        $description .= wp_strip_all_tags($post->post_content);

        $summary       = html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8');
        $url           = get_permalink($post->ID);
        $created       = gmdate('Ymd\THis\Z', strtotime($post->post_date_gmt));
        $last_modified = gmdate('Ymd\THis\Z', strtotime($post->post_modified_gmt));
        $dtstamp       = gmdate('Ymd\THis\Z');
        if ($location && $location_addr) {
            $location_full = $location . ', ' . $location_addr;
        } elseif ($location) {
            $location_full = $location;
        } else {
            $location_full = $location_addr;
        }

        $common = [
            'dtstamp'       => $dtstamp,
            'created'       => $created,
            'last_modified' => $last_modified,
            'tzid'          => $tzid,
            'summary'       => $summary,
            'description'   => $description,
            'location'      => $location_full,
            'url'           => $url,
        ];

        if ($expand_fallback) {
            $occurrences = self::expand_occurrences($pattern, $start_dt, $end_dt, $skipped_occurrences, $tz);
            $out = '';
            foreach ($occurrences as $occ) {
                $out .= self::render_vevent($common + [
                    'uid'     => 'mayo-' . $post->ID . '-' . $occ['start']->format('Ymd') . '@' . $host,
                    'dtstart' => $occ['start']->format('Ymd\THis'),
                    'dtend'   => $occ['end']->format('Ymd\THis'),
                    'rrule'   => null,
                    'exdates' => [],
                ]);
            }
            return ['ics' => $out, 'tzid' => $tzid];
        }

        $ics = self::render_vevent($common + [
            'uid'     => 'mayo-' . $post->ID . '@' . $host,
            'dtstart' => $start_dt->format('Ymd\THis'),
            'dtend'   => $end_dt->format('Ymd\THis'),
            'rrule'   => $rrule,
            'exdates' => $exdates,
        ]);
        return ['ics' => $ics, 'tzid' => $tzid];
    }

    private static function render_vevent($d) {
        $tzid = $d['tzid'];
        $out  = "BEGIN:VEVENT\r\n";
        $out .= "UID:" . self::escape_ical_text($d['uid']) . "\r\n";
        $out .= "DTSTAMP:" . $d['dtstamp'] . "\r\n";
        $out .= "CREATED:" . $d['created'] . "\r\n";
        $out .= "LAST-MODIFIED:" . $d['last_modified'] . "\r\n";
        $out .= "DTSTART;TZID=" . $tzid . ":" . $d['dtstart'] . "\r\n";
        $out .= "DTEND;TZID="   . $tzid . ":" . $d['dtend']   . "\r\n";
        if (!empty($d['rrule'])) {
            $out .= "RRULE:" . $d['rrule'] . "\r\n";
        }
        if (!empty($d['exdates'])) {
            foreach ($d['exdates'] as $exd) {
                $out .= "EXDATE;TZID=" . $tzid . ":" . $exd . "\r\n";
            }
        }
        $out .= "SUMMARY:" . self::escape_ical_text($d['summary']) . "\r\n";
        if (!empty($d['location'])) {
            $out .= "LOCATION:" . self::escape_ical_text($d['location']) . "\r\n";
        }
        $out .= "DESCRIPTION:" . self::escape_ical_text($d['description']) . "\r\n";
        if (!empty($d['url'])) {
            $out .= "URL:" . self::escape_ical_text($d['url']) . "\r\n";
        }
        $out .= "END:VEVENT\r\n";
        return $out;
    }

    /**
     * Build an RFC 5545 RRULE for the recurring pattern, or null if not expressible.
     */
    private static function build_rrule($pattern, $tz) {
        $type     = $pattern['type'] ?? '';
        $interval = max(1, intval($pattern['interval'] ?? 1));
        $parts    = [];

        if ($type === 'daily') {
            $parts[] = 'FREQ=DAILY';
            if ($interval > 1) $parts[] = 'INTERVAL=' . $interval;
        } elseif ($type === 'weekly') {
            $parts[] = 'FREQ=WEEKLY';
            if ($interval > 1) $parts[] = 'INTERVAL=' . $interval;
            if (!empty($pattern['weekdays']) && is_array($pattern['weekdays'])) {
                $map = ['SU','MO','TU','WE','TH','FR','SA'];
                $days = [];
                foreach ($pattern['weekdays'] as $d) {
                    $i = intval($d);
                    if (isset($map[$i])) $days[] = $map[$i];
                }
                if (!empty($days)) {
                    $parts[] = 'BYDAY=' . implode(',', $days);
                }
            }
        } elseif ($type === 'monthly') {
            $parts[] = 'FREQ=MONTHLY';
            if ($interval > 1) $parts[] = 'INTERVAL=' . $interval;
            $monthlyType = $pattern['monthlyType'] ?? 'date';
            if ($monthlyType === 'date' && isset($pattern['monthlyDate'])) {
                $parts[] = 'BYMONTHDAY=' . intval($pattern['monthlyDate']);
            } elseif ($monthlyType === 'weekday' && isset($pattern['monthlyWeekday'])) {
                $pieces = array_pad(explode(',', $pattern['monthlyWeekday']), 2, null);
                $week    = intval($pieces[0]);
                $weekday = intval($pieces[1]);
                $map = ['SU','MO','TU','WE','TH','FR','SA'];
                if (!isset($map[$weekday])) return null;
                // week=0 means "last" per EventsController:1809-1810
                $prefix = ($week === 0) ? '-1' : (string)$week;
                $parts[] = 'BYDAY=' . $prefix . $map[$weekday];
            } else {
                return null;
            }
        } else {
            return null;
        }

        if (!empty($pattern['endDate'])) {
            try {
                $until = new \DateTime($pattern['endDate'] . ' 23:59:59', $tz);
                $until->setTimezone(new \DateTimeZone('UTC'));
                $parts[] = 'UNTIL=' . $until->format('Ymd\THis\Z');
            } catch (\Exception $e) {
                // omit UNTIL on malformed endDate
            }
        }

        return implode(';', $parts);
    }

    /**
     * Expansion fallback for recurring patterns that can't be encoded as RRULE.
     * Mirrors the algorithm in EventsController::generate_recurring_events().
     */
    private static function expand_occurrences($pattern, $start_dt, $end_dt, $skipped, $tz) {
        $weekdays = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        $duration = $start_dt->diff($end_dt);
        $occurrences = [['start' => clone $start_dt, 'end' => clone $end_dt]];
        $end_pattern = !empty($pattern['endDate']) ? new \DateTime($pattern['endDate'], $tz) : null;

        $max = 0;
        switch ($pattern['type'] ?? '') {
            case 'daily':   $max = 365 * 5; break;
            case 'weekly':  $max = 52 * 5;  break;
            case 'monthly': $max = 12 * 5;  break;
            default: return $occurrences;
        }

        if ($pattern['type'] === 'monthly') {
            $current  = clone $start_dt;
            $interval = max(1, intval($pattern['interval'] ?? 1));
            $current->modify('first day of +' . $interval . ' month');
            while (($end_pattern === null || $current <= $end_pattern) && count($occurrences) < $max) {
                $year  = (int)$current->format('Y');
                $month = (int)$current->format('m');
                if (isset($pattern['monthlyType']) && $pattern['monthlyType'] === 'date') {
                    $day = (int)$pattern['monthlyDate'];
                    $days_in_month = (int)$current->format('t');
                    if ($day > $days_in_month) {
                        $current->modify('first day of +' . $interval . ' month');
                        continue;
                    }
                    $current->setDate($year, $month, $day);
                } else {
                    if (!isset($pattern['monthlyWeekday'])) {
                        $current->modify('first day of +' . $interval . ' month');
                        continue;
                    }
                    list($week, $weekday) = explode(',', $pattern['monthlyWeekday']);
                    $week = (int)$week; $weekday = (int)$weekday;
                    $current->setDate($year, $month, 1);
                    if ($week > 0) {
                        $current->modify('first ' . $weekdays[$weekday] . ' of this month');
                        if ($week > 1) {
                            $current->modify('+' . ($week - 1) . ' weeks');
                        }
                    } else {
                        $current->modify('last ' . $weekdays[$weekday] . ' of this month');
                    }
                }
                if ($end_pattern === null || $current <= $end_pattern) {
                    $date_str = $current->format('Y-m-d');
                    if (!in_array($date_str, $skipped, true)) {
                        $occ_start = (clone $current)->setTime(
                            (int)$start_dt->format('H'),
                            (int)$start_dt->format('i'),
                            (int)$start_dt->format('s')
                        );
                        $occ_end = clone $occ_start;
                        $occ_end->add($duration);
                        $occurrences[] = ['start' => $occ_start, 'end' => $occ_end];
                    }
                }
                $current->setDate($year, $month, 1);
                $current->modify('+' . $interval . ' month');
            }
        } else {
            $interval = max(1, intval($pattern['interval'] ?? 1));
            $spec = new \DateInterval('P' . $interval . ($pattern['type'] === 'daily' ? 'D' : ($pattern['type'] === 'weekly' ? 'W' : 'M')));
            $current = clone $start_dt;
            $current->add($spec);
            while (($end_pattern === null || $current <= $end_pattern) && count($occurrences) < $max) {
                if ($pattern['type'] === 'weekly' && !empty($pattern['weekdays'])) {
                    $is = clone $current;
                    $ie = clone $current;
                    $ie->add($spec);
                    while ($is < $ie && count($occurrences) < $max) {
                        if (in_array($is->format('w'), $pattern['weekdays'])) {
                            $date_str = $is->format('Y-m-d');
                            if (!in_array($date_str, $skipped, true)) {
                                $occ_start = clone $is;
                                $occ_end = clone $occ_start;
                                $occ_end->add($duration);
                                $occurrences[] = ['start' => $occ_start, 'end' => $occ_end];
                            }
                        }
                        $is->modify('+1 day');
                    }
                } else {
                    $date_str = $current->format('Y-m-d');
                    if (!in_array($date_str, $skipped, true)) {
                        $occ_start = clone $current;
                        $occ_end = clone $occ_start;
                        $occ_end->add($duration);
                        $occurrences[] = ['start' => $occ_start, 'end' => $occ_end];
                    }
                }
                $current->add($spec);
            }
        }

        return $occurrences;
    }

    /**
     * Emit a VTIMEZONE block with current STANDARD/DAYLIGHT transitions.
     * One historical pair is sufficient for the vast majority of calendar clients.
     */
    private static function build_vtimezone($tzid) {
        try {
            $tz = new \DateTimeZone($tzid);
        } catch (\Exception $e) {
            return '';
        }

        $now = time();
        $transitions = $tz->getTransitions(strtotime('-1 year', $now), strtotime('+2 year', $now));
        if (empty($transitions)) {
            return '';
        }

        $std = null;
        $dst = null;
        foreach ($transitions as $t) {
            if (!empty($t['isdst'])) {
                $dst = $t;
            } else {
                $std = $t;
            }
        }

        $out  = "BEGIN:VTIMEZONE\r\n";
        $out .= "TZID:" . $tzid . "\r\n";
        if ($std) {
            $out .= self::build_vtimezone_component('STANDARD', $std, $dst ?: $std);
        }
        if ($dst) {
            $out .= self::build_vtimezone_component('DAYLIGHT', $dst, $std ?: $dst);
        }
        $out .= "END:VTIMEZONE\r\n";
        return $out;
    }

    private static function build_vtimezone_component($type, $trans, $other) {
        $offset_from = self::format_offset($other['offset']);
        $offset_to   = self::format_offset($trans['offset']);
        // VTIMEZONE DTSTART is local time of the transition (clock face just after the change).
        $local = gmdate('Ymd\THis', $trans['ts'] + $trans['offset']);
        $abbr  = isset($trans['abbr']) ? $trans['abbr'] : '';

        $out  = "BEGIN:" . $type . "\r\n";
        $out .= "DTSTART:" . $local . "\r\n";
        $out .= "TZOFFSETFROM:" . $offset_from . "\r\n";
        $out .= "TZOFFSETTO:"   . $offset_to   . "\r\n";
        if ($abbr !== '') {
            $out .= "TZNAME:" . $abbr . "\r\n";
        }
        $out .= "END:" . $type . "\r\n";
        return $out;
    }

    private static function format_offset($seconds) {
        $sign = $seconds < 0 ? '-' : '+';
        $abs  = abs($seconds);
        return $sign . sprintf('%02d%02d', floor($abs / 3600), floor(($abs % 3600) / 60));
    }

    private static function escape_ical_text($text) {
        // Order matters: backslashes first, then commas/semicolons, then newlines.
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace([',', ';'], ['\\,', '\\;'], $text);
        $text = str_replace(["\r\n", "\n", "\r"], '\\n', $text);
        return $text;
    }
}

CalendarFeed::init();
