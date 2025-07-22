<?php

namespace BmltEnabled\Mayo\Tests\Integration;

use WP_UnitTestCase;
use BmltEnabled\Mayo\Rest;

class EmailNotificationsIntegrationTest extends WP_UnitTestCase {
    
    public function setUp(): void {
        parent::setUp();
        
        // Mock wp_mail function to capture emails
        if (!function_exists('wp_mail')) {
            function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
                global $captured_emails;
                $captured_emails[] = [
                    'to' => $to,
                    'subject' => $subject,
                    'message' => $message,
                    'headers' => $headers,
                    'attachments' => $attachments
                ];
                return true;
            }
        }
        
        // Initialize captured emails array
        global $captured_emails;
        $captured_emails = [];
    }
    
    public function tearDown(): void {
        parent::tearDown();
        
        // Clear captured emails
        global $captured_emails;
        $captured_emails = [];
    }
    
    public function testEmailNotificationIncludesAllDetails() {
        // Set up test data
        $test_params = [
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
            'tags' => 'test, meeting',
            'recurring_pattern' => json_encode([
                'type' => 'weekly',
                'interval' => 1,
                'weekdays' => [1, 3], // Monday and Wednesday
                'endDate' => '2024-12-31'
            ])
        ];
        
        // Mock service body data
        $this->mockServiceBodyData();
        
        // Create a test post
        $post_id = wp_insert_post([
            'post_title' => $test_params['event_name'],
            'post_content' => $test_params['description'],
            'post_status' => 'pending',
            'post_type' => 'mayo_event'
        ]);
        
        // Call the email function
        $reflection = new \ReflectionClass('BmltEnabled\Mayo\Rest');
        $method = $reflection->getMethod('send_event_submission_email');
        $method->setAccessible(true);
        $method->invoke(null, $post_id, $test_params);
        
        // Get captured email
        global $captured_emails;
        $this->assertNotEmpty($captured_emails, 'Email should have been sent');
        
        $email = $captured_emails[0];
        
        // Test subject
        $this->assertStringContainsString('New Event Submission: Test Event', $email['subject']);
        
        // Test message content
        $message = $email['message'];
        
        // Check basic event details
        $this->assertStringContainsString('Event Name: Test Event', $message);
        $this->assertStringContainsString('Event Type: Meeting', $message);
        $this->assertStringContainsString('Start Date: 2024-01-15', $message);
        $this->assertStringContainsString('Start Time: 19:00', $message);
        $this->assertStringContainsString('End Date: 2024-01-15', $message);
        $this->assertStringContainsString('End Time: 20:00', $message);
        $this->assertStringContainsString('Timezone: America/New_York', $message);
        
        // Check contact information
        $this->assertStringContainsString('Contact Name: John Doe', $message);
        $this->assertStringContainsString('Contact Email: john@example.com', $message);
        
        // Check service body information
        $this->assertStringContainsString('Service Body: Test Service Body (ID: 1)', $message);
        
        // Check location information
        $this->assertStringContainsString('Location:', $message);
        $this->assertStringContainsString('Name: Test Location', $message);
        $this->assertStringContainsString('Address: 123 Test St, Test City, TS 12345', $message);
        $this->assertStringContainsString('Details: Room 101', $message);
        
        // Check categories and tags
        $this->assertStringContainsString('Categories: Test Category', $message);
        $this->assertStringContainsString('Tags: test, meeting', $message);
        
        // Check recurring pattern
        $this->assertStringContainsString('Recurring Pattern: Weekly on Monday, Wednesday until 2024-12-31', $message);
        
        // Check description
        $this->assertStringContainsString('Description:', $message);
        $this->assertStringContainsString('This is a test event description.', $message);
        
        // Check admin link
        $this->assertStringContainsString('View the event:', $message);
        $this->assertStringContainsString(admin_url('post.php?post=' . $post_id . '&action=edit'), $message);
    }
    
    public function testEmailNotificationWithUnaffiliatedServiceBody() {
        $test_params = [
            'event_name' => 'Unaffiliated Event',
            'event_type' => 'Meeting',
            'service_body' => '0', // Unaffiliated
            'event_start_date' => '2024-01-15',
            'event_start_time' => '19:00',
            'event_end_date' => '2024-01-15',
            'event_end_time' => '20:00',
            'timezone' => 'America/New_York',
            'contact_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'description' => 'Unaffiliated event description.'
        ];
        
        $post_id = wp_insert_post([
            'post_title' => $test_params['event_name'],
            'post_content' => $test_params['description'],
            'post_status' => 'pending',
            'post_type' => 'mayo_event'
        ]);
        
        $reflection = new \ReflectionClass('BmltEnabled\Mayo\Rest');
        $method = $reflection->getMethod('send_event_submission_email');
        $method->setAccessible(true);
        $method->invoke(null, $post_id, $test_params);
        
        global $captured_emails;
        $this->assertNotEmpty($captured_emails);
        
        $email = $captured_emails[0];
        $message = $email['message'];
        
        // Check that unaffiliated service body is handled correctly
        $this->assertStringContainsString('Service Body: Unaffiliated (ID: 0)', $message);
    }
    
    public function testEmailNotificationWithFileAttachments() {
        // Mock $_FILES
        $_FILES = [
            'flyer' => [
                'name' => 'test-flyer.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/test',
                'error' => 0,
                'size' => 1024
            ]
        ];
        
        $test_params = [
            'event_name' => 'Event with Flyer',
            'event_type' => 'Meeting',
            'service_body' => '1',
            'event_start_date' => '2024-01-15',
            'event_start_time' => '19:00',
            'event_end_date' => '2024-01-15',
            'event_end_time' => '20:00',
            'timezone' => 'America/New_York',
            'contact_name' => 'Test User',
            'email' => 'test@example.com',
            'description' => 'Event with flyer attachment.'
        ];
        
        $post_id = wp_insert_post([
            'post_title' => $test_params['event_name'],
            'post_content' => $test_params['description'],
            'post_status' => 'pending',
            'post_type' => 'mayo_event'
        ]);
        
        $reflection = new \ReflectionClass('BmltEnabled\Mayo\Rest');
        $method = $reflection->getMethod('send_event_submission_email');
        $method->setAccessible(true);
        $method->invoke(null, $post_id, $test_params);
        
        global $captured_emails;
        $this->assertNotEmpty($captured_emails);
        
        $email = $captured_emails[0];
        $message = $email['message'];
        
        // Check that file attachment is mentioned
        $this->assertStringContainsString('Attachments: test-flyer.jpg', $message);
        
        // Clean up
        $_FILES = [];
    }
    
    private function mockServiceBodyData() {
        // Mock the wp_remote_get function to return test service body data
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