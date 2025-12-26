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
     * @return array Result with success status and message
     */
    public static function subscribe($email)
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
                // Resend confirmation email
                self::send_confirmation_email($email, $existing->token);
                return [
                    'success' => true,
                    'code' => 'confirmation_resent',
                    'message' => 'A confirmation email has been sent. Please check your inbox (and spam folder).'
                ];
            } elseif ($existing->status === 'unsubscribed') {
                // Re-subscribe: generate new token and set to pending
                $token = self::generate_token();
                $wpdb->update(
                    $table_name,
                    [
                        'status' => 'pending',
                        'token' => $token,
                        'confirmed_at' => null
                    ],
                    ['id' => $existing->id],
                    ['%s', '%s', null],
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

        $result = $wpdb->insert(
            $table_name,
            [
                'email' => $email,
                'status' => 'pending',
                'token' => $token,
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s']
        );

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
        $message .= "Unsubscribe: {$unsubscribe_url}\n";

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

        $site_name = get_bloginfo('name');
        $title = html_entity_decode(get_the_title($announcement_id), ENT_QUOTES, 'UTF-8');
        $content = apply_filters('the_content', $announcement->post_content);
        $content_plain = wp_strip_all_tags($content);
        $permalink = get_permalink($announcement_id);

        // Get linked events
        $linked_events_text = self::get_linked_events_text($announcement_id);

        $subject = sprintf('[%s] %s', $site_name, $title);

        foreach ($subscribers as $subscriber) {
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
            $message .= "Unsubscribe: {$unsubscribe_url}\n";

            $headers = ['Content-Type: text/plain; charset=UTF-8'];

            $result = wp_mail($subscriber->email, $subject, $message, $headers);

            if (!$result) {
                error_log('Mayo Subscriber: Failed to send announcement email to ' . $subscriber->email);
            }
        }
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
