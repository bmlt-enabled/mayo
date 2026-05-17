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
     * Get the wpdb instance
     *
     * This method is separated to allow for mocking in unit tests.
     * Tests can extend this class and override this method to return a mock.
     *
     * @return \wpdb The WordPress database object
     */
    protected static function get_wpdb()
    {
        global $wpdb;
        return $wpdb;
    }

    /**
     * Get the full table name with prefix
     */
    public static function get_table_name()
    {
        $wpdb = static::get_wpdb();
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Create the subscribers table
     * Called on plugin activation
     */
    public static function create_table()
    {
        $wpdb = static::get_wpdb();

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
        $wpdb = static::get_wpdb();

        $email = sanitize_email($email);

        if (!is_email($email)) {
            return [
                'success' => false,
                'code' => 'invalid_email',
                'message' => __('Please enter a valid email address.', 'mayo-events-manager')
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
                    'message' => __('This email is already subscribed.', 'mayo-events-manager')
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
                    'message' => __('A confirmation email has been sent. Please check your inbox (and spam folder).', 'mayo-events-manager')
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
                    'message' => __('A confirmation email has been sent. Please check your inbox (and spam folder).', 'mayo-events-manager')
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
                'message' => __('An error occurred. Please try again.', 'mayo-events-manager')
            ];
        }

        // Send confirmation email
        self::send_confirmation_email($email, $token);

        return [
            'success' => true,
            'code' => 'confirmation_sent',
            'message' => __('A confirmation email has been sent. Please check your inbox (and spam folder).', 'mayo-events-manager')
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
        $wpdb = static::get_wpdb();

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
        $wpdb = static::get_wpdb();

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
        $wpdb = static::get_wpdb();

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
                'message' => __('Invalid or expired confirmation link.', 'mayo-events-manager')
            ];
        }

        if ($subscriber->status === 'active') {
            return [
                'success' => true,
                'code' => 'already_confirmed',
                'message' => __('Your subscription is already confirmed.', 'mayo-events-manager')
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
            'message' => __('Your subscription has been confirmed! You will now receive announcement emails.', 'mayo-events-manager')
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
        $wpdb = static::get_wpdb();

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
                'message' => __('Invalid unsubscribe link.', 'mayo-events-manager')
            ];
        }

        if ($subscriber->status === 'unsubscribed') {
            return [
                'success' => true,
                'code' => 'already_unsubscribed',
                'message' => __('You have already been unsubscribed.', 'mayo-events-manager')
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
            'message' => __('You have been unsubscribed and will no longer receive announcement emails.', 'mayo-events-manager')
        ];
    }

    /**
     * Get all active subscribers
     *
     * @return array Array of subscriber objects
     */
    public static function get_active_subscribers()
    {
        $wpdb = static::get_wpdb();

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
        $wpdb = static::get_wpdb();

        $table_name = self::get_table_name();

        return $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY created_at DESC"
        );
    }

    /**
     * Update subscriber status by ID (admin only)
     *
     * @param int $id Subscriber ID
     * @param string $status New status (active, pending, unsubscribed)
     * @return bool Success status
     */
    public static function update_status($id, $status)
    {
        $wpdb = static::get_wpdb();

        $id = intval($id);
        $status = sanitize_text_field($status);
        $table_name = self::get_table_name();

        $data = ['status' => $status];
        $format = ['%s'];

        // Set confirmed_at when activating
        if ($status === 'active') {
            $data['confirmed_at'] = current_time('mysql');
            $format[] = '%s';
        }

        $result = $wpdb->update(
            $table_name,
            $data,
            ['id' => $id],
            $format,
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Update subscriber preferences by ID (admin only)
     *
     * @param int $id Subscriber ID
     * @param array $preferences New preferences
     * @return bool Success status
     */
    public static function update_preferences_by_id($id, $preferences)
    {
        $wpdb = static::get_wpdb();

        $id = intval($id);
        $table_name = self::get_table_name();

        $result = $wpdb->update(
            $table_name,
            ['preferences' => wp_json_encode($preferences)],
            ['id' => $id],
            ['%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Delete subscriber by ID (admin only)
     *
     * @param int $id Subscriber ID
     * @return bool Success status
     */
    public static function delete($id)
    {
        $wpdb = static::get_wpdb();

        $id = intval($id);
        $table_name = self::get_table_name();

        $result = $wpdb->delete(
            $table_name,
            ['id' => $id],
            ['%d']
        );

        return $result !== false;
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

        // Check categories - cast to integers for consistent comparison
        if (!empty($prefs['categories']) && !empty($announcement_data['categories'])) {
            $pref_categories = array_map('intval', $prefs['categories']);
            $announcement_categories = array_map('intval', $announcement_data['categories']);
            $matching_categories = array_intersect($pref_categories, $announcement_categories);
            if (!empty($matching_categories)) {
                foreach ($matching_categories as $cat_id) {
                    $term = get_term($cat_id, 'category');
                    if ($term && !is_wp_error($term)) {
                        $reasons['categories'][] = $term->name;
                    }
                }
            }
        }

        // Check tags - cast to integers for consistent comparison
        if (!empty($prefs['tags']) && !empty($announcement_data['tags'])) {
            $pref_tags = array_map('intval', $prefs['tags']);
            $announcement_tags = array_map('intval', $announcement_data['tags']);
            $matching_tags = array_intersect($pref_tags, $announcement_tags);
            if (!empty($matching_tags)) {
                foreach ($matching_tags as $tag_id) {
                    $term = get_term($tag_id, 'post_tag');
                    if ($term && !is_wp_error($term)) {
                        $reasons['tags'][] = $term->name;
                    }
                }
            }
        }

        // Check service body - cast to string for consistent comparison
        if (!empty($prefs['service_bodies']) && !empty($announcement_data['service_body'])) {
            $pref_service_bodies = array_map('strval', $prefs['service_bodies']);
            if (in_array((string) $announcement_data['service_body'], $pref_service_bodies, true)) {
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

        /* translators: %s: site name */
        $subject = sprintf(__('Please confirm your subscription to %s announcements', 'mayo-events-manager'), $site_name);

        /* translators: %s: site name */
        $message = sprintf(__('You have requested to subscribe to announcements from %s.', 'mayo-events-manager'), $site_name) . "\n\n";
        $message .= __('Click the link below to confirm your subscription:', 'mayo-events-manager') . "\n";
        $message .= "{$confirm_url}\n\n";
        $message .= __("If you didn't request this, you can safely ignore this email.", 'mayo-events-manager') . "\n\n";
        $message .= __("Note: If you don't see our emails, please check your spam or junk folder.", 'mayo-events-manager');

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

        /* translators: %s: site name */
        $subject = sprintf(__('Welcome to %s announcements', 'mayo-events-manager'), $site_name);

        /* translators: %s: site name */
        $message = sprintf(__("You're now subscribed to announcements from %s.", 'mayo-events-manager'), $site_name) . "\n\n";
        $message .= __("You'll receive an email whenever a new announcement is published.", 'mayo-events-manager') . "\n\n";
        $message .= "---\n";
        $message .= __('Manage preferences / Unsubscribe:', 'mayo-events-manager') . " {$unsubscribe_url}\n";

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

            $message .= __('View online:', 'mayo-events-manager') . " {$permalink}\n\n";
            $message .= "---\n";
            $message .= __('Manage preferences / Unsubscribe:', 'mayo-events-manager') . " {$unsubscribe_url}\n";

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

        // Check categories - cast to integers for consistent comparison
        if (!empty($prefs['categories']) && !empty($announcement_data['categories'])) {
            $pref_categories = array_map('intval', $prefs['categories']);
            $announcement_categories = array_map('intval', $announcement_data['categories']);
            $matching_categories = array_intersect($pref_categories, $announcement_categories);
            if (!empty($matching_categories)) {
                return true;
            }
        }

        // Check tags - cast to integers for consistent comparison
        if (!empty($prefs['tags']) && !empty($announcement_data['tags'])) {
            $pref_tags = array_map('intval', $prefs['tags']);
            $announcement_tags = array_map('intval', $announcement_data['tags']);
            $matching_tags = array_intersect($pref_tags, $announcement_tags);
            if (!empty($matching_tags)) {
                return true;
            }
        }

        // Check service body - cast to string for consistent comparison
        if (!empty($prefs['service_bodies']) && !empty($announcement_data['service_body'])) {
            $pref_service_bodies = array_map('strval', $prefs['service_bodies']);
            if (in_array((string) $announcement_data['service_body'], $pref_service_bodies, true)) {
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
        $events_text .= _n(
            'RELATED EVENT',
            'RELATED EVENTS',
            count($linked_refs),
            'mayo-events-manager'
        ) . "\n";
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
                    $events_text .= __('Source:', 'mayo-events-manager') . ' ' . $resolved['source']['name'] . "\n";
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
                    $events_text .= __('Date:', 'mayo-events-manager') . " {$date_str}\n";
                }

                if ($start_time) {
                    $time_str = self::format_time($start_time);
                    if ($end_time) {
                        $time_str .= ' - ' . self::format_time($end_time);
                    }
                    $events_text .= __('Time:', 'mayo-events-manager') . " {$time_str}\n";
                }

                // Location
                if ($location_name || $location_address) {
                    $location = $location_name ?: '';
                    if ($location_address) {
                        $location .= $location ? "\n       {$location_address}" : $location_address;
                    }
                    $events_text .= __('Location:', 'mayo-events-manager') . " {$location}\n";
                }

                $events_text .= __('Details:', 'mayo-events-manager') . " {$event_permalink}\n\n";
            } elseif ($ref['type'] === 'external' && !empty($ref['source_id'])) {
                // External event unavailable - include note
                $source = Announcement::get_external_source($ref['source_id']);
                $source_name = $source ? ($source['name'] ?? parse_url($source['url'], PHP_URL_HOST)) : __('External', 'mayo-events-manager');
                /* translators: %s: external source name */
                $events_text .= sprintf(__('Event details unavailable from %s', 'mayo-events-manager'), $source_name) . "\n\n";
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
