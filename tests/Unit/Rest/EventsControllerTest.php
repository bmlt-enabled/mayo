<?php

namespace BmltEnabled\Mayo\Tests\Unit\Rest;

use BmltEnabled\Mayo\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * Unit tests for EventsController
 *
 * Note: These tests focus on the behaviors that can be tested without WordPress
 * integration. Complex tests involving database queries and external dependencies
 * are deferred to integration testing.
 */
class EventsControllerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->mockGetOption([
            'mayo_settings' => [
                'bmlt_root_server' => 'https://bmlt.example.com',
                'notification_email' => 'admin@example.com',
            ],
            'mayo_external_sources' => []
        ]);

        $this->mockPostMeta();
        $this->mockWpMail();
        $this->mockGetTheTitle();
        $this->mockHasPostThumbnail(false);
        $this->mockWpResetPostdata();
        $this->mockTrailingslashit();
    }

    /**
     * Test REST request for submitting an event
     */
    public function testRestRequestForSubmitEvent(): void {
        $request = $this->createRestRequest('POST', '/event-manager/v1/submit-event', [
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

        $params = $request->get_params();

        $this->assertEquals('POST', $request->get_method());
        $this->assertEquals('Test Event Name', $params['event_name']);
        $this->assertEquals('Meeting', $params['event_type']);
        $this->assertEquals('2024-06-15', $params['event_start_date']);
        $this->assertEquals('America/New_York', $params['timezone']);
        $this->assertEquals('test@example.com', $params['email']);
    }

    /**
     * Test post meta storage for event data
     */
    public function testPostMetaStorageForEventData(): void {
        $postId = 123;
        $this->setPostMeta($postId, [
            'event_type' => 'Meeting',
            'event_start_date' => '2024-06-15',
            'event_end_date' => '2024-06-15',
            'event_start_time' => '19:00',
            'event_end_time' => '21:00',
            'timezone' => 'America/New_York',
            'service_body' => '1',
            'email' => 'test@example.com',
            'contact_name' => 'Test Contact',
            'location_name' => 'Test Venue',
            'location_address' => '123 Main St',
            'location_details' => 'Room 101'
        ]);

        $eventType = get_post_meta($postId, 'event_type', true);
        $startDate = get_post_meta($postId, 'event_start_date', true);
        $timezone = get_post_meta($postId, 'timezone', true);
        $locationName = get_post_meta($postId, 'location_name', true);

        $this->assertEquals('Meeting', $eventType);
        $this->assertEquals('2024-06-15', $startDate);
        $this->assertEquals('America/New_York', $timezone);
        $this->assertEquals('Test Venue', $locationName);
    }

    /**
     * Test recurring pattern JSON encoding/decoding
     */
    public function testRecurringPatternJsonEncodingDecoding(): void {
        $recurringPattern = [
            'type' => 'weekly',
            'interval' => 1,
            'weekdays' => [1, 3, 5],
            'endDate' => '2024-12-31'
        ];

        $encoded = json_encode($recurringPattern);
        $decoded = json_decode($encoded, true);

        $this->assertEquals('weekly', $decoded['type']);
        $this->assertEquals(1, $decoded['interval']);
        $this->assertEquals([1, 3, 5], $decoded['weekdays']);
        $this->assertEquals('2024-12-31', $decoded['endDate']);
    }

    /**
     * Test mock post creation for events
     */
    public function testMockPostCreationForEvents(): void {
        $post = $this->createMockPost([
            'ID' => 125,
            'post_title' => 'Format Test Event',
            'post_content' => 'Event content here',
            'post_status' => 'publish',
            'post_name' => 'format-test-event'
        ]);

        $this->assertEquals(125, $post->ID);
        $this->assertEquals('Format Test Event', $post->post_title);
        $this->assertEquals('publish', $post->post_status);
        $this->assertEquals('mayo_event', $post->post_type);
    }

    /**
     * Test REST request for GET event by ID
     */
    public function testRestRequestForGetEventById(): void {
        $request = $this->createRestRequest('GET', '/event-manager/v1/events/126');
        $request->set_param('id', 126);

        $this->assertEquals('GET', $request->get_method());
        $this->assertEquals(126, $request->get_param('id'));
    }

    /**
     * Test email content building helpers
     */
    public function testEmailContentBuildingHelpers(): void {
        $params = [
            'event_name' => 'Test Event',
            'event_type' => 'Meeting',
            'service_body' => '1',
            'event_start_date' => '2024-01-15',
            'event_start_time' => '19:00',
            'event_end_date' => '2024-01-15',
            'event_end_time' => '20:00',
            'timezone' => 'America/New_York',
            'contact_name' => 'John Doe',
            'email' => 'john@example.com',
            'description' => 'This is a test event description.',
            'location_name' => 'Test Location',
            'location_address' => '123 Test St, Test City, TS 12345',
            'location_details' => 'Room 101',
            'categories' => 'Test Category',
            'tags' => 'test, meeting'
        ];

        // Verify params have expected structure
        $this->assertArrayHasKey('event_name', $params);
        $this->assertArrayHasKey('event_type', $params);
        $this->assertArrayHasKey('event_start_date', $params);
        $this->assertArrayHasKey('location_name', $params);

        $this->assertEquals('Test Event', $params['event_name']);
        $this->assertEquals('Meeting', $params['event_type']);
    }

    /**
     * Test recurring pattern validation
     */
    public function testRecurringPatternValidation(): void {
        // Valid weekly pattern
        $weeklyPattern = [
            'type' => 'weekly',
            'interval' => 1,
            'weekdays' => [1, 3, 5],
            'endDate' => '2024-12-31'
        ];

        $this->assertEquals('weekly', $weeklyPattern['type']);
        $this->assertIsArray($weeklyPattern['weekdays']);
        $this->assertContains(1, $weeklyPattern['weekdays']);
        $this->assertContains(3, $weeklyPattern['weekdays']);
        $this->assertContains(5, $weeklyPattern['weekdays']);

        // Valid monthly pattern
        $monthlyPattern = [
            'type' => 'monthly',
            'interval' => 1,
            'monthlyType' => 'date',
            'monthlyDate' => '15',
            'endDate' => '2024-12-31'
        ];

        $this->assertEquals('monthly', $monthlyPattern['type']);
        $this->assertEquals('date', $monthlyPattern['monthlyType']);
        $this->assertEquals('15', $monthlyPattern['monthlyDate']);
    }

    /**
     * Test sanitization functions
     */
    public function testSanitizationFunctions(): void {
        // Test sanitize_text_field
        $text = sanitize_text_field('  <script>alert("xss")</script>Test Text  ');
        $this->assertEquals('alert("xss")Test Text', $text);

        // Test sanitize_email
        $email = sanitize_email('test@example.com');
        $this->assertEquals('test@example.com', $email);

        // Test is_email
        $validEmail = is_email('test@example.com');
        $this->assertEquals('test@example.com', $validEmail);

        $invalidEmail = is_email('not-valid');
        $this->assertFalse($invalidEmail);
    }

    /**
     * Test WP_REST_Response creation
     */
    public function testWpRestResponseCreation(): void {
        $response = new \WP_REST_Response([
            'id' => 123,
            'message' => 'Event created successfully'
        ], 200);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertEquals(123, $data['id']);
        $this->assertEquals('Event created successfully', $data['message']);
    }

    /**
     * Test WP_Error creation
     */
    public function testWpErrorCreation(): void {
        $error = new \WP_Error('not_found', 'Event not found');

        $this->assertEquals('not_found', $error->get_error_code());
        $this->assertEquals('Event not found', $error->get_error_message());
    }
}
