<?php

namespace BmltEnabled\Mayo\Tests\Integration;

use WP_UnitTestCase;
use BmltEnabled\Mayo\Rest;
use WP_REST_Request;
use WP_REST_Server;

class RestEventsIntegrationTest extends WP_UnitTestCase {

    protected $server;

    public function setUp(): void {
        parent::setUp();

        // Initialize REST server
        global $wp_rest_server;
        $this->server = $wp_rest_server = new WP_REST_Server();
        do_action('rest_api_init');

        // Mock service body data for BMLT API calls
        $this->mockServiceBodyData();

        // Initialize captured emails array
        global $captured_emails;
        $captured_emails = [];
    }

    public function tearDown(): void {
        parent::tearDown();

        global $wp_rest_server;
        $wp_rest_server = null;

        global $captured_emails;
        $captured_emails = [];

        remove_all_filters('pre_http_request');
    }

    /**
     * Test that submitting an event creates a post with all metadata
     */
    public function testSubmitEventCreatesPostWithMetadata() {
        $request = new WP_REST_Request('POST', '/event-manager/v1/submit-event');
        $request->set_body_params([
            'event_name' => 'Test Event Name',
            'event_type' => 'Meeting',
            'event_start_date' => '2024-06-15',
            'event_end_date' => '2024-06-15',
            'event_start_time' => '19:00',
            'event_end_time' => '21:00',
            'timezone' => 'America/New_York',
            'service_body' => '1',
            'email' => 'test@example.com',
            'contact_name' => 'Test Contact',
            'description' => 'Test description content',
            'location_name' => 'Test Venue',
            'location_address' => '123 Main St',
            'location_details' => 'Room 101'
        ]);

        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('id', $data);

        $post_id = $data['id'];
        $post = get_post($post_id);

        // Verify post was created
        $this->assertNotNull($post);
        $this->assertEquals('Test Event Name', $post->post_title);
        $this->assertEquals('pending', $post->post_status);
        $this->assertEquals('mayo_event', $post->post_type);

        // Verify metadata
        $this->assertEquals('Meeting', get_post_meta($post_id, 'event_type', true));
        $this->assertEquals('2024-06-15', get_post_meta($post_id, 'event_start_date', true));
        $this->assertEquals('2024-06-15', get_post_meta($post_id, 'event_end_date', true));
        $this->assertEquals('19:00', get_post_meta($post_id, 'event_start_time', true));
        $this->assertEquals('21:00', get_post_meta($post_id, 'event_end_time', true));
        $this->assertEquals('America/New_York', get_post_meta($post_id, 'timezone', true));
        $this->assertEquals('1', get_post_meta($post_id, 'service_body', true));
        $this->assertEquals('test@example.com', get_post_meta($post_id, 'email', true));
        $this->assertEquals('Test Contact', get_post_meta($post_id, 'contact_name', true));
        $this->assertEquals('Test Venue', get_post_meta($post_id, 'location_name', true));
        $this->assertEquals('123 Main St', get_post_meta($post_id, 'location_address', true));
        $this->assertEquals('Room 101', get_post_meta($post_id, 'location_details', true));
    }

    /**
     * Test that submitting an event with recurring pattern stores the pattern
     */
    public function testSubmitEventWithRecurringPattern() {
        $recurring_pattern = [
            'type' => 'weekly',
            'interval' => 1,
            'weekdays' => [1, 3, 5],
            'endDate' => '2024-12-31'
        ];

        $request = new WP_REST_Request('POST', '/event-manager/v1/submit-event');
        $request->set_body_params([
            'event_name' => 'Recurring Event',
            'event_type' => 'Meeting',
            'event_start_date' => '2024-06-01',
            'event_end_date' => '2024-06-01',
            'event_start_time' => '18:00',
            'event_end_time' => '19:00',
            'timezone' => 'America/Chicago',
            'service_body' => '2',
            'email' => 'recurring@example.com',
            'contact_name' => 'Recurring Contact',
            'recurring_pattern' => json_encode($recurring_pattern)
        ]);

        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $post_id = $data['id'];

        $stored_pattern = get_post_meta($post_id, 'recurring_pattern', true);

        $this->assertIsArray($stored_pattern);
        $this->assertEquals('weekly', $stored_pattern['type']);
        $this->assertEquals(1, $stored_pattern['interval']);
        $this->assertEquals([1, 3, 5], $stored_pattern['weekdays']);
        $this->assertEquals('2024-12-31', $stored_pattern['endDate']);
    }

    /**
     * Test that GET /events returns published events
     */
    public function testGetEventsReturnsPublishedEvents() {
        // Create test events
        $event1_id = $this->createTestEvent('Published Event 1', 'publish', '2025-01-15');
        $event2_id = $this->createTestEvent('Published Event 2', 'publish', '2025-01-20');
        $draft_id = $this->createTestEvent('Draft Event', 'draft', '2025-01-25');

        $request = new WP_REST_Request('GET', '/event-manager/v1/events');
        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('events', $data);
        $this->assertArrayHasKey('pagination', $data);

        $event_ids = array_column($data['events'], 'id');

        // Published events should be included
        $this->assertContains($event1_id, $event_ids);
        $this->assertContains($event2_id, $event_ids);

        // Draft events should not be included
        $this->assertNotContains($draft_id, $event_ids);
    }

    /**
     * Test that GET /events filters by service body
     */
    public function testGetEventsWithServiceBodyFilter() {
        $event1_id = $this->createTestEvent('Event SB1', 'publish', '2025-02-01', ['service_body' => '1']);
        $event2_id = $this->createTestEvent('Event SB2', 'publish', '2025-02-05', ['service_body' => '2']);

        $request = new WP_REST_Request('GET', '/event-manager/v1/events');
        $request->set_query_params(['service_body' => '1']);

        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $event_ids = array_column($data['events'], 'id');

        $this->assertContains($event1_id, $event_ids);
        $this->assertNotContains($event2_id, $event_ids);
    }

    /**
     * Test that GET /events filters by category
     */
    public function testGetEventsWithCategoryFilter() {
        // Create a test category
        $category = wp_create_category('Test Category');

        $event1_id = $this->createTestEvent('Categorized Event', 'publish', '2025-03-01');
        wp_set_post_categories($event1_id, [$category]);

        $event2_id = $this->createTestEvent('Uncategorized Event', 'publish', '2025-03-05');

        $cat_obj = get_category($category);

        $request = new WP_REST_Request('GET', '/event-manager/v1/events');
        $request->set_query_params(['categories' => $cat_obj->slug]);

        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $event_ids = array_column($data['events'], 'id');

        $this->assertContains($event1_id, $event_ids);
        $this->assertNotContains($event2_id, $event_ids);
    }

    /**
     * Test that GET /events with archive=true returns past events only
     */
    public function testGetEventsWithArchiveMode() {
        // Create past and future events
        $past_event_id = $this->createTestEvent('Past Event', 'publish', '2020-01-01');
        $future_event_id = $this->createTestEvent('Future Event', 'publish', '2030-01-01');

        $request = new WP_REST_Request('GET', '/event-manager/v1/events');
        $request->set_query_params(['archive' => 'true']);

        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $event_ids = array_column($data['events'], 'id');

        // Past events should be included in archive
        $this->assertContains($past_event_id, $event_ids);
        // Future events should not be in archive
        $this->assertNotContains($future_event_id, $event_ids);
    }

    /**
     * Test GET /event/{slug} returns event details
     */
    public function testGetEventBySlug() {
        $event_id = $this->createTestEvent('Slug Test Event', 'publish', '2025-04-01');
        $post = get_post($event_id);

        $request = new WP_REST_Request('GET', '/event-manager/v1/event/' . $post->post_name);
        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertEquals($event_id, $data['id']);
        $this->assertEquals('Slug Test Event', $data['title']['rendered']);
    }

    /**
     * Test GET /events/search returns matching events
     */
    public function testSearchEvents() {
        $event1_id = $this->createTestEvent('Searchable Meeting', 'publish', '2025-05-01');
        $event2_id = $this->createTestEvent('Different Event', 'publish', '2025-05-05');

        $request = new WP_REST_Request('GET', '/event-manager/v1/events/search');
        $request->set_query_params(['search' => 'Searchable']);

        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('events', $data);

        $event_ids = array_column($data['events'], 'id');
        $this->assertContains($event1_id, $event_ids);
    }

    /**
     * Test GET /events/{id} returns event by ID
     */
    public function testGetEventById() {
        $event_id = $this->createTestEvent('ID Test Event', 'publish', '2025-06-01');

        $request = new WP_REST_Request('GET', '/event-manager/v1/events/' . $event_id);
        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertEquals($event_id, $data['id']);
        $this->assertEquals('ID Test Event', $data['title']);
    }

    /**
     * Test format_event returns proper structure
     */
    public function testFormatEventReturnsProperStructure() {
        $event_id = $this->createTestEvent('Format Test Event', 'publish', '2025-07-01', [
            'service_body' => '1',
            'location_name' => 'Test Location',
            'location_address' => '456 Test Ave',
        ]);

        $formatted = Rest::format_event($event_id);

        $this->assertIsArray($formatted);
        $this->assertArrayHasKey('id', $formatted);
        $this->assertArrayHasKey('title', $formatted);
        $this->assertArrayHasKey('content', $formatted);
        $this->assertArrayHasKey('link', $formatted);
        $this->assertArrayHasKey('meta', $formatted);

        // Check meta structure
        $this->assertArrayHasKey('event_start_date', $formatted['meta']);
        $this->assertArrayHasKey('event_end_date', $formatted['meta']);
        $this->assertArrayHasKey('event_start_time', $formatted['meta']);
        $this->assertArrayHasKey('event_end_time', $formatted['meta']);
        $this->assertArrayHasKey('timezone', $formatted['meta']);
        $this->assertArrayHasKey('service_body', $formatted['meta']);
        $this->assertArrayHasKey('location_name', $formatted['meta']);
        $this->assertArrayHasKey('location_address', $formatted['meta']);
    }

    /**
     * Helper: Create a test event
     */
    private function createTestEvent($title, $status, $start_date, $meta = []) {
        $post_id = wp_insert_post([
            'post_title' => $title,
            'post_content' => 'Test content for ' . $title,
            'post_status' => $status,
            'post_type' => 'mayo_event'
        ]);

        $defaults = [
            'event_type' => 'Meeting',
            'event_start_date' => $start_date,
            'event_end_date' => $start_date,
            'event_start_time' => '19:00',
            'event_end_time' => '21:00',
            'timezone' => 'America/New_York',
            'service_body' => '1',
            'email' => 'test@example.com',
            'contact_name' => 'Test Contact'
        ];

        $meta = array_merge($defaults, $meta);

        foreach ($meta as $key => $value) {
            add_post_meta($post_id, $key, $value);
        }

        return $post_id;
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
