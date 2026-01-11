<?php

namespace BmltEnabled\Mayo\Tests\Integration;

use WP_UnitTestCase;
use BmltEnabled\Mayo\Rest;
use WP_REST_Request;
use WP_REST_Server;

class RestAnnouncementsIntegrationTest extends WP_UnitTestCase {

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
     * Test that GET /announcements returns only active announcements by default
     */
    public function testGetAnnouncementsReturnsActiveOnly() {
        $today = current_time('Y-m-d');
        $past_date = date('Y-m-d', strtotime('-30 days'));
        $future_date = date('Y-m-d', strtotime('+30 days'));
        $far_future = date('Y-m-d', strtotime('+60 days'));

        // Active announcement (currently within display window)
        $active_id = $this->createTestAnnouncement('Active Announcement', 'publish', [
            'display_start_date' => $past_date,
            'display_end_date' => $future_date
        ]);

        // Expired announcement (display window ended)
        $expired_id = $this->createTestAnnouncement('Expired Announcement', 'publish', [
            'display_start_date' => date('Y-m-d', strtotime('-60 days')),
            'display_end_date' => $past_date
        ]);

        // Future announcement (display window hasn't started)
        $future_id = $this->createTestAnnouncement('Future Announcement', 'publish', [
            'display_start_date' => $future_date,
            'display_end_date' => $far_future
        ]);

        $request = new WP_REST_Request('GET', '/event-manager/v1/announcements');
        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('announcements', $data);

        $announcement_ids = array_column($data['announcements'], 'id');

        // Active should be included
        $this->assertContains($active_id, $announcement_ids);
        // Expired should not be included
        $this->assertNotContains($expired_id, $announcement_ids);
        // Future should not be included
        $this->assertNotContains($future_id, $announcement_ids);
    }

    /**
     * Test that GET /announcement/{id} returns announcement by ID
     */
    public function testGetAnnouncementById() {
        $announcement_id = $this->createTestAnnouncement('Test Announcement by ID', 'publish');

        $request = new WP_REST_Request('GET', '/event-manager/v1/announcement/' . $announcement_id);
        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertEquals($announcement_id, $data['id']);
        $this->assertEquals('Test Announcement by ID', $data['title']);
    }

    /**
     * Test that GET /announcement-by-slug/{slug} returns announcement by slug
     */
    public function testGetAnnouncementBySlug() {
        $announcement_id = $this->createTestAnnouncement('Announcement By Slug Test', 'publish');
        $post = get_post($announcement_id);

        $request = new WP_REST_Request('GET', '/event-manager/v1/announcement-by-slug/' . $post->post_name);
        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertEquals($announcement_id, $data['id']);
        $this->assertEquals('Announcement By Slug Test', $data['title']);
    }

    /**
     * Test that POST /submit-announcement creates a pending announcement
     */
    public function testSubmitAnnouncementCreatesPost() {
        $request = new WP_REST_Request('POST', '/event-manager/v1/submit-announcement');
        $request->set_body_params([
            'title' => 'Submitted Announcement',
            'description' => 'This is a test announcement submission.',
            'service_body' => '1',
            'email' => 'submitter@example.com',
            'contact_name' => 'Announcement Submitter',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31'
        ]);

        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('id', $data);

        $post_id = $data['id'];
        $post = get_post($post_id);

        // Verify post was created
        $this->assertNotNull($post);
        $this->assertEquals('Submitted Announcement', $post->post_title);
        $this->assertEquals('pending', $post->post_status);
        $this->assertEquals('mayo_announcement', $post->post_type);

        // Verify metadata
        $this->assertEquals('1', get_post_meta($post_id, 'service_body', true));
        $this->assertEquals('submitter@example.com', get_post_meta($post_id, 'email', true));
        $this->assertEquals('Announcement Submitter', get_post_meta($post_id, 'contact_name', true));
        $this->assertEquals('2025-01-01', get_post_meta($post_id, 'display_start_date', true));
        $this->assertEquals('2025-12-31', get_post_meta($post_id, 'display_end_date', true));
    }

    /**
     * Test that announcements include linked events data
     */
    public function testAnnouncementIncludesLinkedEvents() {
        // Create a test event first
        $event_id = wp_insert_post([
            'post_title' => 'Linked Event',
            'post_content' => 'Event content',
            'post_status' => 'publish',
            'post_type' => 'mayo_event'
        ]);
        add_post_meta($event_id, 'event_start_date', '2025-06-15');
        add_post_meta($event_id, 'event_end_date', '2025-06-15');
        add_post_meta($event_id, 'event_start_time', '19:00');
        add_post_meta($event_id, 'event_end_time', '21:00');
        add_post_meta($event_id, 'timezone', 'America/New_York');

        // Create announcement linked to the event
        $announcement_id = $this->createTestAnnouncement('Announcement with Linked Event', 'publish');

        // Store linked event reference (format used by the Announcement class)
        $linked_refs = [
            ['type' => 'local', 'id' => $event_id]
        ];
        update_post_meta($announcement_id, 'linked_events', $linked_refs);

        $request = new WP_REST_Request('GET', '/event-manager/v1/announcement/' . $announcement_id);
        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('linked_events', $data);
    }

    /**
     * Test that format_announcement returns proper structure
     */
    public function testFormatAnnouncementReturnsProperStructure() {
        $announcement_id = $this->createTestAnnouncement('Format Test Announcement', 'publish', [
            'priority' => 'high',
            'service_body' => '1'
        ]);

        // Use reflection to call the private method
        $reflection = new \ReflectionClass('BmltEnabled\Mayo\Rest');
        $method = $reflection->getMethod('format_announcement');
        $method->setAccessible(true);

        $post = get_post($announcement_id);
        $formatted = $method->invoke(null, $post);

        $this->assertIsArray($formatted);
        $this->assertArrayHasKey('id', $formatted);
        $this->assertArrayHasKey('title', $formatted);
        $this->assertArrayHasKey('content', $formatted);
        $this->assertArrayHasKey('permalink', $formatted);
        $this->assertArrayHasKey('display_start_date', $formatted);
        $this->assertArrayHasKey('display_end_date', $formatted);
        $this->assertArrayHasKey('priority', $formatted);
        $this->assertArrayHasKey('is_active', $formatted);
        $this->assertArrayHasKey('linked_events', $formatted);
        $this->assertArrayHasKey('categories', $formatted);
        $this->assertArrayHasKey('tags', $formatted);
    }

    /**
     * Test GET /announcements with category filter
     */
    public function testGetAnnouncementsWithCategoryFilter() {
        // Create a test category
        $category = wp_create_category('Announcement Category');

        $categorized_id = $this->createTestAnnouncement('Categorized Announcement', 'publish');
        wp_set_post_categories($categorized_id, [$category]);

        $uncategorized_id = $this->createTestAnnouncement('Uncategorized Announcement', 'publish');

        $cat_obj = get_category($category);

        $request = new WP_REST_Request('GET', '/event-manager/v1/announcements');
        $request->set_query_params(['categories' => $cat_obj->slug]);

        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $announcement_ids = array_column($data['announcements'], 'id');

        $this->assertContains($categorized_id, $announcement_ids);
        $this->assertNotContains($uncategorized_id, $announcement_ids);
    }

    /**
     * Test GET /announcements with priority filter
     */
    public function testGetAnnouncementsWithPriorityFilter() {
        $high_priority_id = $this->createTestAnnouncement('High Priority', 'publish', [
            'priority' => 'high'
        ]);
        $normal_priority_id = $this->createTestAnnouncement('Normal Priority', 'publish', [
            'priority' => 'normal'
        ]);

        $request = new WP_REST_Request('GET', '/event-manager/v1/announcements');
        $request->set_query_params(['priority' => 'high']);

        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $announcement_ids = array_column($data['announcements'], 'id');

        $this->assertContains($high_priority_id, $announcement_ids);
        $this->assertNotContains($normal_priority_id, $announcement_ids);
    }

    /**
     * Helper: Create a test announcement
     */
    private function createTestAnnouncement($title, $status, $meta = []) {
        $post_id = wp_insert_post([
            'post_title' => $title,
            'post_content' => 'Test content for ' . $title,
            'post_status' => $status,
            'post_type' => 'mayo_announcement'
        ]);

        $defaults = [
            'display_start_date' => date('Y-m-d', strtotime('-7 days')),
            'display_end_date' => date('Y-m-d', strtotime('+30 days')),
            'priority' => 'normal'
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
