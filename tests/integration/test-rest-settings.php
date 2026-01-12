<?php

namespace BmltEnabled\Mayo\Tests\Integration;

use WP_UnitTestCase;
use BmltEnabled\Mayo\Rest;
use WP_REST_Request;
use WP_REST_Server;

class RestSettingsIntegrationTest extends WP_UnitTestCase {

    protected $server;

    public function setUp(): void {
        parent::setUp();

        // Initialize REST server
        global $wp_rest_server;
        $this->server = $wp_rest_server = new WP_REST_Server();
        do_action('rest_api_init');

        // Setup initial settings
        $this->setupInitialSettings();

        // Mock service body data for BMLT API calls
        $this->mockServiceBodyData();
    }

    public function tearDown(): void {
        parent::tearDown();

        global $wp_rest_server;
        $wp_rest_server = null;

        remove_all_filters('pre_http_request');

        // Clean up settings
        delete_option('mayo_settings');
        delete_option('mayo_external_sources');
    }

    /**
     * Test that GET /settings returns public settings data
     */
    public function testGetSettingsReturnsPublicData() {
        $request = new WP_REST_Request('GET', '/event-manager/v1/settings');
        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();

        $this->assertArrayHasKey('bmlt_root_server', $data);
        $this->assertArrayHasKey('notification_email', $data);
        $this->assertArrayHasKey('default_service_bodies', $data);
        $this->assertArrayHasKey('external_sources', $data);
        $this->assertArrayHasKey('subscription_categories', $data);
        $this->assertArrayHasKey('subscription_tags', $data);
        $this->assertArrayHasKey('subscription_service_bodies', $data);

        // Verify expected values
        $this->assertEquals('https://bmlt.example.com', $data['bmlt_root_server']);
        $this->assertEquals('admin@example.com', $data['notification_email']);
    }

    /**
     * Test that POST /settings requires admin permissions
     */
    public function testUpdateSettingsRequiresAdmin() {
        // Test without authentication
        $request = new WP_REST_Request('POST', '/event-manager/v1/settings');
        $request->set_body_params([
            'bmlt_root_server' => 'https://new.bmlt.com'
        ]);

        $response = $this->server->dispatch($request);

        // Should be forbidden for unauthenticated users
        $this->assertEquals(401, $response->get_status());

        // Test with non-admin user
        $editor_id = $this->factory->user->create(['role' => 'editor']);
        wp_set_current_user($editor_id);

        $request = new WP_REST_Request('POST', '/event-manager/v1/settings');
        $request->set_body_params([
            'bmlt_root_server' => 'https://new.bmlt.com'
        ]);

        $response = $this->server->dispatch($request);

        // Should still be forbidden for non-admin
        $this->assertEquals(403, $response->get_status());

        wp_set_current_user(0);
    }

    /**
     * Test that admin can update settings
     */
    public function testAdminCanUpdateSettings() {
        $admin_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_id);

        $request = new WP_REST_Request('POST', '/event-manager/v1/settings');
        $request->set_body_params([
            'bmlt_root_server' => 'https://updated.bmlt.com',
            'notification_email' => 'updated@example.com',
            'default_service_bodies' => '1,2,3'
        ]);

        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);

        // Verify settings were updated
        $this->assertEquals('https://updated.bmlt.com', $data['settings']['bmlt_root_server']);
        $this->assertEquals('updated@example.com', $data['settings']['notification_email']);
        $this->assertEquals('1,2,3', $data['settings']['default_service_bodies']);

        wp_set_current_user(0);
    }

    /**
     * Test that POST /settings sanitizes external sources
     */
    public function testUpdateSettingsSanitizesExternalSources() {
        $admin_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_id);

        $request = new WP_REST_Request('POST', '/event-manager/v1/settings');
        $request->set_body_params([
            'external_sources' => [
                [
                    'url' => 'https://external1.example.com',
                    'name' => 'External Source 1',
                    'event_type' => 'Meeting',
                    'service_body' => '1',
                    'enabled' => true
                ],
                [
                    'url' => 'https://external2.example.com',
                    'name' => '<script>alert("xss")</script>External Source 2',
                    'enabled' => false
                ]
            ]
        ]);

        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);

        // Verify external sources were saved
        $sources = $data['settings']['external_sources'];
        $this->assertCount(2, $sources);

        // Check first source
        $this->assertEquals('https://external1.example.com', $sources[0]['url']);
        $this->assertEquals('External Source 1', $sources[0]['name']);
        $this->assertEquals('Meeting', $sources[0]['event_type']);
        $this->assertEquals('1', $sources[0]['service_body']);
        $this->assertTrue($sources[0]['enabled']);
        $this->assertArrayHasKey('id', $sources[0]);

        // Check second source (name should be sanitized)
        $this->assertEquals('https://external2.example.com', $sources[1]['url']);
        // Script tags should be removed by sanitize_text_field
        $this->assertStringNotContainsString('<script>', $sources[1]['name']);
        $this->assertFalse($sources[1]['enabled']);

        wp_set_current_user(0);
    }

    /**
     * Test that empty URLs are filtered out from external sources
     */
    public function testExternalSourcesFilterEmptyUrls() {
        $admin_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_id);

        $request = new WP_REST_Request('POST', '/event-manager/v1/settings');
        $request->set_body_params([
            'external_sources' => [
                [
                    'url' => 'https://valid.example.com',
                    'name' => 'Valid Source',
                    'enabled' => true
                ],
                [
                    'url' => '',
                    'name' => 'Invalid Empty Source',
                    'enabled' => true
                ],
                [
                    'url' => '   ',
                    'name' => 'Whitespace Only Source',
                    'enabled' => true
                ]
            ]
        ]);

        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $sources = $data['settings']['external_sources'];

        // Only valid source should remain
        $this->assertCount(1, $sources);
        $this->assertEquals('https://valid.example.com', $sources[0]['url']);

        wp_set_current_user(0);
    }

    /**
     * Test that subscription settings are saved correctly
     */
    public function testUpdateSubscriptionSettings() {
        $admin_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_id);

        // Create categories and tags
        $category_id = wp_create_category('Subscription Category');
        $tag = wp_insert_term('Subscription Tag', 'post_tag');
        $tag_id = $tag['term_id'];

        $request = new WP_REST_Request('POST', '/event-manager/v1/settings');
        $request->set_body_params([
            'subscription_categories' => [$category_id],
            'subscription_tags' => [$tag_id],
            'subscription_service_bodies' => ['1', '2'],
            'subscription_new_option_behavior' => 'auto_include'
        ]);

        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);

        // Verify subscription settings
        $this->assertContains($category_id, $data['settings']['subscription_categories']);
        $this->assertContains($tag_id, $data['settings']['subscription_tags']);
        $this->assertContains('1', $data['settings']['subscription_service_bodies']);
        $this->assertContains('2', $data['settings']['subscription_service_bodies']);
        $this->assertEquals('auto_include', $data['settings']['subscription_new_option_behavior']);

        wp_set_current_user(0);
    }

    /**
     * Test that email validation works for notification email
     */
    public function testNotificationEmailValidation() {
        $admin_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_id);

        // Test with multiple valid emails
        $request = new WP_REST_Request('POST', '/event-manager/v1/settings');
        $request->set_body_params([
            'notification_email' => 'email1@example.com, email2@example.com; email3@example.com'
        ]);

        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();

        // All valid emails should be saved
        $this->assertStringContainsString('email1@example.com', $data['settings']['notification_email']);
        $this->assertStringContainsString('email2@example.com', $data['settings']['notification_email']);
        $this->assertStringContainsString('email3@example.com', $data['settings']['notification_email']);

        wp_set_current_user(0);
    }

    /**
     * Helper: Setup initial settings
     */
    private function setupInitialSettings() {
        $settings = [
            'bmlt_root_server' => 'https://bmlt.example.com',
            'notification_email' => 'admin@example.com',
            'default_service_bodies' => '1,2',
            'subscription_categories' => [],
            'subscription_tags' => [],
            'subscription_service_bodies' => [],
            'subscription_new_option_behavior' => 'opt_in'
        ];
        update_option('mayo_settings', $settings);
        update_option('mayo_external_sources', []);
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
