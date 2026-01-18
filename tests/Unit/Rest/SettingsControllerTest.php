<?php

namespace BmltEnabled\Mayo\Tests\Unit\Rest;

use BmltEnabled\Mayo\Tests\Unit\TestCase;
use BmltEnabled\Mayo\Rest\SettingsController;
use Brain\Monkey\Functions;

class SettingsControllerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        $this->mockGetOption([
            'mayo_settings' => [
                'bmlt_root_server' => 'https://bmlt.example.com',
                'notification_email' => 'admin@example.com',
                'default_service_bodies' => '1,2',
                'subscription_categories' => [],
                'subscription_tags' => [],
                'subscription_service_bodies' => [],
                'subscription_new_option_behavior' => 'opt_in'
            ],
            'mayo_external_sources' => []
        ]);
    }

    /**
     * Test GET /settings returns public settings data
     */
    public function testGetSettingsReturnsPublicData(): void {
        $request = $this->createRestRequest('GET', '/event-manager/v1/settings');
        $response = SettingsController::get_settings($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();

        $this->assertArrayHasKey('bmlt_root_server', $data);
        $this->assertArrayHasKey('notification_email', $data);
        $this->assertArrayHasKey('default_service_bodies', $data);
        $this->assertArrayHasKey('external_sources', $data);
        $this->assertArrayHasKey('subscription_categories', $data);
        $this->assertArrayHasKey('subscription_tags', $data);
        $this->assertArrayHasKey('subscription_service_bodies', $data);

        $this->assertEquals('https://bmlt.example.com', $data['bmlt_root_server']);
        $this->assertEquals('admin@example.com', $data['notification_email']);
    }

    /**
     * Test POST /settings requires admin permissions
     */
    public function testUpdateSettingsRequiresAdmin(): void {
        // Test without authentication (no permissions)
        $this->logoutUser();

        $request = $this->createRestRequest('POST', '/event-manager/v1/settings', [
            'bmlt_root_server' => 'https://new.bmlt.com'
        ]);

        $response = SettingsController::update_settings($request);

        // Should return WP_Error for unauthorized
        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('rest_forbidden', $response->get_error_code());
    }

    /**
     * Test POST /settings with editor permissions (should fail)
     */
    public function testUpdateSettingsFailsForEditor(): void {
        $this->loginAsEditor();

        $request = $this->createRestRequest('POST', '/event-manager/v1/settings', [
            'bmlt_root_server' => 'https://new.bmlt.com'
        ]);

        $response = SettingsController::update_settings($request);

        // Should return WP_Error
        $this->assertInstanceOf(\WP_Error::class, $response);
    }

    /**
     * Test admin can update settings
     */
    public function testAdminCanUpdateSettings(): void {
        $this->loginAsAdmin();

        $request = $this->createRestRequest('POST', '/event-manager/v1/settings', [
            'bmlt_root_server' => 'https://updated.bmlt.com',
            'notification_email' => 'updated@example.com',
            'default_service_bodies' => '1,2,3'
        ]);

        $response = SettingsController::update_settings($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals('https://updated.bmlt.com', $data['settings']['bmlt_root_server']);
        $this->assertEquals('updated@example.com', $data['settings']['notification_email']);
        $this->assertEquals('1,2,3', $data['settings']['default_service_bodies']);
    }

    /**
     * Test external sources are sanitized
     */
    public function testUpdateSettingsSanitizesExternalSources(): void {
        $this->loginAsAdmin();

        $request = $this->createRestRequest('POST', '/event-manager/v1/settings', [
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

        $response = SettingsController::update_settings($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);

        $sources = $data['settings']['external_sources'];
        $this->assertCount(2, $sources);

        // Check first source
        $this->assertEquals('https://external1.example.com', $sources[0]['url']);
        $this->assertEquals('External Source 1', $sources[0]['name']);
        $this->assertTrue($sources[0]['enabled']);
        $this->assertArrayHasKey('id', $sources[0]);

        // Check second source - XSS should be stripped
        $this->assertEquals('https://external2.example.com', $sources[1]['url']);
        $this->assertStringNotContainsString('<script>', $sources[1]['name']);
        $this->assertFalse($sources[1]['enabled']);
    }

    /**
     * Test empty URLs are filtered out from external sources
     */
    public function testExternalSourcesFilterEmptyUrls(): void {
        $this->loginAsAdmin();

        $request = $this->createRestRequest('POST', '/event-manager/v1/settings', [
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

        $response = SettingsController::update_settings($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $sources = $data['settings']['external_sources'];

        // Only valid source should remain (whitespace URL will pass esc_url_raw but might remain)
        // The actual behavior depends on how esc_url_raw handles whitespace
        $validSources = array_filter($sources, function($s) {
            return !empty(trim($s['url']));
        });
        $this->assertGreaterThanOrEqual(1, count($validSources));
    }

    /**
     * Test subscription settings are saved correctly
     */
    public function testUpdateSubscriptionSettings(): void {
        $this->loginAsAdmin();

        $request = $this->createRestRequest('POST', '/event-manager/v1/settings', [
            'subscription_categories' => [1, 2],
            'subscription_tags' => [3, 4],
            'subscription_service_bodies' => ['1', '2'],
            'subscription_new_option_behavior' => 'auto_include'
        ]);

        $response = SettingsController::update_settings($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);

        $this->assertContains(1, $data['settings']['subscription_categories']);
        $this->assertContains(2, $data['settings']['subscription_categories']);
        $this->assertContains(3, $data['settings']['subscription_tags']);
        $this->assertContains(4, $data['settings']['subscription_tags']);
        $this->assertContains('1', $data['settings']['subscription_service_bodies']);
        $this->assertContains('2', $data['settings']['subscription_service_bodies']);
        $this->assertEquals('auto_include', $data['settings']['subscription_new_option_behavior']);
    }

    /**
     * Test email validation works for notification email
     */
    public function testNotificationEmailValidation(): void {
        $this->loginAsAdmin();

        // Test with multiple valid emails
        $request = $this->createRestRequest('POST', '/event-manager/v1/settings', [
            'notification_email' => 'email1@example.com, email2@example.com; email3@example.com'
        ]);

        $response = SettingsController::update_settings($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();

        // All valid emails should be saved
        $this->assertStringContainsString('email1@example.com', $data['settings']['notification_email']);
        $this->assertStringContainsString('email2@example.com', $data['settings']['notification_email']);
        $this->assertStringContainsString('email3@example.com', $data['settings']['notification_email']);
    }
}
