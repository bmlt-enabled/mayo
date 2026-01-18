<?php

namespace BmltEnabled\Mayo\Tests\Unit;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Base test case for Brain Monkey tests
 *
 * Provides common setup/teardown and helper methods for mocking WordPress functions.
 */
abstract class TestCase extends PHPUnitTestCase {

    /**
     * Mock post meta storage
     *
     * @var array
     */
    protected $postMeta = [];

    /**
     * Mock options storage
     *
     * @var array
     */
    protected $options = [];

    /**
     * Captured emails for verification
     *
     * @var array
     */
    protected $capturedEmails = [];

    /**
     * Captured error logs for verification
     *
     * @var array
     */
    protected $capturedLogs = [];

    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        // Reset storage
        $this->postMeta = [];
        $this->options = [];
        $this->capturedEmails = [];
        $this->capturedLogs = [];

        // Set up common WordPress function stubs
        $this->setupCommonStubs();
    }

    /**
     * Tear down test environment
     */
    protected function tearDown(): void {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Set up common WordPress function stubs
     */
    protected function setupCommonStubs(): void {
        // Sanitization functions - pass through for testing
        Functions\stubs([
            'sanitize_text_field' => function($str) {
                return trim(strip_tags($str));
            },
            'sanitize_textarea_field' => function($str) {
                return trim(strip_tags($str));
            },
            'sanitize_email' => function($email) {
                return filter_var($email, FILTER_SANITIZE_EMAIL);
            },
            'esc_url_raw' => function($url) {
                return filter_var($url, FILTER_SANITIZE_URL);
            },
            'esc_html' => function($text) {
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            },
            'esc_attr' => function($text) {
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            },
            'wp_unslash' => function($value) {
                return stripslashes($value);
            },
            'absint' => function($value) {
                return abs((int) $value);
            },
        ]);

        // Email validation
        Functions\when('is_email')->alias(function($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : false;
        });

        // URL functions
        Functions\stubs([
            'home_url' => 'https://example.com',
            'site_url' => 'https://example.com',
            'admin_url' => function($path = '') {
                return 'https://example.com/wp-admin/' . ltrim($path, '/');
            },
            'rest_url' => function($path = '') {
                return 'https://example.com/wp-json/' . ltrim($path, '/');
            },
            'get_site_url' => 'https://example.com',
            'get_permalink' => function($post_id) {
                return 'https://example.com/event/' . $post_id;
            },
            'get_edit_post_link' => function($post_id, $context = 'display') {
                return 'https://example.com/wp-admin/post.php?post=' . $post_id . '&action=edit';
            },
        ]);

        // WordPress timezone
        Functions\when('wp_timezone_string')->justReturn('America/New_York');
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('America/New_York'));
        Functions\when('current_time')->alias(function($type, $gmt = false) {
            if ($type === 'Y-m-d') {
                return date('Y-m-d');
            }
            return date('Y-m-d H:i:s');
        });
        Functions\when('wp_date')->alias(function($format, $timestamp = null, $timezone = null) {
            return date($format, $timestamp ?? time());
        });

        // Default: no user logged in
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('get_current_user_id')->justReturn(0);
        Functions\when('wp_get_current_user')->justReturn(null);

        // i18n functions
        Functions\stubs([
            '__' => function($text, $domain = 'default') {
                return $text;
            },
            '_e' => function($text, $domain = 'default') {
                echo $text;
            },
            'esc_html__' => function($text, $domain = 'default') {
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            },
        ]);

        // Taxonomy functions (basic stubs)
        Functions\when('wp_set_post_categories')->justReturn([]);
        Functions\when('wp_set_post_tags')->justReturn([]);
        Functions\when('wp_get_post_categories')->justReturn([]);
        Functions\when('wp_get_post_tags')->justReturn([]);
        Functions\when('wp_create_category')->justReturn(1);
        Functions\when('wp_insert_term')->justReturn(['term_id' => 1, 'term_taxonomy_id' => 1]);
        Functions\when('get_category')->justReturn(null);

        // Hook functions
        Functions\when('add_action')->justReturn(true);
        Functions\when('add_filter')->justReturn(true);
        Functions\when('remove_action')->justReturn(true);
        Functions\when('remove_filter')->justReturn(true);
        Functions\when('do_action')->justReturn(null);
        Functions\when('apply_filters')->alias(function($tag, $value) {
            return $value;
        });
        Functions\when('has_filter')->justReturn(false);

        // Query args
        Functions\when('add_query_arg')->alias(function($args, $url = '') {
            if (is_string($args)) {
                return $url . (strpos($url, '?') !== false ? '&' : '?') . $args;
            }
            $query = http_build_query($args);
            return $url . (strpos($url, '?') !== false ? '&' : '?') . $query;
        });

        // Register route (just return true)
        Functions\when('register_rest_route')->justReturn(true);
    }

    /**
     * Mock get_option to use local storage
     *
     * @param array $initialOptions Initial options to set
     */
    protected function mockGetOption(array $initialOptions = []): void {
        $this->options = $initialOptions;

        Functions\when('get_option')->alias(function($option, $default = false) {
            return $this->options[$option] ?? $default;
        });

        Functions\when('update_option')->alias(function($option, $value, $autoload = null) {
            $this->options[$option] = $value;
            return true;
        });

        Functions\when('delete_option')->alias(function($option) {
            unset($this->options[$option]);
            return true;
        });
    }

    /**
     * Mock post meta functions to use local storage
     */
    protected function mockPostMeta(): void {
        Functions\when('get_post_meta')->alias(function($post_id, $key = '', $single = false) {
            if (empty($key)) {
                return $this->postMeta[$post_id] ?? [];
            }
            $value = $this->postMeta[$post_id][$key] ?? null;
            if ($single) {
                return $value;
            }
            return $value !== null ? [$value] : [];
        });

        Functions\when('add_post_meta')->alias(function($post_id, $key, $value, $unique = false) {
            if (!isset($this->postMeta[$post_id])) {
                $this->postMeta[$post_id] = [];
            }
            $this->postMeta[$post_id][$key] = $value;
            return true;
        });

        Functions\when('update_post_meta')->alias(function($post_id, $key, $value, $prev_value = '') {
            if (!isset($this->postMeta[$post_id])) {
                $this->postMeta[$post_id] = [];
            }
            $this->postMeta[$post_id][$key] = $value;
            return true;
        });

        Functions\when('delete_post_meta')->alias(function($post_id, $key, $value = '') {
            if (isset($this->postMeta[$post_id][$key])) {
                unset($this->postMeta[$post_id][$key]);
                return true;
            }
            return false;
        });
    }

    /**
     * Set post meta for testing
     *
     * @param int $postId
     * @param array $meta Key-value pairs of meta data
     */
    protected function setPostMeta(int $postId, array $meta): void {
        $this->postMeta[$postId] = $meta;
    }

    /**
     * Mock wp_mail to capture sent emails
     */
    protected function mockWpMail(): void {
        Functions\when('wp_mail')->alias(function($to, $subject, $message, $headers = '', $attachments = []) {
            $this->capturedEmails[] = [
                'to' => $to,
                'subject' => $subject,
                'message' => $message,
                'headers' => $headers,
                'attachments' => $attachments
            ];
            return true;
        });
    }

    /**
     * Get captured emails
     *
     * @return array
     */
    protected function getCapturedEmails(): array {
        return $this->capturedEmails;
    }

    /**
     * Create a mock REST request
     *
     * @param string $method HTTP method
     * @param string $route Route path
     * @param array $params Request parameters
     * @return \WP_REST_Request
     */
    protected function createRestRequest(string $method = 'GET', string $route = '', array $params = []): \WP_REST_Request {
        $request = new \WP_REST_Request($method, $route);

        if ($method === 'GET') {
            $request->set_query_params($params);
        } else {
            $request->set_body_params($params);
        }

        return $request;
    }

    /**
     * Create a mock post object
     *
     * @param array $data Post data
     * @return \WP_Post
     */
    protected function createMockPost(array $data = []): \WP_Post {
        $defaults = [
            'ID' => 1,
            'post_author' => 1,
            'post_date' => '2024-01-01 12:00:00',
            'post_date_gmt' => '2024-01-01 12:00:00',
            'post_content' => 'Test content',
            'post_title' => 'Test Title',
            'post_excerpt' => '',
            'post_status' => 'publish',
            'post_name' => 'test-title',
            'post_type' => 'mayo_event',
        ];

        return new \WP_Post(array_merge($defaults, $data));
    }

    /**
     * Mock wp_insert_post
     *
     * @param int $returnId The post ID to return
     */
    protected function mockWpInsertPost(int $returnId): void {
        Functions\expect('wp_insert_post')
            ->andReturn($returnId);
    }

    /**
     * Mock get_post to return a specific post
     *
     * @param \WP_Post|null $post The post to return
     */
    protected function mockGetPost(?\WP_Post $post): void {
        Functions\when('get_post')->justReturn($post);
    }

    /**
     * Mock get_posts to return specific posts
     *
     * @param array $posts Array of posts to return
     */
    protected function mockGetPosts(array $posts): void {
        Functions\when('get_posts')->justReturn($posts);
    }

    /**
     * Mock get_the_title
     *
     * @param string|null $title Title to return, or null to derive from post
     */
    protected function mockGetTheTitle(?string $title = null): void {
        Functions\when('get_the_title')->alias(function($post) use ($title) {
            if ($title !== null) {
                return $title;
            }
            if ($post instanceof \WP_Post) {
                return $post->post_title;
            }
            return 'Test Event';
        });
    }

    /**
     * Mock has_post_thumbnail
     *
     * @param bool $has Whether the post has a thumbnail
     */
    protected function mockHasPostThumbnail(bool $has = false): void {
        Functions\when('has_post_thumbnail')->justReturn($has);
        Functions\when('get_the_post_thumbnail_url')->justReturn(
            $has ? 'https://example.com/image.jpg' : ''
        );
    }

    /**
     * Set the current user's capabilities
     *
     * @param array $capabilities Array of capability => true/false
     */
    protected function setUserCapabilities(array $capabilities): void {
        Functions\when('current_user_can')->alias(function($capability) use ($capabilities) {
            return $capabilities[$capability] ?? false;
        });
    }

    /**
     * Mock user as admin
     */
    protected function loginAsAdmin(): void {
        $this->setUserCapabilities([
            'manage_options' => true,
            'edit_posts' => true,
            'publish_posts' => true,
            'administrator' => true,
        ]);
        Functions\when('get_current_user_id')->justReturn(1);
    }

    /**
     * Mock user as editor
     */
    protected function loginAsEditor(): void {
        $this->setUserCapabilities([
            'manage_options' => false,
            'edit_posts' => true,
            'publish_posts' => true,
            'editor' => true,
        ]);
        Functions\when('get_current_user_id')->justReturn(2);
    }

    /**
     * Mock user as logged out
     */
    protected function logoutUser(): void {
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('get_current_user_id')->justReturn(0);
    }

    /**
     * Mock wp_remote_get for API calls
     *
     * @param array $responses Array of URL patterns => response data
     */
    protected function mockWpRemoteGet(array $responses): void {
        Functions\when('wp_remote_get')->alias(function($url, $args = []) use ($responses) {
            foreach ($responses as $pattern => $response) {
                if (strpos($url, $pattern) !== false) {
                    return [
                        'response' => ['code' => $response['code'] ?? 200],
                        'body' => is_array($response['body']) ? json_encode($response['body']) : $response['body']
                    ];
                }
            }
            return new \WP_Error('http_request_failed', 'No mock response configured');
        });

        Functions\when('wp_remote_retrieve_body')->alias(function($response) {
            return $response['body'] ?? '';
        });

        Functions\when('wp_remote_retrieve_response_code')->alias(function($response) {
            return $response['response']['code'] ?? 200;
        });
    }

    /**
     * Mock wp_reset_postdata
     */
    protected function mockWpResetPostdata(): void {
        Functions\when('wp_reset_postdata')->justReturn(null);
    }

    /**
     * Mock trailingslashit
     */
    protected function mockTrailingslashit(): void {
        Functions\when('trailingslashit')->alias(function($string) {
            return rtrim($string, '/\\') . '/';
        });
    }
}
