<?php

namespace BmltEnabled\Mayo\Tests\Integration;

use WP_UnitTestCase;
use BmltEnabled\Mayo\Admin;

class SubmitterNotificationsIntegrationTest extends WP_UnitTestCase {
    
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
    
    public function testEventPublishedNotificationSent() {
        // Create a test event with pending status
        $post_id = wp_insert_post([
            'post_title' => 'Test Event',
            'post_content' => 'This is a test event description.',
            'post_status' => 'pending',
            'post_type' => 'mayo_event'
        ]);
        
        // Add event metadata
        add_post_meta($post_id, 'event_type', 'Meeting');
        add_post_meta($post_id, 'event_start_date', '2024-01-15');
        add_post_meta($post_id, 'event_start_time', '19:00');
        add_post_meta($post_id, 'event_end_date', '2024-01-15');
        add_post_meta($post_id, 'event_end_time', '20:00');
        add_post_meta($post_id, 'timezone', 'America/New_York');
        add_post_meta($post_id, 'service_body', '1');
        add_post_meta($post_id, 'email', 'test@example.com');
        add_post_meta($post_id, 'contact_name', 'John Doe');
        add_post_meta($post_id, 'location_name', 'Test Location');
        add_post_meta($post_id, 'location_address', '123 Test St');
        
        // Mock service body data
        $this->mockServiceBodyData();
        
        // Enable submitter notifications
        $settings = get_option('mayo_settings', []);
        $settings['notify_submitters'] = true;
        update_option('mayo_settings', $settings);
        
        // Simulate status transition from pending to publish
        $post = get_post($post_id);
        Admin::handle_event_status_transition('publish', 'pending', $post);
        
        // Check that email was sent
        global $captured_emails;
        $this->assertNotEmpty($captured_emails, 'Email should have been sent');
        
        $email = $captured_emails[0];
        
        // Test email details
        $this->assertEquals('test@example.com', $email['to']);
        $this->assertStringContainsString('Your event has been published: Test Event', $email['subject']);
        
        $message = $email['message'];
        
        // Check email content
        $this->assertStringContainsString('Hello John Doe,', $message);
        $this->assertStringContainsString('Great news! Your event has been approved and published on our website.', $message);
        $this->assertStringContainsString('Event Name: Test Event', $message);
        $this->assertStringContainsString('Event Type: Meeting', $message);
        $this->assertStringContainsString('Service Body: Test Service Body', $message);
        $this->assertStringContainsString('Start Date: 2024-01-15', $message);
        $this->assertStringContainsString('Start Time: 19:00', $message);
        $this->assertStringContainsString('End Date: 2024-01-15', $message);
        $this->assertStringContainsString('End Time: 20:00', $message);
        $this->assertStringContainsString('Timezone: America/New_York', $message);
        $this->assertStringContainsString('Location:', $message);
        $this->assertStringContainsString('Test Location', $message);
        $this->assertStringContainsString('123 Test St', $message);
        $this->assertStringContainsString('This is a test event description.', $message);
        $this->assertStringContainsString('View your event:', $message);
        $this->assertStringContainsString('Thank you for submitting your event!', $message);
    }
    

    
    public function testEventPublishedNotificationNotSentForOtherPostTypes() {
        // Create a test post with pending status (not mayo_event)
        $post_id = wp_insert_post([
            'post_title' => 'Test Post',
            'post_content' => 'This is a test post.',
            'post_status' => 'pending',
            'post_type' => 'post'
        ]);
        
        // Add email metadata
        add_post_meta($post_id, 'email', 'test@example.com');
        
        // Enable submitter notifications
        $settings = get_option('mayo_settings', []);
        $settings['notify_submitters'] = true;
        update_option('mayo_settings', $settings);
        
        // Simulate status transition from pending to publish
        $post = get_post($post_id);
        Admin::handle_event_status_transition('publish', 'pending', $post);
        
        // Check that no email was sent
        global $captured_emails;
        $this->assertEmpty($captured_emails, 'No email should have been sent for non-mayo_event post types');
    }
    
    public function testEventPublishedNotificationNotSentForOtherStatusTransitions() {
        // Create a test event with draft status
        $post_id = wp_insert_post([
            'post_title' => 'Test Event',
            'post_content' => 'This is a test event description.',
            'post_status' => 'draft',
            'post_type' => 'mayo_event'
        ]);
        
        // Add event metadata
        add_post_meta($post_id, 'email', 'test@example.com');
        add_post_meta($post_id, 'contact_name', 'John Doe');
        
        // Enable submitter notifications
        $settings = get_option('mayo_settings', []);
        $settings['notify_submitters'] = true;
        update_option('mayo_settings', $settings);
        
        // Simulate status transition from draft to publish (not pending to publish)
        $post = get_post($post_id);
        Admin::handle_event_status_transition('publish', 'draft', $post);
        
        // Check that no email was sent
        global $captured_emails;
        $this->assertEmpty($captured_emails, 'No email should have been sent for non-pending to publish transitions');
    }
    
    public function testEventPublishedNotificationNotSentWithoutEmail() {
        // Create a test event with pending status but no email
        $post_id = wp_insert_post([
            'post_title' => 'Test Event',
            'post_content' => 'This is a test event description.',
            'post_status' => 'pending',
            'post_type' => 'mayo_event'
        ]);
        
        // Add event metadata but no email
        add_post_meta($post_id, 'contact_name', 'John Doe');
        
        // Enable submitter notifications
        $settings = get_option('mayo_settings', []);
        $settings['notify_submitters'] = true;
        update_option('mayo_settings', $settings);
        
        // Simulate status transition from pending to publish
        $post = get_post($post_id);
        Admin::handle_event_status_transition('publish', 'pending', $post);
        
        // Check that no email was sent
        global $captured_emails;
        $this->assertEmpty($captured_emails, 'No email should have been sent when no email is provided');
    }
    
    public function testEventPublishedNotificationWithRecurringPattern() {
        // Create a test event with pending status
        $post_id = wp_insert_post([
            'post_title' => 'Recurring Test Event',
            'post_content' => 'This is a recurring test event.',
            'post_status' => 'pending',
            'post_type' => 'mayo_event'
        ]);
        
        // Add event metadata with recurring pattern
        add_post_meta($post_id, 'event_type', 'Meeting');
        add_post_meta($post_id, 'event_start_date', '2024-01-15');
        add_post_meta($post_id, 'event_start_time', '19:00');
        add_post_meta($post_id, 'event_end_date', '2024-01-15');
        add_post_meta($post_id, 'event_end_time', '20:00');
        add_post_meta($post_id, 'timezone', 'America/New_York');
        add_post_meta($post_id, 'service_body', '1');
        add_post_meta($post_id, 'email', 'test@example.com');
        add_post_meta($post_id, 'contact_name', 'John Doe');
        
        // Add recurring pattern
        $recurring_pattern = [
            'type' => 'weekly',
            'interval' => 1,
            'weekdays' => [1, 3], // Monday and Wednesday
            'endDate' => '2024-12-31'
        ];
        add_post_meta($post_id, 'recurring_pattern', $recurring_pattern);
        
        // Mock service body data
        $this->mockServiceBodyData();
        
        // Enable submitter notifications
        $settings = get_option('mayo_settings', []);
        $settings['notify_submitters'] = true;
        update_option('mayo_settings', $settings);
        
        // Simulate status transition from pending to publish
        $post = get_post($post_id);
        Admin::handle_event_status_transition('publish', 'pending', $post);
        
        // Check that email was sent
        global $captured_emails;
        $this->assertNotEmpty($captured_emails, 'Email should have been sent');
        
        $email = $captured_emails[0];
        $message = $email['message'];
        
        // Check that recurring pattern information is included
        $this->assertStringContainsString('Recurring Pattern: Weekly on Monday, Wednesday until 2024-12-31', $message);
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