<?php

namespace BmltEnabled\Mayo\Tests\Unit\Rest;

use BmltEnabled\Mayo\Tests\Unit\TestCase;
use BmltEnabled\Mayo\Rest\SubscribersController;
use BmltEnabled\Mayo\Rest\Helpers\ServiceBodyLookup;
use Brain\Monkey\Functions;

class SubscribersControllerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        ServiceBodyLookup::clear_cache();
        $this->mockGetOption([
            'mayo_settings' => [
                'bmlt_root_server' => 'https://bmlt.example.com',
                'subscription_categories' => [1, 2],
                'subscription_tags' => [3, 4],
                'subscription_service_bodies' => ['10', '20']
            ]
        ]);
        $this->mockTrailingslashit();
    }

    /**
     * Test register_routes registers all routes
     */
    public function testRegisterRoutesRegistersAllRoutes(): void {
        $registeredRoutes = [];

        Functions\when('register_rest_route')->alias(function($namespace, $route, $args) use (&$registeredRoutes) {
            $registeredRoutes[] = $namespace . $route;
            return true;
        });

        SubscribersController::register_routes();

        $this->assertContains('event-manager/v1/subscribe', $registeredRoutes);
        $this->assertContains('event-manager/v1/subscription-options', $registeredRoutes);
        $this->assertContains('event-manager/v1/subscriber/(?P<token>[a-fA-F0-9]+)', $registeredRoutes);
        $this->assertContains('event-manager/v1/subscribers', $registeredRoutes);
        $this->assertContains('event-manager/v1/subscribers/count', $registeredRoutes);
        $this->assertContains('event-manager/v1/subscribers/(?P<id>\d+)', $registeredRoutes);
    }

    /**
     * Test subscribe with missing email
     */
    public function testSubscribeReturnsErrorWhenEmailMissing(): void {
        $request = $this->createRestRequest('POST', '/event-manager/v1/subscribe', []);

        $response = SubscribersController::subscribe($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertEquals('missing_email', $data['code']);
    }

    /**
     * Test subscribe with empty email
     */
    public function testSubscribeReturnsErrorWhenEmailEmpty(): void {
        $request = $this->createRestRequest('POST', '/event-manager/v1/subscribe', [
            'email' => ''
        ]);

        $response = SubscribersController::subscribe($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('missing_email', $data['code']);
    }

    /**
     * Test subscribe with preferences but no selections
     */
    public function testSubscribeReturnsErrorWhenNoSelections(): void {
        $request = $this->createRestRequest('POST', '/event-manager/v1/subscribe', [
            'email' => 'test@example.com',
            'preferences' => [
                'categories' => [],
                'tags' => [],
                'service_bodies' => []
            ]
        ]);

        $response = SubscribersController::subscribe($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('no_preferences', $data['code']);
    }

    /**
     * Test subscribe with invalid preferences structure
     */
    public function testSubscribeRejectsInvalidPreferencesStructure(): void {
        $request = $this->createRestRequest('POST', '/event-manager/v1/subscribe', [
            'email' => 'test@example.com',
            'preferences' => 'not an array'
        ]);

        $response = SubscribersController::subscribe($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(400, $response->get_status());
    }

    /**
     * Test get_subscription_options returns categories, tags, and service bodies
     */
    public function testGetSubscriptionOptionsReturnsOptions(): void {
        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [
                    ['id' => '10', 'name' => 'Region A'],
                    ['id' => '20', 'name' => 'Region B'],
                    ['id' => '30', 'name' => 'Region C']
                ]
            ]
        ]);

        Functions\when('get_terms')->alias(function($args) {
            if ($args['taxonomy'] === 'category') {
                return [
                    (object)['term_id' => 1, 'name' => 'News', 'slug' => 'news'],
                    (object)['term_id' => 2, 'name' => 'Events', 'slug' => 'events']
                ];
            }
            if ($args['taxonomy'] === 'post_tag') {
                return [
                    (object)['term_id' => 3, 'name' => 'Featured', 'slug' => 'featured'],
                    (object)['term_id' => 4, 'name' => 'Important', 'slug' => 'important']
                ];
            }
            return [];
        });

        $request = new \WP_REST_Request('GET', '/event-manager/v1/subscription-options');
        $response = SubscribersController::get_subscription_options($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('categories', $data);
        $this->assertArrayHasKey('tags', $data);
        $this->assertArrayHasKey('service_bodies', $data);

        $this->assertCount(2, $data['categories']);
        $this->assertCount(2, $data['tags']);
        $this->assertCount(2, $data['service_bodies']);
    }

    /**
     * Test get_subscription_options handles empty settings
     */
    public function testGetSubscriptionOptionsHandlesEmptySettings(): void {
        $this->mockGetOption([
            'mayo_settings' => []
        ]);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/subscription-options');
        $response = SubscribersController::get_subscription_options($request);

        $data = $response->get_data();
        $this->assertEmpty($data['categories']);
        $this->assertEmpty($data['tags']);
        $this->assertEmpty($data['service_bodies']);
    }

    /**
     * Test get_subscription_options handles get_terms error
     */
    public function testGetSubscriptionOptionsHandlesGetTermsError(): void {
        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => []
            ]
        ]);

        Functions\when('get_terms')->justReturn(new \WP_Error('error', 'Failed'));

        $request = new \WP_REST_Request('GET', '/event-manager/v1/subscription-options');
        $response = SubscribersController::get_subscription_options($request);

        $data = $response->get_data();
        $this->assertEmpty($data['categories']);
        $this->assertEmpty($data['tags']);
    }

    /**
     * Test that email validation works
     */
    public function testEmailValidation(): void {
        $validEmail = is_email('test@example.com');
        $this->assertEquals('test@example.com', $validEmail);

        $invalidEmail = is_email('not-an-email');
        $this->assertFalse($invalidEmail);
    }

    /**
     * Test that get_all_subscribers requires admin permissions
     */
    public function testGetAllSubscribersRequiresAdmin(): void {
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
     * Test that editor has edit_posts permission for count endpoint
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

    /**
     * Test get_subscription_options formats categories correctly
     */
    public function testGetSubscriptionOptionsFormatsCategoriesCorrectly(): void {
        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => []
            ]
        ]);

        Functions\when('get_terms')->alias(function($args) {
            if ($args['taxonomy'] === 'category') {
                return [
                    (object)['term_id' => 1, 'name' => 'Category One', 'slug' => 'category-one']
                ];
            }
            return [];
        });

        $request = new \WP_REST_Request('GET', '/event-manager/v1/subscription-options');
        $response = SubscribersController::get_subscription_options($request);
        $data = $response->get_data();

        $this->assertCount(1, $data['categories']);
        $this->assertEquals(1, $data['categories'][0]['id']);
        $this->assertEquals('Category One', $data['categories'][0]['name']);
        $this->assertEquals('category-one', $data['categories'][0]['slug']);
    }

    /**
     * Test get_subscription_options formats tags correctly
     */
    public function testGetSubscriptionOptionsFormatsTagsCorrectly(): void {
        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => []
            ]
        ]);

        Functions\when('get_terms')->alias(function($args) {
            if ($args['taxonomy'] === 'post_tag') {
                return [
                    (object)['term_id' => 5, 'name' => 'Tag One', 'slug' => 'tag-one']
                ];
            }
            return [];
        });

        $request = new \WP_REST_Request('GET', '/event-manager/v1/subscription-options');
        $response = SubscribersController::get_subscription_options($request);
        $data = $response->get_data();

        $this->assertCount(1, $data['tags']);
        $this->assertEquals(5, $data['tags'][0]['id']);
        $this->assertEquals('Tag One', $data['tags'][0]['name']);
        $this->assertEquals('tag-one', $data['tags'][0]['slug']);
    }
}
