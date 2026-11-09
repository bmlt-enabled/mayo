<?php

namespace BmltEnabled\Mayo;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Moved occurrences of a recurring event.
 *
 * A recurring event may have individual occurrences rescheduled to another
 * date (e.g. a 1st-Sunday meeting that moves to the 2nd Sunday because of a
 * holiday). These are stored in the `moved_occurrences` post meta as a list of
 * `{ original_date: 'Y-m-d', new_date: 'Y-m-d' }` objects. Every consumer that
 * expands a recurring pattern (REST list, iCal feed) resolves them through
 * this class so they agree on what a valid move is.
 */
class MovedOccurrences {

    const META_KEY = 'moved_occurrences';

    /**
     * Sanitize a raw `moved_occurrences` value into a clean list.
     *
     * Drops entries with malformed or identical dates, and keeps only the
     * first move for a given original date so a date can't move twice.
     *
     * @param mixed $value Raw meta value.
     * @return array<int, array{original_date: string, new_date: string}>
     */
    public static function sanitize($value) {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($value)) {
            return [];
        }

        $clean = [];
        $seen = [];
        foreach ($value as $entry) {
            if (is_object($entry)) {
                $entry = (array) $entry;
            }
            if (!is_array($entry)) {
                continue;
            }

            $original = self::normalize_date($entry['original_date'] ?? '');
            $new = self::normalize_date($entry['new_date'] ?? '');
            if ($original === null || $new === null || $original === $new || isset($seen[$original])) {
                continue;
            }

            $seen[$original] = true;
            $clean[] = [
                'original_date' => $original,
                'new_date' => $new,
            ];
        }

        return $clean;
    }

    /**
     * Get the moves for a post as an `original_date => new_date` map.
     *
     * @param int $post_id Post ID.
     * @return array<string, string>
     */
    public static function get_map($post_id) {
        $map = [];
        foreach (self::sanitize(get_post_meta($post_id, self::META_KEY, true)) as $move) {
            $map[$move['original_date']] = $move['new_date'];
        }
        return $map;
    }

    /**
     * Return the date as Y-m-d if it is a real calendar date, else null.
     *
     * @param mixed $date
     * @return string|null
     */
    private static function normalize_date($date) {
        if (!is_string($date)) {
            return null;
        }
        $date = trim($date);
        $dt = \DateTime::createFromFormat('!Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            return null;
        }
        return $date;
    }
}
