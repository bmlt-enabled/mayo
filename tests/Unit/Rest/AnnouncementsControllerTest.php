<?php

namespace BmltEnabled\Mayo\Tests\Unit\Rest;

use BmltEnabled\Mayo\Tests\Unit\TestCase;
use BmltEnabled\Mayo\Rest\AnnouncementsController;
use BmltEnabled\Mayo\Rest\Helpers\ServiceBodyLookup;
use Brain\Monkey\Functions;

class AnnouncementsControllerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        ServiceBodyLookup::clear_cache();
        $this->mockGetOption([
            'mayo_settings' => [
                'notification_email' => 'admin@example.com',
                'bmlt_root_server' => 'https://bmlt.example.com'
            ],
            'admin_email' => 'admin@example.com',
            'mayo_external_sources' => []
        ]);
        $this->mockPostMeta();
        $this->mockWpMail();
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

        AnnouncementsController::register_routes();

        $this->assertContains('event-manager/v1/announcements', $registeredRoutes);
        $this->assertContains('event-manager/v1/announcement/(?P<id>\d+)', $registeredRoutes);
        $this->assertContains('event-manager/v1/announcement-by-slug/(?P<slug>[a-zA-Z0-9-]+)', $registeredRoutes);
        $this->assertContains('event-manager/v1/submit-announcement', $registeredRoutes);
    }

    /**
     * Test get_announcements returns announcements
     */
    public function testGetAnnouncementsReturnsAnnouncements(): void {
        $posts = [];
        Functions\when('get_posts')->justReturn($posts);
        Functions\when('get_term_by')->justReturn(null);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/announcements');
        $response = AnnouncementsController::get_announcements($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertArrayHasKey('announcements', $data);
        $this->assertArrayHasKey('total', $data);
    }

    /**
     * Test get_announcements with priority filter
     */
    public function testGetAnnouncementsWithPriorityFilter(): void {
        Functions\when('get_posts')->justReturn([]);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/announcements');
        $request->set_param('priority', 'high');

        $response = AnnouncementsController::get_announcements($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    /**
     * Test get_announcements with category filter
     */
    public function testGetAnnouncementsWithCategoryFilter(): void {
        Functions\when('get_posts')->justReturn([]);
        Functions\when('get_term_by')->justReturn(
            (object)['term_id' => 1]
        );

        $request = new \WP_REST_Request('GET', '/event-manager/v1/announcements');
        $request->set_param('categories', 'news,events');

        $response = AnnouncementsController::get_announcements($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    /**
     * Test get_announcements with active filter false
     */
    public function testGetAnnouncementsWithActiveFilterFalse(): void {
        Functions\when('get_posts')->justReturn([]);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/announcements');
        $request->set_param('active', 'false');

        $response = AnnouncementsController::get_announcements($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    /**
     * Test get_announcements with linked_event filter
     */
    public function testGetAnnouncementsWithLinkedEventFilter(): void {
        Functions\when('get_posts')->justReturn([]);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/announcements');
        $request->set_param('linked_event', 123);

        $response = AnnouncementsController::get_announcements($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    /**
     * Test get_announcements with orderby and order parameters
     */
    public function testGetAnnouncementsWithOrderParameters(): void {
        $post1 = $this->createMockPost([
            'ID' => 1,
            'post_title' => 'Announcement A',
            'post_type' => 'mayo_announcement',
            'post_content' => 'Content',
            'post_date' => '2024-01-01'
        ]);
        $post2 = $this->createMockPost([
            'ID' => 2,
            'post_title' => 'Announcement B',
            'post_type' => 'mayo_announcement',
            'post_content' => 'Content',
            'post_date' => '2024-02-01'
        ]);

        $this->setPostMeta(1, ['display_start_date' => '2024-01-01']);
        $this->setPostMeta(2, ['display_start_date' => '2024-02-01']);

        Functions\when('get_posts')->justReturn([$post1, $post2]);
        Functions\when('get_the_excerpt')->justReturn('Excerpt');
        Functions\when('get_the_post_thumbnail_url')->justReturn('');
        Functions\when('wp_get_post_terms')->justReturn([]);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/announcements');
        $request->set_param('orderby', 'title');
        $request->set_param('order', 'ASC');

        $response = AnnouncementsController::get_announcements($request);
        $data = $response->get_data();

        $this->assertEquals(2, $data['total']);
    }

    /**
     * Test get_announcement returns announcement by ID
     */
    public function testGetAnnouncementReturnsAnnouncementById(): void {
        $post = $this->createMockPost([
            'ID' => 100,
            'post_title' => 'Test Announcement',
            'post_type' => 'mayo_announcement',
            'post_status' => 'publish',
            'post_content' => 'Test content',
            'post_date' => '2024-01-01'
        ]);

        $this->setPostMeta(100, [
            'display_start_date' => '2024-01-01',
            'display_end_date' => '2024-12-31'
        ]);

        Functions\when('get_post')->justReturn($post);
        Functions\when('get_the_excerpt')->justReturn('Excerpt');
        Functions\when('get_the_post_thumbnail_url')->justReturn('https://example.com/image.jpg');
        Functions\when('wp_get_post_terms')->justReturn([]);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/announcement/100');
        $request->set_param('id', '100');

        $response = AnnouncementsController::get_announcement($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertEquals(100, $data['id']);
        $this->assertEquals('Test Announcement', $data['title']);
    }

    /**
     * Test get_announcement returns 404 for non-existent announcement
     */
    public function testGetAnnouncementReturns404ForNonExistent(): void {
        Functions\when('get_post')->justReturn(null);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/announcement/999');
        $request->set_param('id', '999');

        $response = AnnouncementsController::get_announcement($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('not_found', $response->get_error_code());
    }

    /**
     * Test get_announcement returns 404 for wrong post type
     */
    public function testGetAnnouncementReturns404ForWrongPostType(): void {
        $post = $this->createMockPost([
            'ID' => 100,
            'post_type' => 'post'
        ]);

        Functions\when('get_post')->justReturn($post);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/announcement/100');
        $request->set_param('id', '100');

        $response = AnnouncementsController::get_announcement($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('not_found', $response->get_error_code());
    }

    /**
     * Test get_announcement_by_slug returns announcement
     */
    public function testGetAnnouncementBySlugReturnsAnnouncement(): void {
        $post = $this->createMockPost([
            'ID' => 101,
            'post_title' => 'Test Announcement',
            'post_type' => 'mayo_announcement',
            'post_name' => 'test-announcement',
            'post_content' => 'Content',
            'post_date' => '2024-01-01'
        ]);

        $this->setPostMeta(101, []);

        Functions\when('get_posts')->justReturn([$post]);
        Functions\when('get_the_excerpt')->justReturn('');
        Functions\when('get_the_post_thumbnail_url')->justReturn('');
        Functions\when('wp_get_post_terms')->justReturn([]);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/announcement-by-slug/test-announcement');
        $request->set_param('slug', 'test-announcement');

        $response = AnnouncementsController::get_announcement_by_slug($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertEquals(101, $data['id']);
    }

    /**
     * Test get_announcement_by_slug returns 404 when not found
     */
    public function testGetAnnouncementBySlugReturns404WhenNotFound(): void {
        Functions\when('get_posts')->justReturn([]);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/announcement-by-slug/nonexistent');
        $request->set_param('slug', 'nonexistent');

        $response = AnnouncementsController::get_announcement_by_slug($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('not_found', $response->get_error_code());
    }

    /**
     * Test submit_announcement creates announcement
     */
    public function testSubmitAnnouncementCreatesAnnouncement(): void {
        Functions\expect('wp_insert_post')->once()->andReturn(200);
        Functions\expect('add_post_meta')->andReturn(true);
        Functions\when('get_bloginfo')->justReturn('Test Site');

        $request = $this->createRestRequest('POST', '/event-manager/v1/submit-announcement', [
            'title' => 'New Announcement',
            'description' => 'Test description',
            'service_body' => '1',
            'email' => 'submitter@example.com',
            'contact_name' => 'John Doe',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31'
        ]);

        $response = AnnouncementsController::submit_announcement($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals(200, $data['id']);

        // Verify email was sent
        $emails = $this->getCapturedEmails();
        $this->assertNotEmpty($emails);
    }

    /**
     * Test submit_announcement handles insert error
     */
    public function testSubmitAnnouncementHandlesInsertError(): void {
        Functions\expect('wp_insert_post')->once()->andReturn(
            new \WP_Error('insert_error', 'Failed to create announcement')
        );

        $request = $this->createRestRequest('POST', '/event-manager/v1/submit-announcement', [
            'title' => 'New Announcement'
        ]);

        $response = AnnouncementsController::submit_announcement($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(400, $response->get_status());

        $data = $response->get_data();
        $this->assertFalse($data['success']);
    }

    /**
     * Test submit_announcement with categories and tags
     */
    public function testSubmitAnnouncementWithCategoriesAndTags(): void {
        Functions\expect('wp_insert_post')->once()->andReturn(201);
        Functions\expect('add_post_meta')->andReturn(true);
        Functions\when('get_bloginfo')->justReturn('Test Site');

        $request = $this->createRestRequest('POST', '/event-manager/v1/submit-announcement', [
            'title' => 'Categorized Announcement',
            'categories' => '1,2,3',
            'tags' => 'tag1,tag2'
        ]);

        $response = AnnouncementsController::submit_announcement($request);

        $this->assertEquals(200, $response->get_status());
    }

    /**
     * Test format_announcement includes all fields
     */
    public function testFormatAnnouncementIncludesAllFields(): void {
        $post = $this->createMockPost([
            'ID' => 500,
            'post_title' => 'Formatted Announcement',
            'post_type' => 'mayo_announcement',
            'post_content' => '<p>Test content</p>',
            'post_date' => '2024-06-15 10:00:00'
        ]);

        $this->setPostMeta(500, [
            'display_start_date' => '2024-06-01',
            'display_start_time' => '09:00',
            'display_end_date' => '2024-06-30',
            'display_end_time' => '17:00',
            'priority' => 'high',
            'service_body' => '5'
        ]);

        Functions\when('get_the_excerpt')->justReturn('Test excerpt');
        Functions\when('get_the_post_thumbnail_url')->justReturn('https://example.com/image.jpg');
        Functions\when('wp_get_post_terms')->justReturn([]);

        $result = AnnouncementsController::format_announcement($post);

        $this->assertEquals(500, $result['id']);
        $this->assertEquals('Formatted Announcement', $result['title']);
        $this->assertStringContainsString('Test content', $result['content']);
        $this->assertEquals('2024-06-01', $result['display_start_date']);
        $this->assertEquals('09:00', $result['display_start_time']);
        $this->assertEquals('2024-06-30', $result['display_end_date']);
        $this->assertEquals('17:00', $result['display_end_time']);
        $this->assertEquals('high', $result['priority']);
        $this->assertEquals('5', $result['service_body']);
        $this->assertEquals('https://example.com/image.jpg', $result['featured_image']);
        $this->assertArrayHasKey('categories', $result);
        $this->assertArrayHasKey('tags', $result);
        $this->assertArrayHasKey('is_active', $result);
        $this->assertArrayHasKey('linked_events', $result);
    }

    /**
     * Test format_announcement calculates is_active correctly for active announcement
     */
    public function testFormatAnnouncementCalculatesIsActiveForActive(): void {
        $post = $this->createMockPost([
            'ID' => 501,
            'post_title' => 'Active Announcement',
            'post_type' => 'mayo_announcement',
            'post_content' => '',
            'post_date' => '2024-01-01'
        ]);

        $pastDate = date('Y-m-d', strtotime('-30 days'));
        $futureDate = date('Y-m-d', strtotime('+30 days'));

        $this->setPostMeta(501, [
            'display_start_date' => $pastDate,
            'display_end_date' => $futureDate
        ]);

        Functions\when('get_the_excerpt')->justReturn('');
        Functions\when('get_the_post_thumbnail_url')->justReturn('');
        Functions\when('wp_get_post_terms')->justReturn([]);

        $result = AnnouncementsController::format_announcement($post);

        $this->assertTrue($result['is_active']);
    }

    /**
     * Test format_announcement calculates is_active correctly for future announcement
     */
    public function testFormatAnnouncementCalculatesIsActiveForFuture(): void {
        $post = $this->createMockPost([
            'ID' => 502,
            'post_title' => 'Future Announcement',
            'post_type' => 'mayo_announcement',
            'post_content' => '',
            'post_date' => '2024-01-01'
        ]);

        $futureStart = date('Y-m-d', strtotime('+30 days'));
        $futureEnd = date('Y-m-d', strtotime('+60 days'));

        $this->setPostMeta(502, [
            'display_start_date' => $futureStart,
            'display_end_date' => $futureEnd
        ]);

        Functions\when('get_the_excerpt')->justReturn('');
        Functions\when('get_the_post_thumbnail_url')->justReturn('');
        Functions\when('wp_get_post_terms')->justReturn([]);

        $result = AnnouncementsController::format_announcement($post);

        $this->assertFalse($result['is_active']);
    }

    /**
     * Test format_announcement calculates is_active correctly for past announcement
     */
    public function testFormatAnnouncementCalculatesIsActiveForPast(): void {
        $post = $this->createMockPost([
            'ID' => 503,
            'post_title' => 'Past Announcement',
            'post_type' => 'mayo_announcement',
            'post_content' => '',
            'post_date' => '2024-01-01'
        ]);

        $pastStart = date('Y-m-d', strtotime('-60 days'));
        $pastEnd = date('Y-m-d', strtotime('-30 days'));

        $this->setPostMeta(503, [
            'display_start_date' => $pastStart,
            'display_end_date' => $pastEnd
        ]);

        Functions\when('get_the_excerpt')->justReturn('');
        Functions\when('get_the_post_thumbnail_url')->justReturn('');
        Functions\when('wp_get_post_terms')->justReturn([]);

        $result = AnnouncementsController::format_announcement($post);

        $this->assertFalse($result['is_active']);
    }

    /**
     * Test format_announcement with empty display dates is always active
     */
    public function testFormatAnnouncementWithEmptyDatesIsActive(): void {
        $post = $this->createMockPost([
            'ID' => 504,
            'post_title' => 'No Dates Announcement',
            'post_type' => 'mayo_announcement',
            'post_content' => '',
            'post_date' => '2024-01-01'
        ]);

        $this->setPostMeta(504, []);

        Functions\when('get_the_excerpt')->justReturn('');
        Functions\when('get_the_post_thumbnail_url')->justReturn('');
        Functions\when('wp_get_post_terms')->justReturn([]);

        $result = AnnouncementsController::format_announcement($post);

        $this->assertTrue($result['is_active']);
    }

    /**
     * Test format_announcement includes categories and tags
     */
    public function testFormatAnnouncementIncludesTaxonomies(): void {
        $post = $this->createMockPost([
            'ID' => 505,
            'post_title' => 'Tagged Announcement',
            'post_type' => 'mayo_announcement',
            'post_content' => '',
            'post_date' => '2024-01-01'
        ]);

        $this->setPostMeta(505, []);

        Functions\when('get_the_excerpt')->justReturn('');
        Functions\when('get_the_post_thumbnail_url')->justReturn('');
        Functions\when('wp_get_post_terms')->alias(function($post_id, $taxonomy) {
            if ($taxonomy === 'category') {
                return [(object)['term_id' => 1, 'name' => 'News', 'slug' => 'news']];
            }
            if ($taxonomy === 'post_tag') {
                return [(object)['term_id' => 2, 'name' => 'Featured', 'slug' => 'featured']];
            }
            return [];
        });
        Functions\when('get_term_link')->justReturn('https://example.com/term/slug');

        $result = AnnouncementsController::format_announcement($post);

        $this->assertNotEmpty($result['categories']);
        $this->assertNotEmpty($result['tags']);
    }

    /**
     * Test get_announcements with orderby created
     */
    public function testGetAnnouncementsWithOrderByCreated(): void {
        $post1 = $this->createMockPost([
            'ID' => 1,
            'post_title' => 'Announcement A',
            'post_type' => 'mayo_announcement',
            'post_content' => 'Content',
            'post_date' => '2024-01-01 10:00:00'
        ]);
        $post2 = $this->createMockPost([
            'ID' => 2,
            'post_title' => 'Announcement B',
            'post_type' => 'mayo_announcement',
            'post_content' => 'Content',
            'post_date' => '2024-02-01 10:00:00'
        ]);

        $this->setPostMeta(1, ['display_start_date' => '2024-01-01']);
        $this->setPostMeta(2, ['display_start_date' => '2024-02-01']);

        Functions\when('get_posts')->justReturn([$post1, $post2]);
        Functions\when('get_the_excerpt')->justReturn('Excerpt');
        Functions\when('get_the_post_thumbnail_url')->justReturn('');
        Functions\when('wp_get_post_terms')->justReturn([]);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/announcements');
        $request->set_param('orderby', 'created');
        $request->set_param('order', 'DESC');

        $response = AnnouncementsController::get_announcements($request);
        $data = $response->get_data();

        $this->assertEquals(2, $data['total']);
    }

    /**
     * Test get_announcements with orderby date default order
     */
    public function testGetAnnouncementsWithOrderByDateDefaultOrder(): void {
        $post = $this->createMockPost([
            'ID' => 1,
            'post_title' => 'Test Announcement',
            'post_type' => 'mayo_announcement',
            'post_content' => 'Content',
            'post_date' => '2024-01-01'
        ]);

        $this->setPostMeta(1, ['display_start_date' => '2024-01-01']);

        Functions\when('get_posts')->justReturn([$post]);
        Functions\when('get_the_excerpt')->justReturn('');
        Functions\when('get_the_post_thumbnail_url')->justReturn('');
        Functions\when('wp_get_post_terms')->justReturn([]);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/announcements');
        $request->set_param('orderby', 'date');

        $response = AnnouncementsController::get_announcements($request);
        $data = $response->get_data();

        $this->assertEquals(1, $data['total']);
    }

    /**
     * Test get_announcements with tags filter
     */
    public function testGetAnnouncementsWithTagsFilter(): void {
        Functions\when('get_posts')->justReturn([]);
        Functions\when('get_term_by')->justReturn((object)['term_id' => 5]);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/announcements');
        $request->set_param('tags', 'featured,important');

        $response = AnnouncementsController::get_announcements($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    /**
     * Test submit_announcement with start and end times
     */
    public function testSubmitAnnouncementWithTimes(): void {
        Functions\expect('wp_insert_post')->once()->andReturn(210);
        Functions\expect('add_post_meta')->andReturn(true);
        Functions\when('get_bloginfo')->justReturn('Test Site');

        $request = $this->createRestRequest('POST', '/event-manager/v1/submit-announcement', [
            'title' => 'Timed Announcement',
            'description' => 'Test',
            'start_date' => '2025-01-01',
            'start_time' => '09:00',
            'end_date' => '2025-12-31',
            'end_time' => '17:00'
        ]);

        $response = AnnouncementsController::submit_announcement($request);

        $this->assertEquals(200, $response->get_status());
    }

    /**
     * Test submit_announcement sends email with categories
     */
    public function testSubmitAnnouncementSendsEmailWithCategories(): void {
        Functions\expect('wp_insert_post')->once()->andReturn(211);
        Functions\expect('add_post_meta')->andReturn(true);
        Functions\when('get_bloginfo')->justReturn('Test Site');
        Functions\when('get_category')->alias(function($id) {
            return (object)['name' => 'Category ' . $id];
        });

        $request = $this->createRestRequest('POST', '/event-manager/v1/submit-announcement', [
            'title' => 'Category Announcement',
            'categories' => '1,2,3',
            'tags' => 'featured',
            'email' => 'test@example.com',
            'contact_name' => 'Test User'
        ]);

        $response = AnnouncementsController::submit_announcement($request);

        $this->assertEquals(200, $response->get_status());
        $emails = $this->getCapturedEmails();
        $this->assertNotEmpty($emails);
    }

    /**
     * Test format_announcement with linked local event
     */
    public function testFormatAnnouncementWithLinkedLocalEvent(): void {
        $post = $this->createMockPost([
            'ID' => 600,
            'post_title' => 'Announcement with Event',
            'post_type' => 'mayo_announcement',
            'post_content' => '',
            'post_date' => '2024-01-01'
        ]);

        $linkedEvent = $this->createMockPost([
            'ID' => 100,
            'post_title' => 'Linked Event',
            'post_type' => 'mayo_event',
            'post_status' => 'publish'
        ]);

        $this->setPostMeta(600, [
            'linked_event_refs' => [
                ['type' => 'local', 'id' => 100]
            ]
        ]);

        $this->setPostMeta(100, [
            'event_start_date' => '2024-06-15'
        ]);

        Functions\when('get_post')->alias(function($id) use ($linkedEvent) {
            if ($id == 100) {
                return $linkedEvent;
            }
            return null;
        });
        Functions\when('get_the_excerpt')->justReturn('');
        Functions\when('get_the_post_thumbnail_url')->justReturn('');
        Functions\when('wp_get_post_terms')->justReturn([]);
        Functions\when('get_the_title')->justReturn('Linked Event');

        $result = AnnouncementsController::format_announcement($post);

        $this->assertArrayHasKey('linked_events', $result);
    }

    /**
     * Test format_announcement with external event unavailable
     */
    public function testFormatAnnouncementWithExternalEventUnavailable(): void {
        $post = $this->createMockPost([
            'ID' => 601,
            'post_title' => 'Announcement with External Event',
            'post_type' => 'mayo_announcement',
            'post_content' => '',
            'post_date' => '2024-01-01'
        ]);

        $this->setPostMeta(601, [
            'linked_event_refs' => [
                ['type' => 'external', 'id' => 999, 'source_id' => 'nonexistent']
            ]
        ]);

        $this->mockGetOption([
            'mayo_settings' => [
                'notification_email' => 'admin@example.com',
                'bmlt_root_server' => 'https://bmlt.example.com'
            ],
            'admin_email' => 'admin@example.com',
            'mayo_external_sources' => []
        ]);

        Functions\when('get_post')->justReturn(null);
        Functions\when('get_the_excerpt')->justReturn('');
        Functions\when('get_the_post_thumbnail_url')->justReturn('');
        Functions\when('wp_get_post_terms')->justReturn([]);

        $result = AnnouncementsController::format_announcement($post);

        $this->assertArrayHasKey('linked_events', $result);
        if (!empty($result['linked_events'])) {
            $this->assertTrue($result['linked_events'][0]['unavailable'] ?? false);
        }
    }

    /**
     * Test get_announcements with category_relation AND
     */
    public function testGetAnnouncementsWithCategoryRelationAnd(): void {
        Functions\when('get_posts')->justReturn([]);
        Functions\when('get_term_by')->justReturn((object)['term_id' => 1]);

        $request = new \WP_REST_Request('GET', '/event-manager/v1/announcements');
        $request->set_param('categories', 'news,events');
        $request->set_param('category_relation', 'AND');

        $response = AnnouncementsController::get_announcements($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    /**
     * Test format_announcement handles linked event with rendered title
     */
    public function testFormatAnnouncementHandlesRenderedTitle(): void {
        $post = $this->createMockPost([
            'ID' => 602,
            'post_title' => 'Test Announcement',
            'post_type' => 'mayo_announcement',
            'post_content' => '',
            'post_date' => '2024-01-01'
        ]);

        $this->setPostMeta(602, [
            'linked_event_refs' => [
                ['type' => 'custom_link', 'title' => 'Custom Event', 'url' => 'https://example.com/event']
            ]
        ]);

        Functions\when('get_the_excerpt')->justReturn('');
        Functions\when('get_the_post_thumbnail_url')->justReturn('');
        Functions\when('wp_get_post_terms')->justReturn([]);

        $result = AnnouncementsController::format_announcement($post);

        $this->assertArrayHasKey('linked_events', $result);
    }

    /**
     * Test submit_announcement without notification email
     */
    public function testSubmitAnnouncementWithoutNotificationEmail(): void {
        $this->mockGetOption([
            'mayo_settings' => [
                'notification_email' => '',
                'bmlt_root_server' => 'https://bmlt.example.com'
            ],
            'admin_email' => '',
            'mayo_external_sources' => []
        ]);

        Functions\expect('wp_insert_post')->once()->andReturn(220);
        Functions\expect('add_post_meta')->andReturn(true);
        Functions\when('get_bloginfo')->justReturn('Test Site');

        $request = $this->createRestRequest('POST', '/event-manager/v1/submit-announcement', [
            'title' => 'No Email Announcement'
        ]);

        $response = AnnouncementsController::submit_announcement($request);

        $this->assertEquals(200, $response->get_status());
    }
}
