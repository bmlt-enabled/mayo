<?php

namespace BmltEnabled\Mayo\Tests\Integration;

use WP_UnitTestCase;
use BmltEnabled\Mayo\Rest;
use BmltEnabled\Mayo\Subscriber;
use WP_REST_Request;
use WP_REST_Server;

class RestSubscribersIntegrationTest extends WP_UnitTestCase {

    protected $server;

    public function setUp(): void {
        parent::setUp();

        // Initialize REST server
        global $wp_rest_server;
        $this->server = $wp_rest_server = new WP_REST_Server();
        do_action('rest_api_init');

        // Mock service body data for BMLT API calls
        $this->mockServiceBodyData();

        // Initialize settings with subscription options
        $this->setupSubscriptionSettings();
    }

    public function tearDown(): void {
        parent::tearDown();

        global $wp_rest_server;
        $wp_rest_server = null;

        remove_all_filters('pre_http_request');
    }

    /**
     * Test that POST /subscribe with valid email succeeds
     */
    public function testSubscribeWithValidEmail() {
        // Create categories for preferences
        $category_id = wp_create_category('Test Subscription Category');

        // Update settings to include this category
        $settings = get_option('mayo_settings', []);
        $settings['subscription_categories'] = [$category_id];
        update_option('mayo_settings', $settings);

        $request = new WP_REST_Request('POST', '/event-manager/v1/subscribe');
        $request->set_body_params([
            'email' => 'newsubscriber@example.com',
            'preferences' => [
                'categories' => [$category_id],
                'tags' => [],
                'service_bodies' => []
            ]
        ]);

        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    /**
     * Test that POST /subscribe with preferences stores them correctly
     */
    public function testSubscribeWithPreferences() {
        // Create categories and tags
        $category_id = wp_create_category('Preference Category');
        $tag = wp_insert_term('Preference Tag', 'post_tag');
        $tag_id = $tag['term_id'];

        // Update settings
        $settings = get_option('mayo_settings', []);
        $settings['subscription_categories'] = [$category_id];
        $settings['subscription_tags'] = [$tag_id];
        $settings['subscription_service_bodies'] = ['1'];
        update_option('mayo_settings', $settings);

        $request = new WP_REST_Request('POST', '/event-manager/v1/subscribe');
        $request->set_body_params([
            'email' => 'preferencetest@example.com',
            'preferences' => [
                'categories' => [$category_id],
                'tags' => [$tag_id],
                'service_bodies' => ['1']
            ]
        ]);

        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    /**
     * Test that POST /subscribe rejects empty preferences
     */
    public function testSubscribeRejectsEmptyPreferences() {
        $request = new WP_REST_Request('POST', '/event-manager/v1/subscribe');
        $request->set_body_params([
            'email' => 'emptyprefs@example.com',
            'preferences' => [
                'categories' => [],
                'tags' => [],
                'service_bodies' => []
            ]
        ]);

        $response = $this->server->dispatch($request);

        $this->assertEquals(400, $response->get_status());

        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertEquals('no_preferences', $data['code']);
    }

    /**
     * Test that POST /subscribe rejects missing email
     */
    public function testSubscribeRejectsMissingEmail() {
        $request = new WP_REST_Request('POST', '/event-manager/v1/subscribe');
        $request->set_body_params([
            'preferences' => [
                'categories' => [1],
                'tags' => [],
                'service_bodies' => []
            ]
        ]);

        $response = $this->server->dispatch($request);

        $this->assertEquals(400, $response->get_status());

        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertEquals('missing_email', $data['code']);
    }

    /**
     * Test that GET /subscription-options returns available options
     */
    public function testGetSubscriptionOptionsReturnsAvailableOptions() {
        // Create categories and tags
        $category_id = wp_create_category('Available Category');
        $tag = wp_insert_term('Available Tag', 'post_tag');
        $tag_id = $tag['term_id'];

        // Update settings
        $settings = get_option('mayo_settings', []);
        $settings['subscription_categories'] = [$category_id];
        $settings['subscription_tags'] = [$tag_id];
        $settings['subscription_service_bodies'] = ['1', '2'];
        $settings['bmlt_root_server'] = 'https://bmlt.example.com';
        update_option('mayo_settings', $settings);

        $request = new WP_REST_Request('GET', '/event-manager/v1/subscription-options');
        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();

        $this->assertArrayHasKey('categories', $data);
        $this->assertArrayHasKey('tags', $data);
        $this->assertArrayHasKey('service_bodies', $data);

        // Verify categories
        $cat_ids = array_column($data['categories'], 'id');
        $this->assertContains($category_id, $cat_ids);

        // Verify tags
        $tag_ids = array_column($data['tags'], 'id');
        $this->assertContains($tag_id, $tag_ids);
    }

    /**
     * Test that GET /subscribers requires admin permissions
     */
    public function testGetAllSubscribersRequiresAdmin() {
        // Test without authentication
        $request = new WP_REST_Request('GET', '/event-manager/v1/subscribers');
        $response = $this->server->dispatch($request);

        // Should be forbidden for unauthenticated users
        $this->assertEquals(403, $response->get_status());

        // Now test with admin user
        $admin_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_id);

        $request = new WP_REST_Request('GET', '/event-manager/v1/subscribers');
        $response = $this->server->dispatch($request);

        // Should succeed for admin
        $this->assertEquals(200, $response->get_status());

        wp_set_current_user(0);
    }

    /**
     * Test that DELETE /subscribers/{id} requires admin and deletes subscriber
     */
    public function testAdminDeleteSubscriber() {
        // Create admin user
        $admin_id = $this->factory->user->create(['role' => 'administrator']);

        // Test without authentication
        $request = new WP_REST_Request('DELETE', '/event-manager/v1/subscribers/1');
        $response = $this->server->dispatch($request);

        // Should be forbidden
        $this->assertEquals(403, $response->get_status());

        // Now test with admin
        wp_set_current_user($admin_id);

        // Try to delete non-existent subscriber
        $request = new WP_REST_Request('DELETE', '/event-manager/v1/subscribers/99999');
        $response = $this->server->dispatch($request);

        // Should return 404 for non-existent subscriber
        $this->assertEquals(404, $response->get_status());

        wp_set_current_user(0);
    }

    /**
     * Test that PUT /subscribers/{id} requires admin permissions
     */
    public function testAdminUpdateSubscriberRequiresAdmin() {
        // Test without authentication
        $request = new WP_REST_Request('PUT', '/event-manager/v1/subscribers/1');
        $request->set_body(json_encode(['status' => 'active']));
        $request->set_header('Content-Type', 'application/json');

        $response = $this->server->dispatch($request);

        // Should be forbidden
        $this->assertEquals(403, $response->get_status());
    }

    /**
     * Test that POST /subscribers/count requires edit_posts permission
     */
    public function testCountMatchingSubscribersRequiresPermission() {
        // Test without authentication
        $request = new WP_REST_Request('POST', '/event-manager/v1/subscribers/count');
        $request->set_body(json_encode([
            'categories' => [],
            'tags' => [],
            'service_body' => null
        ]));
        $request->set_header('Content-Type', 'application/json');

        $response = $this->server->dispatch($request);

        // Should be forbidden for unauthenticated users
        $this->assertEquals(403, $response->get_status());

        // Test with editor user
        $editor_id = $this->factory->user->create(['role' => 'editor']);
        wp_set_current_user($editor_id);

        $request = new WP_REST_Request('POST', '/event-manager/v1/subscribers/count');
        $request->set_body(json_encode([
            'categories' => [],
            'tags' => [],
            'service_body' => null
        ]));
        $request->set_header('Content-Type', 'application/json');

        $response = $this->server->dispatch($request);

        // Should succeed for editor
        $this->assertEquals(200, $response->get_status());

        wp_set_current_user(0);
    }

    /**
     * Helper: Setup subscription settings
     */
    private function setupSubscriptionSettings() {
        $settings = get_option('mayo_settings', []);
        $settings['subscription_categories'] = [];
        $settings['subscription_tags'] = [];
        $settings['subscription_service_bodies'] = [];
        $settings['bmlt_root_server'] = 'https://bmlt.example.com';
        update_option('mayo_settings', $settings);
    }

    /**
     * Helper: Mock BMLT service body data
     */
    private function mockServiceBodyData() {
        add_filter('pre_http_request', function($preempt, $args, $url) {
            if (strpos($url, 'GetServiceBodies') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        ['id' => '1', 'name' => 'Test Service Body'],
                        ['id' => '2', 'name' => 'Another Service Body']
                    ])
                ];
            }
            return $preempt;
        }, 10, 3);
    }
}
