<?php
/**
 * PHPUnit Bootstrap for Brain Monkey Tests
 *
 * This bootstrap sets up the testing environment without requiring WordPress.
 * Uses Brain Monkey to mock WordPress functions.
 */

// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define WordPress constants that the plugin expects
if (!defined('ABSPATH')) {
    define('ABSPATH', '/fake/wordpress/path/');
}

// Mock WordPress classes that don't exist outside WordPress

if (!class_exists('WP_REST_Response')) {
    /**
     * Mock WP_REST_Response class
     */
    class WP_REST_Response {
        private $data;
        private $status;
        private $headers = [];

        public function __construct($data = null, $status = 200, $headers = []) {
            $this->data = $data;
            $this->status = $status;
            $this->headers = $headers;
        }

        public function get_data() {
            return $this->data;
        }

        public function get_status() {
            return $this->status;
        }

        public function get_headers() {
            return $this->headers;
        }

        public function set_data($data) {
            $this->data = $data;
        }

        public function set_status($status) {
            $this->status = $status;
        }

        public function header($key, $value) {
            $this->headers[$key] = $value;
        }
    }
}

if (!class_exists('WP_Error')) {
    /**
     * Mock WP_Error class
     */
    class WP_Error {
        private $code;
        private $message;
        private $data;

        public function __construct($code = '', $message = '', $data = '') {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code() {
            return $this->code;
        }

        public function get_error_message() {
            return $this->message;
        }

        public function get_error_data() {
            return $this->data;
        }

        public function get_error_codes() {
            return [$this->code];
        }

        public function get_error_messages($code = '') {
            return [$this->message];
        }

        public function has_errors() {
            return !empty($this->code);
        }
    }
}

if (!class_exists('WP_REST_Request')) {
    /**
     * Mock WP_REST_Request class
     */
    class WP_REST_Request implements \ArrayAccess {
        private $method;
        private $route;
        private $params = [];
        private $body_params = [];
        private $query_params = [];
        private $headers = [];
        private $body;
        private $attributes = [];

        public function __construct($method = 'GET', $route = '', $attributes = []) {
            $this->method = $method;
            $this->route = $route;
            $this->attributes = $attributes;
        }

        public function get_method() {
            return $this->method;
        }

        public function get_route() {
            return $this->route;
        }

        public function get_params() {
            return array_merge($this->query_params, $this->body_params, $this->params, $this->attributes);
        }

        public function get_param($key) {
            $params = $this->get_params();
            return $params[$key] ?? null;
        }

        public function set_param($key, $value) {
            $this->params[$key] = $value;
        }

        public function get_body_params() {
            return $this->body_params;
        }

        public function set_body_params($params) {
            $this->body_params = $params;
        }

        public function get_query_params() {
            return $this->query_params;
        }

        public function set_query_params($params) {
            $this->query_params = $params;
        }

        public function get_header($key) {
            return $this->headers[strtolower($key)] ?? null;
        }

        public function set_header($key, $value) {
            $this->headers[strtolower($key)] = $value;
        }

        public function get_body() {
            return $this->body;
        }

        public function set_body($body) {
            $this->body = $body;
        }

        public function get_json_params() {
            return $this->body_params;
        }

        #[\ReturnTypeWillChange]
        public function offsetExists($offset): bool {
            $params = $this->get_params();
            return isset($params[$offset]);
        }

        #[\ReturnTypeWillChange]
        public function offsetGet($offset): mixed {
            $params = $this->get_params();
            return $params[$offset] ?? null;
        }

        #[\ReturnTypeWillChange]
        public function offsetSet($offset, $value): void {
            $this->params[$offset] = $value;
        }

        #[\ReturnTypeWillChange]
        public function offsetUnset($offset): void {
            unset($this->params[$offset]);
        }
    }
}

if (!class_exists('WP_Query')) {
    /**
     * Mock WP_Query class
     */
    class WP_Query {
        public $posts = [];
        public $post;
        public $post_count = 0;
        public $found_posts = 0;
        public $max_num_pages = 0;
        private $current_post = -1;
        private $in_the_loop = false;
        public $query_vars = [];

        public function __construct($query = '') {
            if (!empty($query)) {
                $this->query($query);
            }
        }

        public function query($query) {
            $this->query_vars = $query;
            return $this->posts;
        }

        public function have_posts() {
            return ($this->current_post + 1 < $this->post_count);
        }

        public function the_post() {
            $this->current_post++;
            $this->post = $this->posts[$this->current_post];
            $this->in_the_loop = true;
            return $this->post;
        }

        public function rewind_posts() {
            $this->current_post = -1;
        }

        public function get($key) {
            return $this->query_vars[$key] ?? null;
        }

        public function set($key, $value) {
            $this->query_vars[$key] = $value;
        }

        public function is_main_query() {
            return false;
        }
    }
}

if (!class_exists('WP_Post')) {
    /**
     * Mock WP_Post class
     */
    class WP_Post {
        public $ID;
        public $post_author = 0;
        public $post_date = '';
        public $post_date_gmt = '';
        public $post_content = '';
        public $post_title = '';
        public $post_excerpt = '';
        public $post_status = 'publish';
        public $comment_status = 'open';
        public $ping_status = 'open';
        public $post_password = '';
        public $post_name = '';
        public $to_ping = '';
        public $pinged = '';
        public $post_modified = '';
        public $post_modified_gmt = '';
        public $post_content_filtered = '';
        public $post_parent = 0;
        public $guid = '';
        public $menu_order = 0;
        public $post_type = 'post';
        public $post_mime_type = '';
        public $comment_count = 0;
        public $filter;

        public function __construct($post = null) {
            if (is_object($post)) {
                foreach (get_object_vars($post) as $key => $value) {
                    $this->$key = $value;
                }
            } elseif (is_array($post)) {
                foreach ($post as $key => $value) {
                    $this->$key = $value;
                }
            }
        }

        public static function get_instance($post_id) {
            return new self(['ID' => $post_id]);
        }
    }
}

if (!class_exists('WP_Widget')) {
    /**
     * Mock WP_Widget class
     */
    class WP_Widget {
        public $id_base;
        public $name;
        public $widget_options;
        public $control_options;
        public $number = 1;

        public function __construct($id_base = '', $name = '', $widget_options = [], $control_options = []) {
            $this->id_base = $id_base;
            $this->name = $name;
            $this->widget_options = $widget_options;
            $this->control_options = $control_options;
        }

        public function widget($args, $instance) {}
        public function form($instance) {}
        public function update($new_instance, $old_instance) { return $new_instance; }

        public function get_field_id($field_name) {
            return 'widget-' . $this->id_base . '-' . $this->number . '-' . $field_name;
        }

        public function get_field_name($field_name) {
            return 'widget-' . $this->id_base . '[' . $this->number . '][' . $field_name . ']';
        }
    }
}

// Define common WordPress functions that are used as type hints
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return ($thing instanceof WP_Error);
    }
}

if (!function_exists('rest_ensure_response')) {
    function rest_ensure_response($response) {
        if ($response instanceof WP_REST_Response) {
            return $response;
        }
        return new WP_REST_Response($response);
    }
}

// WordPress upload/media functions (so FileUpload doesn't try to require files)
if (!function_exists('wp_handle_upload')) {
    function wp_handle_upload($file, $overrides = false) {
        return [];
    }
}

if (!function_exists('wp_generate_attachment_metadata')) {
    function wp_generate_attachment_metadata($attachment_id, $file) {
        return [];
    }
}

if (!function_exists('wp_insert_attachment')) {
    function wp_insert_attachment($attachment, $filename = false, $parent_post_id = 0, $wp_error = false) {
        return 0;
    }
}

if (!function_exists('wp_update_attachment_metadata')) {
    function wp_update_attachment_metadata($attachment_id, $data) {
        return true;
    }
}

if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name($filename) {
        return preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    }
}
