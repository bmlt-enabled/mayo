<?php

namespace BmltEnabled\Mayo\Rest\Helpers;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Helper class for handling file uploads
 *
 * Centralizes file upload processing for event and announcement submissions.
 */
class FileUpload {

    /**
     * Process uploaded files and attach to post
     *
     * @param int $post_id Post ID to attach files to
     * @return array Array of attachment IDs
     */
    public static function process_uploads($post_id) {
        $attachment_ids = [];

        if (empty($_FILES)) {
            return $attachment_ids;
        }

        // Load required WordPress functions
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

                // Set first image as featured image
                self::maybe_set_featured_image($post_id, $attachment_id, $uploaded_file['type']);
            }
        }

        return $attachment_ids;
    }

    /**
     * Set attachment as featured image if it's an image
     *
     * Only sets if no featured image is already set.
     *
     * @param int $post_id Post ID
     * @param int $attachment_id Attachment ID
     * @param string $mime_type File MIME type
     */
    public static function maybe_set_featured_image($post_id, $attachment_id, $mime_type) {
        // Only process images
        if (strpos($mime_type, 'image/') !== 0) {
            return;
        }

        // Only set if no featured image exists
        if (!has_post_thumbnail($post_id)) {
            set_post_thumbnail($post_id, $attachment_id);
        }
    }

    /**
     * Get list of uploaded file names from $_FILES
     *
     * Useful for email notifications.
     *
     * @return array Array of file names
     */
    public static function get_uploaded_file_names() {
        $file_names = [];

        if (empty($_FILES)) {
            return $file_names;
        }

        foreach ($_FILES as $file_key => $file) {
            if (!empty($file['name'])) {
                $file_names[] = $file['name'];
            }
        }

        return $file_names;
    }
}
