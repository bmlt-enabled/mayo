<?php
/**
 * Subscriber management for announcement email subscriptions
 *
 * @package Mayo
 */

namespace BmltEnabled\Mayo;

class Subscriber
{
    /**
     * Table name (without prefix)
     */
    const TABLE_NAME = 'mayo_subscribers';

    /**
     * Get the full table name with prefix
     */
    public static function get_table_name()
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Create the subscribers table
     * Called on plugin activation
     */
    public static function create_table()
    {
        global $wpdb;

        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            token VARCHAR(64) NOT NULL,
            preferences LONGTEXT DEFAULT NULL,
            confirmed_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY email (email),
            KEY token (token),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Generate a secure random token
     */
    public static function generate_token()
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Subscribe an email address
     * Creates a pending subscriber and sends confirmation email
     *
     * @param string $email Email address to subscribe
     * @param array|null $preferences Optional subscription preferences
     * @return array Result with success status and message
     */
    public static function subscribe($email, $preferences = null)
    {
        global $wpdb;

        $email = sanitize_email($email);

        if (!is_email($email)) {
            return [
                'success' => false,
                'code' => 'invalid_email',
                'message' => 'Please enter a valid email address.'
            ];
        }

        $table_name = self::get_table_name();
        $preferences_json = $preferences ? wp_json_encode($preferences) : null;

        // Check if already subscribed
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE email = %s",
            $email
        ));

        if ($existing) {
            if ($existing->status === 'active') {
                return [
                    'success' => false,
                    'code' => 'already_subscribed',
                    'message' => 'This email is already subscribed.'
                ];
            } elseif ($existing->status === 'pending') {
                // Update preferences and resend confirmation email
                if ($preferences_json) {
                    $wpdb->update(
                        $table_name,
                        ['preferences' => $preferences_json],
                        ['id' => $existing->id],
                        ['%s'],
                        ['%d']
                    );
                }
                self::send_confirmation_email($email, $existing->token);
                return [
                    'success' => true,
                    'code' => 'confirmation_resent',
                    'message' => 'A confirmation email has been sent. Please check your inbox (and spam folder).'
                ];
            } elseif ($existing->status === 'unsubscribed') {
                // Re-subscribe: generate new token and set to pending
                $token = self::generate_token();
                $update_data = [
                    'status' => 'pending',
                    'token' => $token,
                    'confirmed_at' => null
                ];
                $update_format = ['%s', '%s', null];

                if ($preferences_json) {
                    $update_data['preferences'] = $preferences_json;
                    $update_format[] = '%s';
                }

                $wpdb->update(
                    $table_name,
                    $update_data,
                    ['id' => $existing->id],
                    $update_format,
                    ['%d']
                );
                self::send_confirmation_email($email, $token);
                return [
                    'success' => true,
                    'code' => 'resubscribed',
                    'message' => 'A confirmation email has been sent. Please check your inbox (and spam folder).'
                ];
            }
        }

        // Create new subscriber
        $token = self::generate_token();

        $insert_data = [
            'email' => $email,
            'status' => 'pending',
            'token' => $token,
            'created_at' => current_time('mysql')
        ];
        $insert_format = ['%s', '%s', '%s', '%s'];

        if ($preferences_json) {
            $insert_data['preferences'] = $preferences_json;
            $insert_format[] = '%s';
        }

        $result = $wpdb->insert($table_name, $insert_data, $insert_format);

        if ($result === false) {
            return [
                'success' => false,
                'code' => 'database_error',
                'message' => 'An error occurred. Please try again.'
            ];
        }

        // Send confirmation email
        self::send_confirmation_email($email, $token);

        return [
            'success' => true,
            'code' => 'confirmation_sent',
            'message' => 'A confirmation email has been sent. Please check your inbox (and spam folder).'
        ];
    }

    /**
     * Get subscriber by token
     *
     * @param string $token Subscriber token
     * @return object|null Subscriber object or null if not found
     */
    public static function get_by_token($token)
    {
        global $wpdb;

        $token = sanitize_text_field($token);
        $table_name = self::get_table_name();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE token = %s",
            $token
        ));
    }

    /**
     * Update subscriber preferences
     *
     * @param string $token Subscriber token
     * @param array $preferences New preferences
     * @return bool Success status
     */
    public static function update_preferences($token, $preferences)
    {
        global $wpdb;

        $token = sanitize_text_field($token);
        $table_name = self::get_table_name();

        $result = $wpdb->update(
            $table_name,
            ['preferences' => wp_json_encode($preferences)],
            ['token' => $token],
            ['%s'],
            ['%s']
        );

        return $result !== false;
    }

    /**
     * Confirm a subscription
     *
     * @param string $token Confirmation token
     * @return array Result with success status and message
     */
    public static function confirm($token)
    {
        global $wpdb;

        $token = sanitize_text_field($token);
        $table_name = self::get_table_name();

        $subscriber = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE token = %s",
            $token
        ));

        if (!$subscriber) {
            return [
                'success' => false,
                'code' => 'invalid_token',
                'message' => 'Invalid or expired confirmation link.'
            ];
        }

        if ($subscriber->status === 'active') {
            return [
                'success' => true,
                'code' => 'already_confirmed',
                'message' => 'Your subscription is already confirmed.'
            ];
        }

        $wpdb->update(
            $table_name,
            [
                'status' => 'active',
                'confirmed_at' => current_time('mysql')
            ],
            ['id' => $subscriber->id],
            ['%s', '%s'],
            ['%d']
        );

        // Send welcome email
        self::send_welcome_email($subscriber->email, $subscriber->token);

        return [
            'success' => true,
            'code' => 'confirmed',
            'message' => 'Your subscription has been confirmed! You will now receive announcement emails.'
        ];
    }

    /**
     * Unsubscribe
     *
     * @param string $token Unsubscribe token
     * @return array Result with success status and message
     */
    public static function unsubscribe($token)
    {
        global $wpdb;

        $token = sanitize_text_field($token);
        $table_name = self::get_table_name();

        $subscriber = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE token = %s",
            $token
        ));

        if (!$subscriber) {
            return [
                'success' => false,
                'code' => 'invalid_token',
                'message' => 'Invalid unsubscribe link.'
            ];
        }

        if ($subscriber->status === 'unsubscribed') {
            return [
                'success' => true,
                'code' => 'already_unsubscribed',
                'message' => 'You have already been unsubscribed.'
            ];
        }

        $wpdb->update(
            $table_name,
            ['status' => 'unsubscribed'],
            ['id' => $subscriber->id],
            ['%s'],
            ['%d']
        );

        return [
            'success' => true,
            'code' => 'unsubscribed',
            'message' => 'You have been unsubscribed and will no longer receive announcement emails.'
        ];
    }

    /**
     * Get all active subscribers
     *
     * @return array Array of subscriber objects
     */
    public static function get_active_subscribers()
    {
        global $wpdb;

        $table_name = self::get_table_name();

        return $wpdb->get_results(
            "SELECT * FROM $table_name WHERE status = 'active'"
        );
    }

    /**
     * Get all subscribers (for admin view)
     *
     * @return array Array of subscriber objects
     */
    public static function get_all_subscribers()
    {
        global $wpdb;

        $table_name = self::get_table_name();

        return $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY created_at DESC"
        );
    }

    /**
     * Get active subscribers matching given announcement criteria
     *
     * @param array $announcement_data Array with 'categories', 'tags', 'service_body' keys
     * @return array Array of matching subscriber objects
     */
    public static function get_matching($announcement_data)
    {
        $subscribers = self::get_active_subscribers();
        $matching = [];

        foreach ($subscribers as $subscriber) {
            if (self::matches_preferences($subscriber, $announcement_data)) {
                $matching[] = $subscriber;
            }
        }

        return $matching;
    }

    /**
     * Get active subscribers matching given announcement criteria with match reasons
     *
     * @param array $announcement_data Array with 'categories', 'tags', 'service_body' keys
     * @return array Array of ['subscriber' => object, 'reason' => string]
     */
    public static function get_matching_with_reasons($announcement_data)
    {
        $subscribers = self::get_active_subscribers();
        $matching = [];

        // Fetch service body names from BMLT once for all subscribers
        $service_body_names = self::get_service_body_names();

        foreach ($subscribers as $subscriber) {
            $reason = self::get_match_reason($subscriber, $announcement_data, $service_body_names);
            if ($reason !== false) {
                $matching[] = [
                    'subscriber' => $subscriber,
                    'reason' => $reason
                ];
            }
        }

        return $matching;
    }

    /**
     * Get service body names from BMLT
     *
     * @return array Associative array of service body ID => name
     */
    private static function get_service_body_names()
    {
        $settings = get_option('mayo_settings', []);
        $bmlt_root_server = $settings['bmlt_root_server'] ?? '';
        $service_body_names = [];

        if (!empty($bmlt_root_server)) {
            $sb_url = rtrim($bmlt_root_server, '/') . '/client_interface/json/?switcher=GetServiceBodies';
            $response = wp_remote_get($sb_url, ['timeout' => 10]);
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                if (is_array($data)) {
                    foreach ($data as $sb) {
                        if (isset($sb['id']) && isset($sb['name'])) {
                            $service_body_names[(string) $sb['id']] = $sb['name'];
                        }
                    }
                }
            }
        }

        return $service_body_names;
    }

    /**
     * Get the reason why a subscriber matches announcement criteria
     *
     * @param object $subscriber Subscriber object with preferences JSON
     * @param array $announcement_data Announcement categories, tags, service_body
     * @param array $service_body_names Optional lookup for service body names
     * @return array|false Structured reason array if matches, false if no match
     */
    private static function get_match_reason($subscriber, $announcement_data, $service_body_names = [])
    {
        // If no preferences set (legacy subscriber), send all announcements
        if (empty($subscriber->preferences)) {
            return ['all' => true];
        }

        $prefs = json_decode($subscriber->preferences, true);

        if (!is_array($prefs)) {
            return ['all' => true];
        }

        // Check if all preference arrays are empty
        $has_prefs = !empty($prefs['categories']) || !empty($prefs['tags']) || !empty($prefs['service_bodies']);
        if (!$has_prefs) {
            return ['all' => true];
        }

        $reasons = [
            'categories' => [],
            'tags' => [],
            'service_body' => null
        ];

        // Check categories
        if (!empty($prefs['categories']) && !empty($announcement_data['categories'])) {
            $matching_categories = array_intersect($prefs['categories'], $announcement_data['categories']);
            if (!empty($matching_categories)) {
                foreach ($matching_categories as $cat_id) {
                    $term = get_term($cat_id, 'category');
                    if ($term && !is_wp_error($term)) {
                        $reasons['categories'][] = $term->name;
                    }
                }
            }
        }

        // Check tags
        if (!empty($prefs['tags']) && !empty($announcement_data['tags'])) {
            $matching_tags = array_intersect($prefs['tags'], $announcement_data['tags']);
            if (!empty($matching_tags)) {
                foreach ($matching_tags as $tag_id) {
                    $term = get_term($tag_id, 'post_tag');
                    if ($term && !is_wp_error($term)) {
                        $reasons['tags'][] = $term->name;
                    }
                }
            }
        }

        // Check service body
        if (!empty($prefs['service_bodies']) && !empty($announcement_data['service_body'])) {
            if (in_array($announcement_data['service_body'], $prefs['service_bodies'])) {
                $sb_id = $announcement_data['service_body'];
                $reasons['service_body'] = $service_body_names[(string) $sb_id] ?? "Service Body $sb_id";
            }
        }

        // Return false if no matches found
        if (empty($reasons['categories']) && empty($reasons['tags']) && empty($reasons['service_body'])) {
            return false;
        }

        return $reasons;
    }

    /**
     * Count active subscribers matching given announcement criteria
     *
     * @param array $announcement_data Array with 'categories', 'tags', 'service_body' keys
     * @return int Number of matching active subscribers
     */
    public static function count_matching($announcement_data)
    {
        return count(self::get_matching($announcement_data));
    }

    /**
     * Send confirmation email
     *
     * @param string $email Email address
     * @param string $token Confirmation token
     */
    private static function send_confirmation_email($email, $token)
    {
        $confirm_url = add_query_arg([
            'mayo_confirm' => $token
        ], home_url());

        $site_name = get_bloginfo('name');

        $subject = sprintf('Please confirm your subscription to %s announcements', $site_name);

        $message = "You have requested to subscribe to announcements from {$site_name}.\n\n";
        $message .= "Click the link below to confirm your subscription:\n";
        $message .= "{$confirm_url}\n\n";
        $message .= "If you didn't request this, you can safely ignore this email.\n\n";
        $message .= "Note: If you don't see our emails, please check your spam or junk folder.";

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        $result = wp_mail($email, $subject, $message, $headers);

        if (!$result) {
            error_log('Mayo Subscriber: Failed to send confirmation email to ' . $email);
        }
    }

    /**
     * Send welcome email after subscription confirmation
     *
     * @param string $email Email address
     * @param string $token Subscriber token for unsubscribe link
     */
    private static function send_welcome_email($email, $token)
    {
        $unsubscribe_url = add_query_arg([
            'mayo_unsubscribe' => $token
        ], home_url());

        $site_name = get_bloginfo('name');

        $subject = sprintf('Welcome to %s announcements', $site_name);

        $message = "You're now subscribed to announcements from {$site_name}.\n\n";
        $message .= "You'll receive an email whenever a new announcement is published.\n\n";
        $message .= "---\n";
        $message .= "Manage preferences / Unsubscribe: {$unsubscribe_url}\n";

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        $result = wp_mail($email, $subject, $message, $headers);

        if (!$result) {
            error_log('Mayo Subscriber: Failed to send welcome email to ' . $email);
        }
    }

    /**
     * Send announcement email to all active subscribers
     *
     * @param int $announcement_id Announcement post ID
     */
    public static function send_announcement_email($announcement_id)
    {
        $announcement = get_post($announcement_id);

        if (!$announcement || $announcement->post_type !== 'mayo_announcement') {
            return;
        }

        $subscribers = self::get_active_subscribers();

        if (empty($subscribers)) {
            return;
        }

        // Get announcement metadata for preference matching
        $announcement_data = self::get_announcement_data($announcement_id);

        $site_name = get_bloginfo('name');
        $title = html_entity_decode(get_the_title($announcement_id), ENT_QUOTES, 'UTF-8');
        $content = apply_filters('the_content', $announcement->post_content);
        $content_plain = wp_strip_all_tags($content);
        $permalink = get_permalink($announcement_id);

        // Get linked events
        $linked_events_text = self::get_linked_events_text($announcement_id);

        $subject = sprintf('[%s] %s', $site_name, $title);

        foreach ($subscribers as $subscriber) {
            // Check if subscriber preferences match the announcement
            if (!self::matches_preferences($subscriber, $announcement_data)) {
                continue;
            }

            $unsubscribe_url = add_query_arg([
                'mayo_unsubscribe' => $subscriber->token
            ], home_url());

            $message = "{$title}\n";
            $message .= str_repeat('-', strlen($title)) . "\n\n";
            $message .= "{$content_plain}\n\n";

            if (!empty($linked_events_text)) {
                $message .= $linked_events_text . "\n";
            }

            $message .= "View online: {$permalink}\n\n";
            $message .= "---\n";
            $message .= "Manage preferences / Unsubscribe: {$unsubscribe_url}\n";

            $headers = ['Content-Type: text/plain; charset=UTF-8'];

            $result = wp_mail($subscriber->email, $subject, $message, $headers);

            if (!$result) {
                error_log('Mayo Subscriber: Failed to send announcement email to ' . $subscriber->email);
            }
        }
    }

    /**
     * Get announcement data for preference matching
     *
     * @param int $announcement_id Announcement post ID
     * @return array Announcement data with categories, tags, service_body
     */
    private static function get_announcement_data($announcement_id)
    {
        // Get categories
        $categories = wp_get_post_terms($announcement_id, 'category', ['fields' => 'ids']);
        if (is_wp_error($categories)) {
            $categories = [];
        }

        // Get tags
        $tags = wp_get_post_terms($announcement_id, 'post_tag', ['fields' => 'ids']);
        if (is_wp_error($tags)) {
            $tags = [];
        }

        // Get service body
        $service_body = get_post_meta($announcement_id, 'service_body', true);

        return [
            'categories' => $categories,
            'tags' => $tags,
            'service_body' => $service_body
        ];
    }

    /**
     * Check if subscriber preferences match announcement (OR logic)
     *
     * @param object $subscriber Subscriber object with preferences JSON
     * @param array $announcement_data Announcement categories, tags, service_body
     * @return bool True if should send email
     */
    private static function matches_preferences($subscriber, $announcement_data)
    {
        // If no preferences set (legacy subscriber), send all announcements
        if (empty($subscriber->preferences)) {
            return true;
        }

        $prefs = json_decode($subscriber->preferences, true);

        if (!is_array($prefs)) {
            return true; // Invalid preferences, treat as legacy
        }

        // Check if all preference arrays are empty (shouldn't happen, but treat as receive all)
        $has_prefs = !empty($prefs['categories']) || !empty($prefs['tags']) || !empty($prefs['service_bodies']);
        if (!$has_prefs) {
            return true;
        }

        // OR logic: match if ANY preference matches

        // Check categories
        if (!empty($prefs['categories']) && !empty($announcement_data['categories'])) {
            $matching_categories = array_intersect($prefs['categories'], $announcement_data['categories']);
            if (!empty($matching_categories)) {
                return true;
            }
        }

        // Check tags
        if (!empty($prefs['tags']) && !empty($announcement_data['tags'])) {
            $matching_tags = array_intersect($prefs['tags'], $announcement_data['tags']);
            if (!empty($matching_tags)) {
                return true;
            }
        }

        // Check service body
        if (!empty($prefs['service_bodies']) && !empty($announcement_data['service_body'])) {
            if (in_array($announcement_data['service_body'], $prefs['service_bodies'])) {
                return true;
            }
        }

        // No match found
        return false;
    }

    /**
     * Get formatted text for linked events
     *
     * @param int $announcement_id Announcement post ID
     * @return string Formatted event details or empty string if no linked events
     */
    private static function get_linked_events_text($announcement_id)
    {
        $linked_refs = Announcement::get_linked_event_refs($announcement_id);

        if (empty($linked_refs)) {
            return '';
        }

        $events_text = "---\n";
        $events_text .= "RELATED EVENT" . (count($linked_refs) > 1 ? "S" : "") . "\n";
        $events_text .= "---\n\n";

        foreach ($linked_refs as $ref) {
            $resolved = Announcement::resolve_event_ref($ref);

            if ($resolved) {
                $event_title = html_entity_decode($resolved['title'], ENT_QUOTES, 'UTF-8');
                $event_permalink = $resolved['permalink'];
                $is_external = isset($resolved['source']) && $resolved['source']['type'] === 'external';

                $events_text .= "{$event_title}\n";

                // Add source indicator for external events
                if ($is_external && !empty($resolved['source']['name'])) {
                    $events_text .= "Source: " . $resolved['source']['name'] . "\n";
                }

                // Get event meta (available for both local and external events from resolve_event_ref)
                $start_date = $resolved['start_date'] ?? '';
                $end_date = $resolved['end_date'] ?? '';
                $start_time = $resolved['start_time'] ?? '';
                $end_time = $resolved['end_time'] ?? '';
                $location_name = $resolved['location_name'] ?? '';
                $location_address = $resolved['location_address'] ?? '';

                // Format date/time
                if ($start_date) {
                    $date_str = self::format_date($start_date);
                    if ($end_date && $end_date !== $start_date) {
                        $date_str .= ' - ' . self::format_date($end_date);
                    }
                    $events_text .= "Date: {$date_str}\n";
                }

                if ($start_time) {
                    $time_str = self::format_time($start_time);
                    if ($end_time) {
                        $time_str .= ' - ' . self::format_time($end_time);
                    }
                    $events_text .= "Time: {$time_str}\n";
                }

                // Location
                if ($location_name || $location_address) {
                    $location = $location_name ?: '';
                    if ($location_address) {
                        $location .= $location ? "\n       {$location_address}" : $location_address;
                    }
                    $events_text .= "Location: {$location}\n";
                }

                $events_text .= "Details: {$event_permalink}\n\n";
            } elseif ($ref['type'] === 'external' && !empty($ref['source_id'])) {
                // External event unavailable - include note
                $source = Announcement::get_external_source($ref['source_id']);
                $source_name = $source ? ($source['name'] ?? parse_url($source['url'], PHP_URL_HOST)) : 'External';
                $events_text .= "Event details unavailable from {$source_name}\n\n";
            }
        }

        return $events_text;
    }

    /**
     * Format a date string for email display
     *
     * @param string $date Date in Y-m-d format
     * @return string Formatted date
     */
    private static function format_date($date)
    {
        if (empty($date)) {
            return '';
        }

        try {
            $dt = new \DateTime($date);
            return $dt->format('l, F j, Y'); // e.g., "Saturday, January 15, 2025"
        } catch (\Exception $e) {
            return $date;
        }
    }

    /**
     * Format a time string for email display
     *
     * @param string $time Time in H:i format
     * @return string Formatted time
     */
    private static function format_time($time)
    {
        if (empty($time)) {
            return '';
        }

        try {
            $dt = new \DateTime($time);
            return $dt->format('g:i A'); // e.g., "2:30 PM"
        } catch (\Exception $e) {
            return $time;
        }
    }
}
