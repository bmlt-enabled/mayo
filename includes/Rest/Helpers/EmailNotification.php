<?php

namespace BmltEnabled\Mayo\Rest\Helpers;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Helper class for email notification utilities
 *
 * Centralizes email parsing and notification recipient handling.
 */
class EmailNotification {

    /**
     * Parse notification email string into array of valid emails
     *
     * Supports comma and semicolon separated email lists.
     *
     * @param string $email_string Comma/semicolon separated emails
     * @return array Array of valid email addresses
     */
    public static function parse_recipients($email_string) {
        if (empty($email_string)) {
            return [];
        }

        $valid_emails = [];

        // Check for comma or semicolon separators
        if (strpos($email_string, ',') !== false || strpos($email_string, ';') !== false) {
            // Split by comma or semicolon and trim each email
            $emails = preg_split('/[,;]/', $email_string);
            foreach ($emails as $email) {
                $email = trim($email);
                if (is_email($email)) {
                    $valid_emails[] = $email;
                }
            }
        } else {
            // Single email
            $email = trim($email_string);
            if (is_email($email)) {
                $valid_emails[] = $email;
            }
        }

        return $valid_emails;
    }

    /**
     * Get notification recipients from settings
     *
     * Falls back to admin email if no notification email is configured.
     *
     * @return array|string Array of email addresses or single email string
     */
    public static function get_notification_recipients() {
        $settings = get_option('mayo_settings', []);
        $notification_email = isset($settings['notification_email']) && !empty($settings['notification_email'])
            ? $settings['notification_email']
            : get_option('admin_email');

        $recipients = self::parse_recipients($notification_email);

        // If no valid emails found, use admin email
        if (empty($recipients)) {
            return get_option('admin_email');
        }

        // Return array for multiple recipients, string for single
        return count($recipients) === 1 ? $recipients[0] : $recipients;
    }

    /**
     * Get notification recipients as array
     *
     * Always returns an array, even for single recipient.
     *
     * @return array Array of email addresses
     */
    public static function get_notification_recipients_array() {
        $recipients = self::get_notification_recipients();

        if (is_array($recipients)) {
            return $recipients;
        }

        return [$recipients];
    }
}
