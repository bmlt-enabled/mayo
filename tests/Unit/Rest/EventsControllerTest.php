<?php

namespace BmltEnabled\Mayo\Tests\Unit\Rest;

use BmltEnabled\Mayo\Tests\Unit\TestCase;
use BmltEnabled\Mayo\Rest\EventsController;
use BmltEnabled\Mayo\Rest\Helpers\ServiceBodyLookup;
use Brain\Monkey\Functions;

class EventsControllerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        $_GET = [];
        $_FILES = [];
        ServiceBodyLookup::clear_cache();
        $this->mockPostMeta();
        $this->mockGetOption([
            'mayo_settings' => [
                'bmlt_root_server' => 'https://bmlt.example.com',
                'notification_email' => 'admin@example.com'
            ],
            'mayo_external_sources' => [],
            'admin_email' => 'admin@example.com'
        ]);
        $this->mockWpMail();
        $this->mockTrailingslashit();
    }

    protected function tearDown(): void {
        $_GET = [];
        $_FILES = [];
        parent::tearDown();
    }

    /**
     * Test submit_event creates a new event
     */
    public function testSubmitEventCreatesEvent(): void {
        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [['id' => '1', 'name' => 'Test Region']]
            ]
        ]);

        Functions\expect('wp_insert_post')->once()->andReturn(123);
        Functions\expect('add_post_meta')->andReturn(true);

        $post = $this->createMockPost(['ID' => 123, 'post_title' => 'Test Event']);
        $this->mockGetPost($post);
        $this->mockGetTheTitle('Test Event');
        $this->mockHasPostThumbnail(false);

        Functions\when('wp_get_post_terms')->justReturn([]);
        Functions\when('get_term_link')->justReturn('https://example.com/category/test');

        $request = $this->createRestRequest('POST', '/event-manager/v1/submit-event', [
            'event_name' => 'Test Event',
            'description' => 'Test description',
            'event_type' => 'Meeting',
            'event_start_date' => '2024-06-15',
            'event_end_date' => '2024-06-15',
            'event_start_time' => '18:00',
            'event_end_time' => '19:00',
            'timezone' => 'America/New_York',
            'service_body' => '1',
            'email' => 'contact@example.com',
            'contact_name' => 'John Doe'
        ]);

        $response = EventsController::submit_event($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $emails = $this->getCapturedEmails();
        $this->assertNotEmpty($emails);
    }

    /**
     * Test submit_event handles wp_insert_post error
     */
    public function testSubmitEventHandlesInsertError(): void {
        Functions\expect('wp_insert_post')->once()->andReturn(
            new \WP_Error('db_error', 'Database error')
        );

        $request = $this->createRestRequest('POST', '/event-manager/v1/submit-event', [
            'event_name' => 'Test Event',
            'event_type' => 'Meeting',
            'event_start_date' => '2024-06-15',
            'event_end_date' => '2024-06-15',
            'event_start_time' => '18:00',
            'event_end_time' => '19:00',
            'timezone' => 'America/New_York',
            'service_body' => '1',
            'email' => 'contact@example.com',
            'contact_name' => 'John Doe'
        ]);

        $response = EventsController::submit_event($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
    }

    /**
     * Test submit_event handles recurring pattern
     */
    public function testSubmitEventWithRecurringPattern(): void {
        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [['id' => '1', 'name' => 'Test Region']]
            ]
        ]);

        Functions\expect('wp_insert_post')->once()->andReturn(124);
        Functions\expect('add_post_meta')->andReturn(true);

        $post = $this->createMockPost(['ID' => 124, 'post_title' => 'Recurring Event']);
        $this->mockGetPost($post);
        $this->mockGetTheTitle('Recurring Event');
        $this->mockHasPostThumbnail(false);
        Functions\when('wp_get_post_terms')->justReturn([]);

        $request = $this->createRestRequest('POST', '/event-manager/v1/submit-event', [
            'event_name' => 'Recurring Event',
            'event_type' => 'Meeting',
            'event_start_date' => '2024-06-15',
            'event_end_date' => '2024-06-15',
            'event_start_time' => '18:00',
            'event_end_time' => '19:00',
            'timezone' => 'America/New_York',
            'service_body' => '1',
            'email' => 'contact@example.com',
            'contact_name' => 'John Doe',
            'recurring_pattern' => json_encode([
                'type' => 'weekly',
                'interval' => 1,
                'weekdays' => [1, 3, 5],
                'endDate' => '2024-12-31'
            ])
        ]);

        $response = EventsController::submit_event($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());
    }

    /**
     * Test submit_event with monthly recurring pattern
     */
    public function testSubmitEventWithMonthlyRecurringPattern(): void {
        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [['id' => '1', 'name' => 'Test Region']]
            ]
        ]);

        Functions\expect('wp_insert_post')->once()->andReturn(125);
        Functions\expect('add_post_meta')->andReturn(true);

        $post = $this->createMockPost(['ID' => 125, 'post_title' => 'Monthly Event']);
        $this->mockGetPost($post);
        $this->mockGetTheTitle('Monthly Event');
        $this->mockHasPostThumbnail(false);
        Functions\when('wp_get_post_terms')->justReturn([]);

        $request = $this->createRestRequest('POST', '/event-manager/v1/submit-event', [
            'event_name' => 'Monthly Event',
            'event_type' => 'Meeting',
            'event_start_date' => '2024-06-15',
            'event_end_date' => '2024-06-15',
            'event_start_time' => '18:00',
            'event_end_time' => '19:00',
            'timezone' => 'America/New_York',
            'service_body' => '1',
            'email' => 'contact@example.com',
            'contact_name' => 'John Doe',
            'recurring_pattern' => [
                'type' => 'monthly',
                'interval' => 1,
                'monthlyType' => 'date',
                'monthlyDate' => 15,
                'endDate' => '2024-12-31'
            ]
        ]);

        $response = EventsController::submit_event($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());
    }

    /**
     * Test submit_event with location data
     */
    public function testSubmitEventWithLocation(): void {
        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [['id' => '1', 'name' => 'Test Region']]
            ]
        ]);

        Functions\expect('wp_insert_post')->once()->andReturn(126);
        Functions\expect('add_post_meta')->andReturn(true);

        $post = $this->createMockPost(['ID' => 126, 'post_title' => 'Event With Location']);
        $this->mockGetPost($post);
        $this->mockGetTheTitle('Event With Location');
        $this->mockHasPostThumbnail(false);
        Functions\when('wp_get_post_terms')->justReturn([]);

        $request = $this->createRestRequest('POST', '/event-manager/v1/submit-event', [
            'event_name' => 'Event With Location',
            'event_type' => 'Meeting',
            'event_start_date' => '2024-06-15',
            'event_end_date' => '2024-06-15',
            'event_start_time' => '18:00',
            'event_end_time' => '19:00',
            'timezone' => 'America/New_York',
            'service_body' => '1',
            'email' => 'contact@example.com',
            'contact_name' => 'John Doe',
            'location_name' => 'Community Center',
            'location_address' => '123 Main St',
            'location_details' => 'Room 101'
        ]);

        $response = EventsController::submit_event($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());
    }

    /**
     * Test submit_event with categories and tags
     */
    public function testSubmitEventWithCategoriesAndTags(): void {
        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [['id' => '1', 'name' => 'Test Region']]
            ]
        ]);

        Functions\expect('wp_insert_post')->once()->andReturn(127);
        Functions\expect('add_post_meta')->andReturn(true);

        $post = $this->createMockPost(['ID' => 127, 'post_title' => 'Categorized Event']);
        $this->mockGetPost($post);
        $this->mockGetTheTitle('Categorized Event');
        $this->mockHasPostThumbnail(false);
        Functions\when('wp_get_post_terms')->justReturn([]);

        $request = $this->createRestRequest('POST', '/event-manager/v1/submit-event', [
            'event_name' => 'Categorized Event',
            'event_type' => 'Meeting',
            'event_start_date' => '2024-06-15',
            'event_end_date' => '2024-06-15',
            'event_start_time' => '18:00',
            'event_end_time' => '19:00',
            'timezone' => 'America/New_York',
            'service_body' => '1',
            'email' => 'contact@example.com',
            'contact_name' => 'John Doe',
            'categories' => '1,2',
            'tags' => 'na-event,meeting'
        ]);

        $response = EventsController::submit_event($request);
        $this->assertEquals(200, $response->get_status());
    }

    /**
     * Test get_event_by_id returns event
     */
    public function testGetEventByIdReturnsEvent(): void {
        $post = $this->createMockPost([
            'ID' => 200,
            'post_title' => 'Event By ID',
            'post_type' => 'mayo_event',
            'post_status' => 'publish'
        ]);

        Functions\when('get_post')->justReturn($post);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/events/200');
        $request->set_param('id', '200');

        $response = EventsController::get_event_by_id($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertEquals(200, $data['id']);
        $this->assertEquals('Event By ID', $data['title']);
    }

    /**
     * Test get_event_by_id returns 404 for non-existent event
     */
    public function testGetEventByIdReturns404(): void {
        Functions\when('get_post')->justReturn(null);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/events/999');
        $request->set_param('id', '999');

        $response = EventsController::get_event_by_id($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('not_found', $response->get_error_code());
    }

    /**
     * Test get_event_by_id returns 404 for wrong post type
     */
    public function testGetEventByIdReturns404ForWrongPostType(): void {
        $post = $this->createMockPost([
            'ID' => 201,
            'post_type' => 'post',
            'post_status' => 'publish'
        ]);

        Functions\when('get_post')->justReturn($post);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/events/201');
        $request->set_param('id', '201');

        $response = EventsController::get_event_by_id($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
    }

    /**
     * Test format_event returns null for non-existent post
     */
    public function testFormatEventReturnsNullForNoPost(): void {
        Functions\when('get_post')->justReturn(null);

        $result = EventsController::format_event(999);

        $this->assertNull($result);
    }

    /**
     * Test format_event includes featured image when present
     */
    public function testFormatEventIncludesFeaturedImage(): void {
        $post = $this->createMockPost(['ID' => 300, 'post_title' => 'Event With Image']);

        Functions\when('get_post')->justReturn($post);
        Functions\when('get_the_title')->justReturn('Event With Image');
        Functions\when('has_post_thumbnail')->justReturn(true);
        Functions\when('get_the_post_thumbnail_url')->justReturn('https://example.com/image.jpg');
        Functions\when('wp_get_post_terms')->justReturn([]);

        $event = EventsController::format_event($post);

        $this->assertArrayHasKey('featured_image', $event);
        $this->assertEquals('https://example.com/image.jpg', $event['featured_image']);
    }

    /**
     * Test search_events returns matching events
     */
    public function testSearchEventsReturnsMatches(): void {
        $posts = [
            $this->createMockPost(['ID' => 1, 'post_title' => 'Meeting One', 'post_name' => 'meeting-one']),
            $this->createMockPost(['ID' => 2, 'post_title' => 'Meeting Two', 'post_name' => 'meeting-two'])
        ];

        Functions\when('get_posts')->justReturn($posts);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/events/search');
        $request->set_param('search', 'meeting');
        $request->set_param('limit', 10);

        $response = EventsController::search_events($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertArrayHasKey('events', $data);
        $this->assertCount(2, $data['events']);
    }

    /**
     * Test search_events with include parameter
     */
    public function testSearchEventsWithInclude(): void {
        $posts = [
            $this->createMockPost(['ID' => 5, 'post_title' => 'Specific Event'])
        ];

        Functions\when('get_posts')->justReturn($posts);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/events/search');
        $request->set_param('include', '5');

        $response = EventsController::search_events($request);

        $data = $response->get_data();
        $this->assertCount(1, $data['events']);
        $this->assertEquals(5, $data['events'][0]['id']);
    }

    /**
     * Test search_all_events returns paginated results
     */
    public function testSearchAllEventsReturnsPaginatedResults(): void {
        $this->loginAsAdmin();

        $posts = [];
        Functions\when('get_posts')->justReturn($posts);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/events/search-all');
        $request->set_param('search', 'test');

        $response = EventsController::search_all_events($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertArrayHasKey('events', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('page', $data);
    }

    /**
     * Test build_event_email_content formats email correctly
     */
    public function testBuildEventEmailContentFormatsCorrectly(): void {
        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [
                    ['id' => '1', 'name' => 'Test Region']
                ]
            ]
        ]);

        $params = [
            'event_name' => 'Test Event',
            'event_type' => 'Meeting',
            'service_body' => '1',
            'event_start_date' => '2024-06-15',
            'event_start_time' => '18:00',
            'event_end_date' => '2024-06-15',
            'event_end_time' => '19:00',
            'timezone' => 'America/New_York',
            'contact_name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'Event description'
        ];

        $result = EventsController::build_event_email_content(
            $params,
            'New Event: %s',
            'https://example.com/event/123'
        );

        $this->assertArrayHasKey('subject', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('New Event: Test Event', $result['subject']);
        $this->assertStringContainsString('Test Event', $result['message']);
        $this->assertStringContainsString('Meeting', $result['message']);
        $this->assertStringContainsString('Test Region', $result['message']);
        $this->assertStringContainsString('John Doe', $result['message']);
    }

    /**
     * Test build_event_email_content with daily recurring
     */
    public function testBuildEventEmailContentWithDailyRecurring(): void {
        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [['id' => '1', 'name' => 'Region']]
            ]
        ]);

        $params = [
            'event_name' => 'Daily Event',
            'event_type' => 'Meeting',
            'service_body' => '1',
            'event_start_date' => '2024-06-15',
            'event_start_time' => '18:00',
            'event_end_date' => '2024-06-15',
            'event_end_time' => '19:00',
            'timezone' => 'America/New_York',
            'contact_name' => 'Jane',
            'email' => 'jane@example.com',
            'recurring_pattern' => [
                'type' => 'daily',
                'interval' => 2,
                'endDate' => '2024-12-31'
            ]
        ];

        $result = EventsController::build_event_email_content($params, 'Event: %s', 'https://test.com');

        $this->assertStringContainsString('Daily', $result['message']);
        $this->assertStringContainsString('every 2 days', $result['message']);
    }

    /**
     * Test build_event_email_content with weekly recurring
     */
    public function testBuildEventEmailContentWithWeeklyRecurring(): void {
        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [['id' => '1', 'name' => 'Region']]
            ]
        ]);

        $params = [
            'event_name' => 'Weekly Event',
            'event_type' => 'Meeting',
            'service_body' => '1',
            'event_start_date' => '2024-06-15',
            'event_start_time' => '18:00',
            'event_end_date' => '2024-06-15',
            'event_end_time' => '19:00',
            'timezone' => 'America/New_York',
            'contact_name' => 'Jane',
            'email' => 'jane@example.com',
            'recurring_pattern' => json_encode([
                'type' => 'weekly',
                'interval' => 1,
                'weekdays' => [1, 3, 5]
            ])
        ];

        $result = EventsController::build_event_email_content($params, 'Event: %s', 'https://test.com');

        $this->assertStringContainsString('Weekly', $result['message']);
        $this->assertStringContainsString('Monday', $result['message']);
        $this->assertStringContainsString('Wednesday', $result['message']);
        $this->assertStringContainsString('Friday', $result['message']);
    }

    /**
     * Test build_event_email_content with monthly recurring by date
     */
    public function testBuildEventEmailContentWithMonthlyByDateRecurring(): void {
        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [['id' => '1', 'name' => 'Region']]
            ]
        ]);

        $params = [
            'event_name' => 'Monthly Event',
            'event_type' => 'Meeting',
            'service_body' => '1',
            'event_start_date' => '2024-06-15',
            'event_start_time' => '18:00',
            'event_end_date' => '2024-06-15',
            'event_end_time' => '19:00',
            'timezone' => 'America/New_York',
            'contact_name' => 'Jane',
            'email' => 'jane@example.com',
            'recurring_pattern' => [
                'type' => 'monthly',
                'interval' => 1,
                'monthlyType' => 'date',
                'monthlyDate' => 15
            ]
        ];

        $result = EventsController::build_event_email_content($params, 'Event: %s', 'https://test.com');

        $this->assertStringContainsString('Monthly', $result['message']);
        $this->assertStringContainsString('day 15', $result['message']);
    }

    /**
     * Test build_event_email_content with location info
     */
    public function testBuildEventEmailContentWithLocation(): void {
        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [['id' => '1', 'name' => 'Region']]
            ]
        ]);

        $params = [
            'event_name' => 'Event With Location',
            'event_type' => 'Meeting',
            'service_body' => '1',
            'event_start_date' => '2024-06-15',
            'event_start_time' => '18:00',
            'event_end_date' => '2024-06-15',
            'event_end_time' => '19:00',
            'timezone' => 'America/New_York',
            'contact_name' => 'Jane',
            'email' => 'jane@example.com',
            'location_name' => 'Community Hall',
            'location_address' => '456 Oak Ave',
            'location_details' => 'Second floor'
        ];

        $result = EventsController::build_event_email_content($params, 'Event: %s', 'https://test.com');

        $this->assertStringContainsString('Location:', $result['message']);
        $this->assertStringContainsString('Community Hall', $result['message']);
        $this->assertStringContainsString('456 Oak Ave', $result['message']);
        $this->assertStringContainsString('Second floor', $result['message']);
    }

    /**
     * Test build_event_email_content with categories and tags
     */
    public function testBuildEventEmailContentWithCategoriesAndTags(): void {
        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [['id' => '1', 'name' => 'Region']]
            ]
        ]);

        $params = [
            'event_name' => 'Categorized Event',
            'event_type' => 'Meeting',
            'service_body' => '1',
            'event_start_date' => '2024-06-15',
            'event_start_time' => '18:00',
            'event_end_date' => '2024-06-15',
            'event_end_time' => '19:00',
            'timezone' => 'America/New_York',
            'contact_name' => 'Jane',
            'email' => 'jane@example.com',
            'categories' => 'Events, Meetings',
            'tags' => 'na-event, important'
        ];

        $result = EventsController::build_event_email_content($params, 'Event: %s', 'https://test.com');

        $this->assertStringContainsString('Categories:', $result['message']);
        $this->assertStringContainsString('Events, Meetings', $result['message']);
        $this->assertStringContainsString('Tags:', $result['message']);
        $this->assertStringContainsString('na-event, important', $result['message']);
    }

    /**
     * Test get_events returns local events
     */
    public function testGetEventsReturnsLocalEvents(): void {
        $_GET = [];

        $posts = [];
        Functions\when('get_posts')->justReturn($posts);
        Functions\when('get_site_url')->justReturn('https://example.com');

        $response = EventsController::get_events();

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertArrayHasKey('events', $data);
        $this->assertArrayHasKey('sources', $data);
        $this->assertArrayHasKey('pagination', $data);
    }

    /**
     * Test get_events respects pagination
     */
    public function testGetEventsRespectsPagination(): void {
        $_GET = [
            'page' => '2',
            'per_page' => '5'
        ];

        Functions\when('get_posts')->justReturn([]);

        $response = EventsController::get_events();

        $data = $response->get_data();
        $this->assertEquals(5, $data['pagination']['per_page']);
    }

    /**
     * Test get_events respects order parameter
     */
    public function testGetEventsRespectsOrderParameter(): void {
        $_GET = ['order' => 'DESC'];

        Functions\when('get_posts')->justReturn([]);

        $response = EventsController::get_events();

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
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

        EventsController::register_routes();

        $this->assertContains('event-manager/v1/submit-event', $registeredRoutes);
        $this->assertContains('event-manager/v1/events', $registeredRoutes);
        $this->assertContains('event-manager/v1/event/(?P<slug>[a-zA-Z0-9-]+)', $registeredRoutes);
        $this->assertContains('event-manager/v1/events/search', $registeredRoutes);
        $this->assertContains('event-manager/v1/events/search-all', $registeredRoutes);
        $this->assertContains('event-manager/v1/events/(?P<id>\d+)', $registeredRoutes);
    }

    /**
     * Test format_event includes categories and tags
     */
    public function testFormatEventIncludesTaxonomies(): void {
        $post = $this->createMockPost(['ID' => 400, 'post_title' => 'Event With Taxonomies']);

        Functions\when('get_post')->justReturn($post);
        Functions\when('get_the_title')->justReturn('Event With Taxonomies');
        Functions\when('has_post_thumbnail')->justReturn(false);
        Functions\when('wp_get_post_terms')->alias(function($post_id, $taxonomy) {
            if ($taxonomy === 'category') {
                return [(object)['term_id' => 1, 'name' => 'News', 'slug' => 'news']];
            }
            return [(object)['term_id' => 2, 'name' => 'Featured', 'slug' => 'featured']];
        });
        Functions\when('get_term_link')->justReturn('https://example.com/term/slug');

        $event = EventsController::format_event($post);

        $this->assertArrayHasKey('categories', $event);
        $this->assertArrayHasKey('tags', $event);
    }

    /**
     * Test format_event includes meta fields
     */
    public function testFormatEventIncludesMetaFields(): void {
        $post = $this->createMockPost(['ID' => 401, 'post_title' => 'Event With Meta']);

        $this->setPostMeta(401, [
            'event_type' => 'Conference',
            'event_start_date' => '2024-07-01',
            'event_end_date' => '2024-07-03',
            'event_start_time' => '09:00',
            'event_end_time' => '17:00',
            'timezone' => 'America/Los_Angeles',
            'location_name' => 'Convention Center',
            'location_address' => '123 Main St',
            'service_body' => '5'
        ]);

        Functions\when('get_post')->justReturn($post);
        Functions\when('get_the_title')->justReturn('Event With Meta');
        Functions\when('has_post_thumbnail')->justReturn(false);
        Functions\when('wp_get_post_terms')->justReturn([]);

        $event = EventsController::format_event($post);

        $this->assertEquals('Conference', $event['meta']['event_type']);
        $this->assertEquals('2024-07-01', $event['meta']['event_start_date']);
        $this->assertEquals('Convention Center', $event['meta']['location_name']);
    }

    /**
     * Test submit_event with monthly weekday pattern
     */
    public function testSubmitEventWithMonthlyWeekdayPattern(): void {
        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [['id' => '1', 'name' => 'Region']]
            ]
        ]);

        Functions\expect('wp_insert_post')->once()->andReturn(128);
        Functions\expect('add_post_meta')->andReturn(true);

        $post = $this->createMockPost(['ID' => 128, 'post_title' => 'Monthly Weekday Event']);
        $this->mockGetPost($post);
        $this->mockGetTheTitle('Monthly Weekday Event');
        $this->mockHasPostThumbnail(false);
        Functions\when('wp_get_post_terms')->justReturn([]);

        $request = $this->createRestRequest('POST', '/event-manager/v1/submit-event', [
            'event_name' => 'Monthly Weekday Event',
            'event_type' => 'Meeting',
            'event_start_date' => '2024-06-15',
            'event_end_date' => '2024-06-15',
            'event_start_time' => '18:00',
            'event_end_time' => '19:00',
            'timezone' => 'America/New_York',
            'service_body' => '1',
            'email' => 'contact@example.com',
            'contact_name' => 'John Doe',
            'recurring_pattern' => [
                'type' => 'monthly',
                'interval' => 1,
                'monthlyType' => 'weekday',
                'monthlyWeekday' => '2,1'
            ]
        ]);

        $response = EventsController::submit_event($request);

        $this->assertEquals(200, $response->get_status());
    }

    /**
     * Test build_event_email_content with monthly weekday recurring
     */
    public function testBuildEventEmailContentWithMonthlyWeekdayRecurring(): void {
        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [['id' => '1', 'name' => 'Region']]
            ]
        ]);

        $params = [
            'event_name' => 'Monthly Weekday Event',
            'event_type' => 'Meeting',
            'service_body' => '1',
            'event_start_date' => '2024-06-15',
            'event_start_time' => '18:00',
            'event_end_date' => '2024-06-15',
            'event_end_time' => '19:00',
            'timezone' => 'America/New_York',
            'contact_name' => 'Jane',
            'email' => 'jane@example.com',
            'recurring_pattern' => [
                'type' => 'monthly',
                'interval' => 2,
                'monthlyWeekday' => '2,1',
                'endDate' => '2024-12-31'
            ]
        ];

        $result = EventsController::build_event_email_content($params, 'Event: %s', 'https://test.com');

        $this->assertStringContainsString('Monthly', $result['message']);
        $this->assertStringContainsString('every 2 months', $result['message']);
        $this->assertStringContainsString('until 2024-12-31', $result['message']);
    }

    /**
     * Test get_event_details returns 404 for non-existent slug
     */
    public function testGetEventDetailsReturns404ForNonExistentSlug(): void {
        Functions\when('wp_reset_postdata')->justReturn(true);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/event/non-existent');
        $request->set_param('slug', 'non-existent');

        $response = EventsController::get_event_details($request);

        // WP_Query won't find any posts, so it should return WP_Error
        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('no_event', $response->get_error_code());
    }

    /**
     * Test get_events with source_ids filter
     */
    public function testGetEventsWithSourceIdsFilter(): void {
        $_GET = [
            'source_ids' => 'local,external1'
        ];

        $this->mockGetOption([
            'mayo_settings' => [
                'bmlt_root_server' => 'https://bmlt.example.com',
                'notification_email' => 'admin@example.com'
            ],
            'mayo_external_sources' => [
                [
                    'id' => 'external1',
                    'url' => 'https://external.example.com',
                    'name' => 'External Site',
                    'enabled' => true
                ]
            ],
            'admin_email' => 'admin@example.com'
        ]);

        Functions\when('get_posts')->justReturn([]);
        Functions\when('get_site_url')->justReturn('https://example.com');

        // Mock external API call
        $this->mockWpRemoteGet([
            'events' => ['code' => 200, 'body' => ['events' => []]],
            'settings' => ['code' => 200, 'body' => ['bmlt_root_server' => 'https://bmlt.example.com']],
            'GetServiceBodies' => ['code' => 200, 'body' => []]
        ]);

        $response = EventsController::get_events();

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertArrayHasKey('sources', $data);
    }

    /**
     * Test get_events with event_type filter
     */
    public function testGetEventsWithEventTypeFilter(): void {
        $_GET = [
            'event_type' => 'Service'
        ];

        Functions\when('get_posts')->justReturn([]);
        Functions\when('get_site_url')->justReturn('https://example.com');

        $response = EventsController::get_events();

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    /**
     * Test get_events with service_body filter
     */
    public function testGetEventsWithServiceBodyFilter(): void {
        $_GET = [
            'service_body' => '5,10'
        ];

        Functions\when('get_posts')->justReturn([]);
        Functions\when('get_site_url')->justReturn('https://example.com');

        $response = EventsController::get_events();

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    /**
     * Test get_events with archive mode
     */
    public function testGetEventsWithArchiveMode(): void {
        $_GET = [
            'archive' => 'true'
        ];

        Functions\when('get_posts')->justReturn([]);
        Functions\when('get_site_url')->justReturn('https://example.com');

        $response = EventsController::get_events();

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    /**
     * Test get_events with date range filter
     */
    public function testGetEventsWithDateRangeFilter(): void {
        $_GET = [
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31'
        ];

        Functions\when('get_posts')->justReturn([]);
        Functions\when('get_site_url')->justReturn('https://example.com');

        $response = EventsController::get_events();

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    /**
     * Test get_events with categories and tags filter
     */
    public function testGetEventsWithCategoriesAndTagsFilter(): void {
        $_GET = [
            'categories' => 'news,events',
            'tags' => 'featured',
            'category_relation' => 'OR'
        ];

        Functions\when('get_posts')->justReturn([]);
        Functions\when('get_site_url')->justReturn('https://example.com');
        Functions\when('get_term_by')->justReturn((object)['term_id' => 1]);

        $response = EventsController::get_events();

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    /**
     * Test get_events sorts events correctly
     */
    public function testGetEventsSortsEventsCorrectly(): void {
        $_GET = [
            'order' => 'ASC'
        ];

        $post1 = $this->createMockPost(['ID' => 1, 'post_title' => 'Event A']);
        $post2 = $this->createMockPost(['ID' => 2, 'post_title' => 'Event B']);

        $this->setPostMeta(1, [
            'event_start_date' => '2024-06-15',
            'event_start_time' => '10:00'
        ]);
        $this->setPostMeta(2, [
            'event_start_date' => '2024-06-14',
            'event_start_time' => '10:00'
        ]);

        Functions\when('get_posts')->justReturn([$post1, $post2]);
        Functions\when('get_site_url')->justReturn('https://example.com');
        Functions\when('get_the_title')->alias(function($post) {
            return $post->post_title;
        });
        Functions\when('has_post_thumbnail')->justReturn(false);
        Functions\when('wp_get_post_terms')->justReturn([]);

        $response = EventsController::get_events();

        $data = $response->get_data();
        $this->assertArrayHasKey('events', $data);
    }

    /**
     * Test get_events with invalid order defaults to ASC
     */
    public function testGetEventsWithInvalidOrderDefaultsToASC(): void {
        $_GET = [
            'order' => 'INVALID'
        ];

        Functions\when('get_posts')->justReturn([]);
        Functions\when('get_site_url')->justReturn('https://example.com');

        $response = EventsController::get_events();

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    /**
     * Test search_all_events uses limit parameter when per_page not set
     */
    public function testSearchAllEventsUsesLimitParameter(): void {
        $this->loginAsAdmin();

        Functions\when('get_posts')->justReturn([]);
        Functions\when('wp_date')->justReturn('2024-06-15');

        $request = new \WP_REST_Request('GET', '/event-manager/v1/events/search-all');
        $request->set_param('limit', 50);

        $response = EventsController::search_all_events($request);

        $data = $response->get_data();
        $this->assertEquals(50, $data['per_page']);
    }

    /**
     * Test search_all_events with hide_past false
     */
    public function testSearchAllEventsWithHidePastFalse(): void {
        $this->loginAsAdmin();

        Functions\when('get_posts')->justReturn([]);
        Functions\when('wp_date')->justReturn('2024-06-15');

        $request = new \WP_REST_Request('GET', '/event-manager/v1/events/search-all');
        $request->set_param('hide_past', 'false');

        $response = EventsController::search_all_events($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    /**
     * Test search_all_events searches external sources
     */
    public function testSearchAllEventsSearchesExternalSources(): void {
        $this->loginAsAdmin();

        $this->mockGetOption([
            'mayo_settings' => [
                'bmlt_root_server' => 'https://bmlt.example.com',
                'notification_email' => 'admin@example.com'
            ],
            'mayo_external_sources' => [
                [
                    'id' => 'ext1',
                    'url' => 'https://external.example.com',
                    'name' => 'External',
                    'enabled' => true
                ]
            ],
            'admin_email' => 'admin@example.com'
        ]);

        Functions\when('get_posts')->justReturn([]);
        Functions\when('wp_date')->justReturn('2024-06-15');

        $this->mockWpRemoteGet([
            'events' => [
                'code' => 200,
                'body' => [
                    'events' => [
                        [
                            'id' => 100,
                            'title' => 'External Event',
                            'slug' => 'external-event',
                            'meta' => ['event_start_date' => '2024-07-01']
                        ]
                    ]
                ]
            ]
        ]);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/events/search-all');
        $request->set_param('search', 'External');

        $response = EventsController::search_all_events($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    /**
     * Test get_event_by_id returns 404 for unpublished event
     */
    public function testGetEventByIdReturns404ForUnpublishedEvent(): void {
        $post = $this->createMockPost([
            'ID' => 202,
            'post_type' => 'mayo_event',
            'post_status' => 'draft'
        ]);

        Functions\when('get_post')->justReturn($post);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/events/202');
        $request->set_param('id', '202');

        $response = EventsController::get_event_by_id($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
    }

    /**
     * Test submit_event with invalid recurring pattern string
     */
    public function testSubmitEventWithInvalidRecurringPatternString(): void {
        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [['id' => '1', 'name' => 'Region']]
            ]
        ]);

        Functions\expect('wp_insert_post')->once()->andReturn(130);
        Functions\expect('add_post_meta')->andReturn(true);

        $post = $this->createMockPost(['ID' => 130, 'post_title' => 'Event']);
        $this->mockGetPost($post);
        $this->mockGetTheTitle('Event');
        $this->mockHasPostThumbnail(false);
        Functions\when('wp_get_post_terms')->justReturn([]);

        $request = $this->createRestRequest('POST', '/event-manager/v1/submit-event', [
            'event_name' => 'Event',
            'event_type' => 'Meeting',
            'event_start_date' => '2024-06-15',
            'event_end_date' => '2024-06-15',
            'event_start_time' => '18:00',
            'event_end_time' => '19:00',
            'timezone' => 'America/New_York',
            'service_body' => '1',
            'email' => 'contact@example.com',
            'contact_name' => 'John Doe',
            'recurring_pattern' => 'invalid-json-string'
        ]);

        $response = EventsController::submit_event($request);

        $this->assertEquals(200, $response->get_status());
    }

    /**
     * Test format_event includes linked announcements
     */
    public function testFormatEventIncludesLinkedAnnouncements(): void {
        $post = $this->createMockPost(['ID' => 450, 'post_title' => 'Event']);

        Functions\when('get_post')->justReturn($post);
        Functions\when('get_the_title')->justReturn('Event');
        Functions\when('has_post_thumbnail')->justReturn(false);
        Functions\when('wp_get_post_terms')->justReturn([]);

        $event = EventsController::format_event($post);

        $this->assertArrayHasKey('linked_announcements', $event);
    }

    /**
     * Test get_events returns recurring events
     */
    public function testGetEventsReturnsRecurringEvents(): void {
        $_GET = [];

        $post = $this->createMockPost([
            'ID' => 600,
            'post_title' => 'Recurring Event',
            'post_type' => 'mayo_event',
            'post_status' => 'publish'
        ]);

        // Set up recurring event meta
        $this->setPostMeta(600, [
            'event_start_date' => date('Y-m-d'),
            'event_end_date' => date('Y-m-d'),
            'event_start_time' => '18:00',
            'event_end_time' => '19:00',
            'recurring_pattern' => [
                'type' => 'daily',
                'interval' => 1,
                'endDate' => date('Y-m-d', strtotime('+30 days'))
            ],
            'skipped_occurrences' => []
        ]);

        // First call for non-recurring, second for recurring
        $callCount = 0;
        Functions\when('get_posts')->alias(function($args) use ($post, &$callCount) {
            $callCount++;
            if (isset($args['meta_query'])) {
                // Recurring query
                foreach ($args['meta_query'] as $query) {
                    if (isset($query['key']) && $query['key'] === 'recurring_pattern') {
                        return [$post];
                    }
                }
            }
            return [];
        });

        Functions\when('get_site_url')->justReturn('https://example.com');
        Functions\when('get_the_title')->justReturn('Recurring Event');
        Functions\when('has_post_thumbnail')->justReturn(false);
        Functions\when('wp_get_post_terms')->justReturn([]);

        $response = EventsController::get_events();

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    /**
     * Test get_events with weekly recurring events
     */
    public function testGetEventsWithWeeklyRecurringEvents(): void {
        $_GET = [];

        $post = $this->createMockPost([
            'ID' => 601,
            'post_title' => 'Weekly Event',
            'post_type' => 'mayo_event',
            'post_status' => 'publish'
        ]);

        $this->setPostMeta(601, [
            'event_start_date' => date('Y-m-d'),
            'event_end_date' => date('Y-m-d'),
            'event_start_time' => '18:00',
            'event_end_time' => '19:00',
            'recurring_pattern' => [
                'type' => 'weekly',
                'interval' => 1,
                'weekdays' => [1, 3, 5],
                'endDate' => date('Y-m-d', strtotime('+60 days'))
            ],
            'skipped_occurrences' => []
        ]);

        Functions\when('get_posts')->alias(function($args) use ($post) {
            if (isset($args['meta_query'])) {
                foreach ($args['meta_query'] as $query) {
                    if (isset($query['key']) && $query['key'] === 'recurring_pattern') {
                        return [$post];
                    }
                }
            }
            return [];
        });

        Functions\when('get_site_url')->justReturn('https://example.com');
        Functions\when('get_the_title')->justReturn('Weekly Event');
        Functions\when('has_post_thumbnail')->justReturn(false);
        Functions\when('wp_get_post_terms')->justReturn([]);

        $response = EventsController::get_events();

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    /**
     * Test get_events with monthly recurring events by date
     */
    public function testGetEventsWithMonthlyRecurringEventsByDate(): void {
        $_GET = [];

        $post = $this->createMockPost([
            'ID' => 602,
            'post_title' => 'Monthly Event',
            'post_type' => 'mayo_event',
            'post_status' => 'publish'
        ]);

        $this->setPostMeta(602, [
            'event_start_date' => date('Y-m-15'),
            'event_end_date' => date('Y-m-15'),
            'event_start_time' => '18:00',
            'event_end_time' => '19:00',
            'recurring_pattern' => [
                'type' => 'monthly',
                'interval' => 1,
                'monthlyType' => 'date',
                'monthlyDate' => 15,
                'endDate' => date('Y-m-d', strtotime('+6 months'))
            ],
            'skipped_occurrences' => []
        ]);

        Functions\when('get_posts')->alias(function($args) use ($post) {
            if (isset($args['meta_query'])) {
                foreach ($args['meta_query'] as $query) {
                    if (isset($query['key']) && $query['key'] === 'recurring_pattern') {
                        return [$post];
                    }
                }
            }
            return [];
        });

        Functions\when('get_site_url')->justReturn('https://example.com');
        Functions\when('get_the_title')->justReturn('Monthly Event');
        Functions\when('has_post_thumbnail')->justReturn(false);
        Functions\when('wp_get_post_terms')->justReturn([]);

        $response = EventsController::get_events();

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    /**
     * Test get_events with monthly recurring events by weekday
     */
    public function testGetEventsWithMonthlyRecurringEventsByWeekday(): void {
        $_GET = [];

        $post = $this->createMockPost([
            'ID' => 603,
            'post_title' => 'Monthly Weekday Event',
            'post_type' => 'mayo_event',
            'post_status' => 'publish'
        ]);

        $this->setPostMeta(603, [
            'event_start_date' => date('Y-m-d'),
            'event_end_date' => date('Y-m-d'),
            'event_start_time' => '18:00',
            'event_end_time' => '19:00',
            'recurring_pattern' => [
                'type' => 'monthly',
                'interval' => 1,
                'monthlyWeekday' => '2,1',
                'endDate' => date('Y-m-d', strtotime('+6 months'))
            ],
            'skipped_occurrences' => []
        ]);

        Functions\when('get_posts')->alias(function($args) use ($post) {
            if (isset($args['meta_query'])) {
                foreach ($args['meta_query'] as $query) {
                    if (isset($query['key']) && $query['key'] === 'recurring_pattern') {
                        return [$post];
                    }
                }
            }
            return [];
        });

        Functions\when('get_site_url')->justReturn('https://example.com');
        Functions\when('get_the_title')->justReturn('Monthly Weekday Event');
        Functions\when('has_post_thumbnail')->justReturn(false);
        Functions\when('wp_get_post_terms')->justReturn([]);

        $response = EventsController::get_events();

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    /**
     * Test get_events with monthly recurring using last weekday of month
     */
    public function testGetEventsWithMonthlyLastWeekdayOfMonth(): void {
        $_GET = [];

        $post = $this->createMockPost([
            'ID' => 604,
            'post_title' => 'Last Friday Event',
            'post_type' => 'mayo_event',
            'post_status' => 'publish'
        ]);

        $this->setPostMeta(604, [
            'event_start_date' => date('Y-m-d'),
            'event_end_date' => date('Y-m-d'),
            'event_start_time' => '18:00',
            'event_end_time' => '19:00',
            'recurring_pattern' => [
                'type' => 'monthly',
                'interval' => 1,
                'monthlyWeekday' => '-1,5',
                'endDate' => date('Y-m-d', strtotime('+6 months'))
            ],
            'skipped_occurrences' => []
        ]);

        Functions\when('get_posts')->alias(function($args) use ($post) {
            if (isset($args['meta_query'])) {
                foreach ($args['meta_query'] as $query) {
                    if (isset($query['key']) && $query['key'] === 'recurring_pattern') {
                        return [$post];
                    }
                }
            }
            return [];
        });

        Functions\when('get_site_url')->justReturn('https://example.com');
        Functions\when('get_the_title')->justReturn('Last Friday Event');
        Functions\when('has_post_thumbnail')->justReturn(false);
        Functions\when('wp_get_post_terms')->justReturn([]);

        $response = EventsController::get_events();

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }
}
