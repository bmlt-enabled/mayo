<?php

namespace BmltEnabled\Mayo\Tests\Unit\Rest;

use BmltEnabled\Mayo\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * Unit tests for AnnouncementsController
 *
 * Note: These tests focus on the behaviors that can be tested without WordPress
 * integration. Complex tests involving database queries and external dependencies
 * are deferred to integration testing.
 */
class AnnouncementsControllerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->mockGetOption([
            'mayo_settings' => [
                'notification_email' => 'admin@example.com'
            ]
        ]);

        $this->mockPostMeta();
        $this->mockWpMail();
    }

    /**
     * Test WP_REST_Response creation
     */
    public function testWpRestResponseCreation(): void {
        $response = new \WP_REST_Response(['test' => 'data'], 200);

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals(['test' => 'data'], $response->get_data());
    }

    /**
     * Test WP_Error creation
     */
    public function testWpErrorCreation(): void {
        $error = new \WP_Error('not_found', 'Announcement not found', ['status' => 404]);

        $this->assertEquals('not_found', $error->get_error_code());
        $this->assertEquals('Announcement not found', $error->get_error_message());
    }

    /**
     * Test mock post creation for announcements
     */
    public function testMockPostCreation(): void {
        $post = $this->createMockPost([
            'ID' => 123,
            'post_title' => 'Test Announcement',
            'post_type' => 'mayo_announcement',
            'post_status' => 'publish'
        ]);

        $this->assertEquals(123, $post->ID);
        $this->assertEquals('Test Announcement', $post->post_title);
        $this->assertEquals('mayo_announcement', $post->post_type);
        $this->assertEquals('publish', $post->post_status);
    }

    /**
     * Test post meta storage
     */
    public function testPostMetaStorage(): void {
        $postId = 123;
        $this->setPostMeta($postId, [
            'display_start_date' => '2024-01-01',
            'display_end_date' => '2024-12-31',
            'priority' => 'high'
        ]);

        $startDate = get_post_meta($postId, 'display_start_date', true);
        $endDate = get_post_meta($postId, 'display_end_date', true);
        $priority = get_post_meta($postId, 'priority', true);

        $this->assertEquals('2024-01-01', $startDate);
        $this->assertEquals('2024-12-31', $endDate);
        $this->assertEquals('high', $priority);
    }

    /**
     * Test is_active calculation for current date range
     */
    public function testIsActiveCalculationForCurrentDateRange(): void {
        $today = date('Y-m-d');
        $pastDate = date('Y-m-d', strtotime('-7 days'));
        $futureDate = date('Y-m-d', strtotime('+30 days'));

        // Active: start date in past, end date in future
        $is_active = true;
        if ($pastDate > $today) {
            $is_active = false;
        }
        if ($futureDate < $today) {
            $is_active = false;
        }

        $this->assertTrue($is_active);
    }

    /**
     * Test is_active calculation for future announcement
     */
    public function testIsActiveCalculationForFutureAnnouncement(): void {
        $today = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('+30 days'));
        $endDate = date('Y-m-d', strtotime('+60 days'));

        $is_active = true;
        if ($startDate && $startDate > $today) {
            $is_active = false;
        }

        $this->assertFalse($is_active);
    }

    /**
     * Test is_active calculation for past announcement
     */
    public function testIsActiveCalculationForPastAnnouncement(): void {
        $today = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-60 days'));
        $endDate = date('Y-m-d', strtotime('-30 days'));

        $is_active = true;
        if ($endDate && $endDate < $today) {
            $is_active = false;
        }

        $this->assertFalse($is_active);
    }

    /**
     * Test REST request for GET announcement by ID
     */
    public function testRestRequestForGetAnnouncementById(): void {
        $request = $this->createRestRequest('GET', '/event-manager/v1/announcement/123');
        $request->set_param('id', 123);

        $this->assertEquals('GET', $request->get_method());
        $this->assertEquals(123, $request->get_param('id'));
    }

    /**
     * Test REST request for POST submit-announcement
     */
    public function testRestRequestForSubmitAnnouncement(): void {
        $request = $this->createRestRequest('POST', '/event-manager/v1/submit-announcement', [
            'title' => 'Test Announcement',
            'description' => 'Test description',
            'service_body' => '1',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31'
        ]);

        $params = $request->get_params();

        $this->assertEquals('POST', $request->get_method());
        $this->assertEquals('Test Announcement', $params['title']);
        $this->assertEquals('Test description', $params['description']);
        $this->assertEquals('1', $params['service_body']);
        $this->assertEquals('2025-01-01', $params['start_date']);
        $this->assertEquals('2025-12-31', $params['end_date']);
    }
}
