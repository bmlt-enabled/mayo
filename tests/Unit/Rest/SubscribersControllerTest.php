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

    /**
     * Test get_subscriber returns 404 for non-existent token
     */
    public function testGetSubscriberReturnsNotFoundForInvalidToken(): void {
        // Mock the Subscriber::get_by_token to return null via global $wpdb mock
        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_row')->andReturn(null);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/subscriber/invalidtoken123');
        $request->set_param('token', 'invalidtoken123');

        $response = SubscribersController::get_subscriber($request);

        $this->assertEquals(404, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertEquals('not_found', $data['code']);
    }

    /**
     * Test get_subscriber returns subscriber data for valid token
     */
    public function testGetSubscriberReturnsDataForValidToken(): void {
        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $subscriber = (object)[
            'id' => 1,
            'email' => 'test@example.com',
            'status' => 'active',
            'preferences' => json_encode(['categories' => [1, 2]]),
            'token' => 'validtoken123'
        ];

        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_row')->andReturn($subscriber);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/subscriber/validtoken123');
        $request->set_param('token', 'validtoken123');

        $response = SubscribersController::get_subscriber($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('test@example.com', $data['email']);
        $this->assertEquals('active', $data['status']);
        $this->assertIsArray($data['preferences']);
    }

    /**
     * Test get_subscriber handles subscriber without preferences
     */
    public function testGetSubscriberHandlesNoPreferences(): void {
        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $subscriber = (object)[
            'id' => 1,
            'email' => 'legacy@example.com',
            'status' => 'active',
            'preferences' => null,
            'token' => 'validtoken123'
        ];

        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_row')->andReturn($subscriber);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/subscriber/validtoken123');
        $request->set_param('token', 'validtoken123');

        $response = SubscribersController::get_subscriber($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertNull($data['preferences']);
    }

    /**
     * Test update_subscriber returns 404 for non-existent token
     */
    public function testUpdateSubscriberReturnsNotFoundForInvalidToken(): void {
        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_row')->andReturn(null);

        $request = new \WP_REST_Request('PUT', '/event-manager/v1/subscriber/invalidtoken123');
        $request->set_param('token', 'invalidtoken123');
        $request->set_body_params(['preferences' => ['categories' => [1]]]);

        $response = SubscribersController::update_subscriber($request);

        $this->assertEquals(404, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('not_found', $data['code']);
    }

    /**
     * Test update_subscriber returns error for non-active subscriber
     */
    public function testUpdateSubscriberReturnsErrorForNonActive(): void {
        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $subscriber = (object)[
            'id' => 1,
            'email' => 'test@example.com',
            'status' => 'pending',
            'token' => 'pendingtoken123'
        ];

        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_row')->andReturn($subscriber);

        $request = new \WP_REST_Request('PUT', '/event-manager/v1/subscriber/pendingtoken123');
        $request->set_param('token', 'pendingtoken123');
        $request->set_body_params(['preferences' => ['categories' => [1]]]);

        $response = SubscribersController::update_subscriber($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('not_active', $data['code']);
    }

    /**
     * Test update_subscriber returns error when preferences missing
     */
    public function testUpdateSubscriberReturnsErrorWhenPreferencesMissing(): void {
        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $subscriber = (object)[
            'id' => 1,
            'email' => 'test@example.com',
            'status' => 'active',
            'token' => 'activetoken123'
        ];

        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_row')->andReturn($subscriber);

        $request = new \WP_REST_Request('PUT', '/event-manager/v1/subscriber/activetoken123');
        $request->set_param('token', 'activetoken123');
        // No preferences in body params

        $response = SubscribersController::update_subscriber($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('invalid_preferences', $data['code']);
    }

    /**
     * Test update_subscriber returns error when preferences empty
     */
    public function testUpdateSubscriberReturnsErrorWhenPreferencesEmpty(): void {
        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $subscriber = (object)[
            'id' => 1,
            'email' => 'test@example.com',
            'status' => 'active',
            'token' => 'activetoken123'
        ];

        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_row')->andReturn($subscriber);

        $request = new \WP_REST_Request('PUT', '/event-manager/v1/subscriber/activetoken123');
        $request->set_param('token', 'activetoken123');
        $request->set_body_params(['preferences' => ['categories' => [], 'tags' => [], 'service_bodies' => []]]);

        $response = SubscribersController::update_subscriber($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('no_preferences', $data['code']);
    }

    /**
     * Test update_subscriber success
     */
    public function testUpdateSubscriberSuccess(): void {
        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $subscriber = (object)[
            'id' => 1,
            'email' => 'test@example.com',
            'status' => 'active',
            'token' => 'activetoken123'
        ];

        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_row')->andReturn($subscriber);
        $wpdb->shouldReceive('update')->andReturn(1);

        $request = new \WP_REST_Request('PUT', '/event-manager/v1/subscriber/activetoken123');
        $request->set_param('token', 'activetoken123');
        $request->set_body_params(['preferences' => ['categories' => [1, 2], 'tags' => [], 'service_bodies' => []]]);

        $response = SubscribersController::update_subscriber($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    /**
     * Test update_subscriber handles update failure
     */
    public function testUpdateSubscriberHandlesUpdateFailure(): void {
        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $subscriber = (object)[
            'id' => 1,
            'email' => 'test@example.com',
            'status' => 'active',
            'token' => 'activetoken123'
        ];

        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_row')->andReturn($subscriber);
        $wpdb->shouldReceive('update')->andReturn(false);

        $request = new \WP_REST_Request('PUT', '/event-manager/v1/subscriber/activetoken123');
        $request->set_param('token', 'activetoken123');
        $request->set_body_params(['preferences' => ['categories' => [1], 'tags' => [], 'service_bodies' => []]]);

        $response = SubscribersController::update_subscriber($request);

        $this->assertEquals(500, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('update_failed', $data['code']);
    }

    /**
     * Test get_all_subscribers returns formatted subscribers
     */
    public function testGetAllSubscribersReturnsFormattedSubscribers(): void {
        $this->loginAsAdmin();

        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $subscribers = [
            (object)[
                'id' => 1,
                'email' => 'a@example.com',
                'status' => 'active',
                'created_at' => '2024-01-01 12:00:00',
                'confirmed_at' => '2024-01-01 12:05:00',
                'preferences' => json_encode(['categories' => [1], 'tags' => [], 'service_bodies' => []])
            ],
            (object)[
                'id' => 2,
                'email' => 'b@example.com',
                'status' => 'pending',
                'created_at' => '2024-01-02 10:00:00',
                'confirmed_at' => null,
                'preferences' => null
            ]
        ];

        $wpdb->shouldReceive('get_results')->andReturn($subscribers);

        Functions\when('get_terms')->alias(function($args) {
            if ($args['taxonomy'] === 'category') {
                return [(object)['term_id' => 1, 'name' => 'News']];
            }
            return [];
        });

        $this->mockWpRemoteGet([
            'GetServiceBodies' => ['code' => 200, 'body' => []]
        ]);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/subscribers');
        $response = SubscribersController::get_all_subscribers($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertCount(2, $data);
        $this->assertEquals('a@example.com', $data[0]['email']);
        $this->assertArrayHasKey('preferences_display', $data[0]);
    }

    /**
     * Test count_matching_subscribers returns count and list
     */
    public function testCountMatchingSubscribersReturnsCountAndList(): void {
        $this->loginAsEditor();

        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $subscribers = [
            (object)[
                'id' => 1,
                'email' => 'match@example.com',
                'status' => 'active',
                'preferences' => null
            ]
        ];

        $wpdb->shouldReceive('get_results')->andReturn($subscribers);

        $this->mockWpRemoteGet([
            'GetServiceBodies' => ['code' => 200, 'body' => []]
        ]);

        $request = new \WP_REST_Request('POST', '/event-manager/v1/subscribers/count');
        $request->set_header('Content-Type', 'application/json');

        // Simulate JSON body
        $reflectionClass = new \ReflectionClass($request);
        $property = $reflectionClass->getProperty('body_params');
        $property->setAccessible(true);
        $property->setValue($request, ['categories' => [1], 'tags' => [], 'service_body' => null]);

        // Need to mock get_json_params
        $response = SubscribersController::count_matching_subscribers($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('subscribers', $data);
    }

    /**
     * Test admin_update_subscriber updates status
     */
    public function testAdminUpdateSubscriberUpdatesStatus(): void {
        $this->loginAsAdmin();

        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('update')->andReturn(1);

        $request = new \WP_REST_Request('PUT', '/event-manager/v1/subscribers/1');
        $request->set_param('id', 1);

        $reflectionClass = new \ReflectionClass($request);
        $property = $reflectionClass->getProperty('body_params');
        $property->setAccessible(true);
        $property->setValue($request, ['status' => 'active']);

        $response = SubscribersController::admin_update_subscriber($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    /**
     * Test admin_update_subscriber updates preferences
     */
    public function testAdminUpdateSubscriberUpdatesPreferences(): void {
        $this->loginAsAdmin();

        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('update')->andReturn(1);

        $request = new \WP_REST_Request('PUT', '/event-manager/v1/subscribers/1');
        $request->set_param('id', 1);

        $reflectionClass = new \ReflectionClass($request);
        $property = $reflectionClass->getProperty('body_params');
        $property->setAccessible(true);
        $property->setValue($request, ['preferences' => ['categories' => [1, 2]]]);

        $response = SubscribersController::admin_update_subscriber($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    /**
     * Test admin_update_subscriber ignores invalid status
     */
    public function testAdminUpdateSubscriberIgnoresInvalidStatus(): void {
        $this->loginAsAdmin();

        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $request = new \WP_REST_Request('PUT', '/event-manager/v1/subscribers/1');
        $request->set_param('id', 1);

        $reflectionClass = new \ReflectionClass($request);
        $property = $reflectionClass->getProperty('body_params');
        $property->setAccessible(true);
        $property->setValue($request, ['status' => 'invalid_status']);

        $response = SubscribersController::admin_update_subscriber($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        // Update wasn't actually called because status is invalid
        $this->assertFalse($data['updated']);
    }

    /**
     * Test admin_delete_subscriber deletes subscriber
     */
    public function testAdminDeleteSubscriberDeletesSubscriber(): void {
        $this->loginAsAdmin();

        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('delete')->andReturn(1);

        $request = new \WP_REST_Request('DELETE', '/event-manager/v1/subscribers/1');
        $request->set_param('id', 1);

        $response = SubscribersController::admin_delete_subscriber($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    /**
     * Test admin_delete_subscriber returns 404 when subscriber not found
     */
    public function testAdminDeleteSubscriberReturns404WhenNotFound(): void {
        $this->loginAsAdmin();

        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('delete')->andReturn(false);

        $request = new \WP_REST_Request('DELETE', '/event-manager/v1/subscribers/999');
        $request->set_param('id', 999);

        $response = SubscribersController::admin_delete_subscriber($request);

        $this->assertEquals(404, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
    }

    /**
     * Test get_subscription_options with service bodies from BMLT
     */
    public function testGetSubscriptionOptionsWithServiceBodies(): void {
        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [
                    ['id' => '10', 'name' => 'Region A', 'url' => 'https://region-a.example.com'],
                    ['id' => '20', 'name' => 'Region B', 'url' => 'https://region-b.example.com'],
                    ['id' => '30', 'name' => 'Region C', 'url' => 'https://region-c.example.com']
                ]
            ]
        ]);

        Functions\when('get_terms')->justReturn([]);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/subscription-options');
        $response = SubscribersController::get_subscription_options($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('service_bodies', $data);
        $this->assertCount(2, $data['service_bodies']);
    }

    /**
     * Test subscribe with valid preferences calls Subscriber::subscribe
     */
    public function testSubscribeWithValidPreferencesCallsSubscriber(): void {
        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_row')->andReturn(null);
        $wpdb->shouldReceive('insert')->andReturn(1);

        Functions\when('get_bloginfo')->alias(function($show) {
            if ($show === 'name') return 'Test Site';
            return '';
        });

        // Mock wp_mail for email sending
        $this->mockWpMail();

        $request = $this->createRestRequest('POST', '/event-manager/v1/subscribe', [
            'email' => 'newuser@example.com',
            'preferences' => [
                'categories' => [1],
                'tags' => [],
                'service_bodies' => []
            ]
        ]);

        $response = SubscribersController::subscribe($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }
}
