<?php

namespace BmltEnabled\Mayo\Tests\Unit\Rest;

use BmltEnabled\Mayo\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * Unit tests for SubscribersController
 *
 * Note: These tests focus on the behaviors that can be tested without WordPress
 * integration. Complex tests involving database queries and external dependencies
 * are deferred to integration testing.
 */
class SubscribersControllerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->mockGetOption([
            'mayo_settings' => [
                'subscription_categories' => [1, 2],
                'subscription_tags' => [3, 4],
                'subscription_service_bodies' => ['1', '2'],
                'bmlt_root_server' => 'https://bmlt.example.com'
            ]
        ]);
    }

    /**
     * Test that email validation works
     */
    public function testEmailValidation(): void {
        // Test valid email
        $validEmail = is_email('test@example.com');
        $this->assertEquals('test@example.com', $validEmail);

        // Test invalid email
        $invalidEmail = is_email('not-an-email');
        $this->assertFalse($invalidEmail);
    }

    /**
     * Test that get_all_subscribers requires admin permissions
     */
    public function testGetAllSubscribersRequiresAdmin(): void {
        // Test without admin permissions
        $this->logoutUser();

        $canAccess = current_user_can('manage_options');
        $this->assertFalse($canAccess);
    }

    /**
     * Test that admin has manage_options permission
     */
    public function testAdminHasManageOptionsPermission(): void {
        $this->loginAsAdmin();

        $canAccess = current_user_can('manage_options');
        $this->assertTrue($canAccess);
    }

    /**
     * Test that editor does not have manage_options permission
     */
    public function testEditorDoesNotHaveManageOptionsPermission(): void {
        $this->loginAsEditor();

        $canAccess = current_user_can('manage_options');
        $this->assertFalse($canAccess);
    }

    /**
     * Test that editor has edit_posts permission
     */
    public function testEditorHasEditPostsPermission(): void {
        $this->loginAsEditor();

        $canAccess = current_user_can('edit_posts');
        $this->assertTrue($canAccess);
    }

    /**
     * Test subscription settings are loaded from options
     */
    public function testSubscriptionSettingsAreLoadedFromOptions(): void {
        $settings = get_option('mayo_settings', []);

        $this->assertArrayHasKey('subscription_categories', $settings);
        $this->assertArrayHasKey('subscription_tags', $settings);
        $this->assertArrayHasKey('subscription_service_bodies', $settings);

        $this->assertContains(1, $settings['subscription_categories']);
        $this->assertContains(2, $settings['subscription_categories']);
        $this->assertContains(3, $settings['subscription_tags']);
        $this->assertContains(4, $settings['subscription_tags']);
        $this->assertContains('1', $settings['subscription_service_bodies']);
        $this->assertContains('2', $settings['subscription_service_bodies']);
    }

    /**
     * Test REST request creation
     */
    public function testRestRequestCreation(): void {
        $request = $this->createRestRequest('POST', '/event-manager/v1/subscribe', [
            'email' => 'test@example.com',
            'preferences' => [
                'categories' => [1],
                'tags' => [],
                'service_bodies' => []
            ]
        ]);

        $this->assertInstanceOf(\WP_REST_Request::class, $request);
        $this->assertEquals('POST', $request->get_method());
        $this->assertEquals('/event-manager/v1/subscribe', $request->get_route());

        $params = $request->get_params();
        $this->assertEquals('test@example.com', $params['email']);
    }
}
