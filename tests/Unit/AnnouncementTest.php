<?php

namespace BmltEnabled\Mayo\Tests\Unit;

use BmltEnabled\Mayo\Announcement;
use Brain\Monkey\Functions;

class AnnouncementTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        $this->mockPostMeta();
        $this->mockGetOption([
            'mayo_settings' => [
                'bmlt_root_server' => 'https://bmlt.example.com'
            ],
            'mayo_external_sources' => [
                ['id' => 'source1', 'url' => 'https://external.example.com', 'name' => 'External Site']
            ]
        ]);
    }

    /**
     * Test init registers all hooks
     */
    public function testInitRegistersHooks(): void {
        $actionsAdded = [];
        $filtersAdded = [];

        Functions\when('add_action')->alias(function($tag, $callback, $priority = 10) use (&$actionsAdded) {
            $actionsAdded[] = $tag;
        });
        Functions\when('add_filter')->alias(function($tag, $callback, $priority = 10) use (&$filtersAdded) {
            $filtersAdded[] = $tag;
        });

        Announcement::init();

        $this->assertContains('init', $actionsAdded);
        $this->assertContains('restrict_manage_posts', $actionsAdded);
        $this->assertContains('rest_after_insert_mayo_announcement', $actionsAdded);
        $this->assertContains('transition_post_status', $actionsAdded);

        $this->assertContains('manage_mayo_announcement_posts_columns', $filtersAdded);
        $this->assertContains('manage_edit-mayo_announcement_sortable_columns', $filtersAdded);
        $this->assertContains('posts_orderby', $filtersAdded);
        $this->assertContains('pre_get_posts', $filtersAdded);
    }

    /**
     * Test register_post_type registers mayo_announcement CPT
     */
    public function testRegisterPostTypeRegistersCPT(): void {
        $registeredTypes = [];

        Functions\when('register_post_type')->alias(function($post_type, $args) use (&$registeredTypes) {
            $registeredTypes[$post_type] = $args;
        });

        Announcement::register_post_type();

        $this->assertArrayHasKey('mayo_announcement', $registeredTypes);
        $this->assertTrue($registeredTypes['mayo_announcement']['public']);
        $this->assertTrue($registeredTypes['mayo_announcement']['show_in_rest']);
        $this->assertContains('title', $registeredTypes['mayo_announcement']['supports']);
        $this->assertContains('editor', $registeredTypes['mayo_announcement']['supports']);
    }

    /**
     * Test register_meta_fields registers all expected meta
     */
    public function testRegisterMetaFieldsRegistersAllExpected(): void {
        $registeredMeta = [];

        Functions\when('register_post_meta')->alias(function($post_type, $meta_key, $args) use (&$registeredMeta) {
            if (!isset($registeredMeta[$post_type])) {
                $registeredMeta[$post_type] = [];
            }
            $registeredMeta[$post_type][] = $meta_key;
        });

        Announcement::register_meta_fields();

        $this->assertArrayHasKey('mayo_announcement', $registeredMeta);
        $this->assertContains('display_start_date', $registeredMeta['mayo_announcement']);
        $this->assertContains('display_end_date', $registeredMeta['mayo_announcement']);
        $this->assertContains('display_start_time', $registeredMeta['mayo_announcement']);
        $this->assertContains('display_end_time', $registeredMeta['mayo_announcement']);
        $this->assertContains('priority', $registeredMeta['mayo_announcement']);
        $this->assertContains('linked_events', $registeredMeta['mayo_announcement']);
        $this->assertContains('linked_event_refs', $registeredMeta['mayo_announcement']);
        $this->assertContains('service_body', $registeredMeta['mayo_announcement']);
        $this->assertContains('notification_settings', $registeredMeta['mayo_announcement']);
    }

    /**
     * Test set_custom_columns returns expected columns
     */
    public function testSetCustomColumnsReturnsExpectedColumns(): void {
        $inputColumns = [
            'cb' => '<input type="checkbox">',
            'title' => 'Title',
            'date' => 'Date'
        ];

        $result = Announcement::set_custom_columns($inputColumns);

        $this->assertArrayHasKey('cb', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('priority', $result);
        $this->assertArrayHasKey('service_body', $result);
        $this->assertArrayHasKey('display_window', $result);
        $this->assertArrayHasKey('status_indicator', $result);
        $this->assertArrayHasKey('linked_events', $result);
        $this->assertArrayHasKey('date', $result);
    }

    /**
     * Test set_sortable_columns adds expected sortable columns
     */
    public function testSetSortableColumnsAddsSortableColumns(): void {
        $inputColumns = [];

        $result = Announcement::set_sortable_columns($inputColumns);

        $this->assertArrayHasKey('priority', $result);
        $this->assertArrayHasKey('display_window', $result);
        $this->assertArrayHasKey('status_indicator', $result);
    }

    /**
     * Test render_custom_columns outputs priority
     */
    public function testRenderCustomColumnsOutputsPriority(): void {
        $this->setPostMeta(100, [
            'priority' => 'high'
        ]);

        ob_start();
        Announcement::render_custom_columns('priority', 100);
        $output = ob_get_clean();

        $this->assertStringContainsString('High', $output);
        $this->assertStringContainsString('ff9800', $output);
    }

    /**
     * Test render_custom_columns outputs urgent priority
     */
    public function testRenderCustomColumnsOutputsUrgentPriority(): void {
        $this->setPostMeta(101, [
            'priority' => 'urgent'
        ]);

        ob_start();
        Announcement::render_custom_columns('priority', 101);
        $output = ob_get_clean();

        $this->assertStringContainsString('Urgent', $output);
        $this->assertStringContainsString('dc3545', $output);
    }

    /**
     * Test render_custom_columns outputs default priority
     */
    public function testRenderCustomColumnsOutputsDefaultPriority(): void {
        $this->setPostMeta(102, []);

        ob_start();
        Announcement::render_custom_columns('priority', 102);
        $output = ob_get_clean();

        $this->assertStringContainsString('Normal', $output);
    }

    /**
     * Test render_custom_columns outputs service body unaffiliated
     */
    public function testRenderCustomColumnsOutputsServiceBodyUnaffiliated(): void {
        $this->setPostMeta(103, [
            'service_body' => '0'
        ]);

        ob_start();
        Announcement::render_custom_columns('service_body', 103);
        $output = ob_get_clean();

        $this->assertStringContainsString('Unaffiliated', $output);
    }

    /**
     * Test render_custom_columns outputs empty service body
     */
    public function testRenderCustomColumnsOutputsEmptyServiceBody(): void {
        $this->setPostMeta(104, [
            'service_body' => ''
        ]);

        ob_start();
        Announcement::render_custom_columns('service_body', 104);
        $output = ob_get_clean();

        $this->assertStringContainsString('—', $output);
    }

    /**
     * Test render_custom_columns outputs display window
     */
    public function testRenderCustomColumnsOutputsDisplayWindow(): void {
        $this->setPostMeta(105, [
            'display_start_date' => '2025-01-15',
            'display_start_time' => '09:00',
            'display_end_date' => '2025-01-31',
            'display_end_time' => '17:00'
        ]);

        Functions\when('date_i18n')->alias(function($format, $timestamp) {
            return date($format, $timestamp);
        });

        ob_start();
        Announcement::render_custom_columns('display_window', 105);
        $output = ob_get_clean();

        $this->assertStringContainsString('Jan', $output);
    }

    /**
     * Test render_custom_columns outputs always visible when no dates
     */
    public function testRenderCustomColumnsOutputsAlwaysVisible(): void {
        $this->setPostMeta(106, [
            'display_start_date' => '',
            'display_end_date' => ''
        ]);

        ob_start();
        Announcement::render_custom_columns('display_window', 106);
        $output = ob_get_clean();

        $this->assertStringContainsString('Always visible', $output);
    }

    /**
     * Test render_custom_columns outputs status active
     */
    public function testRenderCustomColumnsOutputsStatusActive(): void {
        $this->setPostMeta(107, [
            'display_start_date' => date('Y-m-d', strtotime('-1 day')),
            'display_end_date' => date('Y-m-d', strtotime('+1 day'))
        ]);

        ob_start();
        Announcement::render_custom_columns('status_indicator', 107);
        $output = ob_get_clean();

        $this->assertStringContainsString('Active', $output);
        $this->assertStringContainsString('46b450', $output);
    }

    /**
     * Test render_custom_columns outputs status scheduled
     */
    public function testRenderCustomColumnsOutputsStatusScheduled(): void {
        $this->setPostMeta(108, [
            'display_start_date' => date('Y-m-d', strtotime('+5 days')),
            'display_end_date' => date('Y-m-d', strtotime('+10 days'))
        ]);

        ob_start();
        Announcement::render_custom_columns('status_indicator', 108);
        $output = ob_get_clean();

        $this->assertStringContainsString('Scheduled', $output);
        $this->assertStringContainsString('0073aa', $output);
    }

    /**
     * Test render_custom_columns outputs status expired
     */
    public function testRenderCustomColumnsOutputsStatusExpired(): void {
        $this->setPostMeta(109, [
            'display_start_date' => date('Y-m-d', strtotime('-10 days')),
            'display_end_date' => date('Y-m-d', strtotime('-5 days'))
        ]);

        ob_start();
        Announcement::render_custom_columns('status_indicator', 109);
        $output = ob_get_clean();

        $this->assertStringContainsString('Expired', $output);
        $this->assertStringContainsString('dc3545', $output);
    }

    /**
     * Test render_custom_columns outputs no linked events
     */
    public function testRenderCustomColumnsOutputsNoLinkedEvents(): void {
        $this->setPostMeta(110, [
            'linked_event_refs' => [],
            'linked_events' => []
        ]);

        ob_start();
        Announcement::render_custom_columns('linked_events', 110);
        $output = ob_get_clean();

        $this->assertStringContainsString('—', $output);
    }

    /**
     * Test add_announcement_status_filter outputs select for mayo_announcement
     */
    public function testAddAnnouncementStatusFilterOutputsSelect(): void {
        ob_start();
        Announcement::add_announcement_status_filter('mayo_announcement');
        $output = ob_get_clean();

        $this->assertStringContainsString('<select name="announcement_status">', $output);
        $this->assertStringContainsString('Active', $output);
        $this->assertStringContainsString('Scheduled', $output);
        $this->assertStringContainsString('Expired', $output);
    }

    /**
     * Test add_announcement_status_filter returns early for non-mayo_announcement
     */
    public function testAddAnnouncementStatusFilterReturnsEarlyForNonMayoAnnouncement(): void {
        ob_start();
        Announcement::add_announcement_status_filter('post');
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    /**
     * Test get_linked_event_refs returns new format
     */
    public function testGetLinkedEventRefsReturnsNewFormat(): void {
        $this->setPostMeta(111, [
            'linked_event_refs' => [
                ['type' => 'local', 'id' => 1],
                ['type' => 'external', 'id' => 2, 'source_id' => 'source1']
            ]
        ]);

        $result = Announcement::get_linked_event_refs(111);

        $this->assertCount(2, $result);
        $this->assertEquals('local', $result[0]['type']);
        $this->assertEquals('external', $result[1]['type']);
    }

    /**
     * Test get_linked_event_refs falls back to old format
     */
    public function testGetLinkedEventRefsFallsBackToOldFormat(): void {
        $this->setPostMeta(112, [
            'linked_event_refs' => [],
            'linked_events' => [10, 20, 30]
        ]);

        $result = Announcement::get_linked_event_refs(112);

        $this->assertCount(3, $result);
        $this->assertEquals('local', $result[0]['type']);
        $this->assertEquals(10, $result[0]['id']);
    }

    /**
     * Test get_linked_event_refs returns empty for no refs
     */
    public function testGetLinkedEventRefsReturnsEmptyForNoRefs(): void {
        $this->setPostMeta(113, []);

        $result = Announcement::get_linked_event_refs(113);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test get_external_source returns source if found
     */
    public function testGetExternalSourceReturnsSourceIfFound(): void {
        $result = Announcement::get_external_source('source1');

        $this->assertNotNull($result);
        $this->assertEquals('source1', $result['id']);
        $this->assertEquals('External Site', $result['name']);
    }

    /**
     * Test get_external_source returns null if not found
     */
    public function testGetExternalSourceReturnsNullIfNotFound(): void {
        $result = Announcement::get_external_source('nonexistent');

        $this->assertNull($result);
    }

    /**
     * Test resolve_event_ref returns null for invalid ref
     */
    public function testResolveEventRefReturnsNullForInvalidRef(): void {
        $this->assertNull(Announcement::resolve_event_ref(null));
        $this->assertNull(Announcement::resolve_event_ref('string'));
        $this->assertNull(Announcement::resolve_event_ref(['no_type' => true]));
    }

    /**
     * Test resolve_event_ref handles custom links
     */
    public function testResolveEventRefHandlesCustomLinks(): void {
        $ref = [
            'type' => 'custom',
            'url' => 'https://example.com/info',
            'title' => 'More Information',
            'icon' => 'info'
        ];

        $result = Announcement::resolve_event_ref($ref);

        $this->assertNotNull($result);
        $this->assertEquals('More Information', $result['title']);
        $this->assertEquals('https://example.com/info', $result['permalink']);
        $this->assertEquals('info', $result['icon']);
        $this->assertEquals('custom', $result['source']['type']);
    }

    /**
     * Test resolve_event_ref returns null for custom link without url
     */
    public function testResolveEventRefReturnsNullForCustomLinkWithoutUrl(): void {
        $ref = [
            'type' => 'custom',
            'title' => 'Missing URL'
        ];

        $result = Announcement::resolve_event_ref($ref);

        $this->assertNull($result);
    }

    /**
     * Test resolve_event_ref returns null for custom link without title
     */
    public function testResolveEventRefReturnsNullForCustomLinkWithoutTitle(): void {
        $ref = [
            'type' => 'custom',
            'url' => 'https://example.com'
        ];

        $result = Announcement::resolve_event_ref($ref);

        $this->assertNull($result);
    }

    /**
     * Test resolve_event_ref handles local events
     */
    public function testResolveEventRefHandlesLocalEvents(): void {
        $event = $this->createMockPost([
            'ID' => 200,
            'post_title' => 'Test Event',
            'post_name' => 'test-event',
            'post_type' => 'mayo_event'
        ]);

        $this->setPostMeta(200, [
            'event_start_date' => '2025-01-15',
            'event_end_date' => '2025-01-15',
            'event_start_time' => '10:00',
            'event_end_time' => '12:00',
            'location_name' => 'Test Location',
            'location_address' => '123 Main St'
        ]);

        Functions\when('get_post')->justReturn($event);

        $ref = ['type' => 'local', 'id' => 200];
        $result = Announcement::resolve_event_ref($ref);

        $this->assertNotNull($result);
        $this->assertEquals(200, $result['id']);
        $this->assertEquals('Test Event', $result['title']);
        $this->assertEquals('test-event', $result['slug']);
        $this->assertEquals('2025-01-15', $result['start_date']);
        $this->assertEquals('Test Location', $result['location_name']);
        $this->assertEquals('local', $result['source']['type']);
    }

    /**
     * Test resolve_event_ref returns null for local event not found
     */
    public function testResolveEventRefReturnsNullForLocalEventNotFound(): void {
        Functions\when('get_post')->justReturn(null);

        $ref = ['type' => 'local', 'id' => 999];
        $result = Announcement::resolve_event_ref($ref);

        $this->assertNull($result);
    }

    /**
     * Test resolve_event_ref returns null for local event wrong type
     */
    public function testResolveEventRefReturnsNullForLocalEventWrongType(): void {
        $post = $this->createMockPost([
            'ID' => 201,
            'post_type' => 'post' // Not mayo_event
        ]);

        Functions\when('get_post')->justReturn($post);

        $ref = ['type' => 'local', 'id' => 201];
        $result = Announcement::resolve_event_ref($ref);

        $this->assertNull($result);
    }

    /**
     * Test resolve_event_ref returns null for external without source_id
     */
    public function testResolveEventRefReturnsNullForExternalWithoutSourceId(): void {
        $ref = ['type' => 'external', 'id' => 1];
        $result = Announcement::resolve_event_ref($ref);

        $this->assertNull($result);
    }

    /**
     * Test fetch_external_event returns null for invalid source
     */
    public function testFetchExternalEventReturnsNullForInvalidSource(): void {
        $result = Announcement::fetch_external_event('nonexistent', 1);

        $this->assertNull($result);
    }

    /**
     * Test fetch_external_event returns null on error
     */
    public function testFetchExternalEventReturnsNullOnError(): void {
        $this->mockWpRemoteGet([
            'event-manager/v1/events' => [
                'code' => 500,
                'body' => 'Internal Server Error'
            ]
        ]);
        $this->mockTrailingslashit();

        $result = Announcement::fetch_external_event('source1', 1);

        $this->assertNull($result);
    }

    /**
     * Test fetch_external_event returns event data on success
     */
    public function testFetchExternalEventReturnsEventDataOnSuccess(): void {
        $this->mockWpRemoteGet([
            'event-manager/v1/events' => [
                'code' => 200,
                'body' => [
                    'id' => 1,
                    'title' => 'External Event',
                    'link' => 'https://external.example.com/event/1'
                ]
            ]
        ]);
        $this->mockTrailingslashit();

        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);

        $result = Announcement::fetch_external_event('source1', 1);

        $this->assertNotNull($result);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('External Event', $result['title']);
        $this->assertEquals('external', $result['source']['type']);
        $this->assertEquals('source1', $result['source']['id']);
    }

    /**
     * Test build_external_event_permalink returns permalink
     */
    public function testBuildExternalEventPermalinkReturnsPermalink(): void {
        $source = ['url' => 'https://external.example.com'];
        $this->mockTrailingslashit();

        $result = Announcement::build_external_event_permalink($source, 'my-event');

        $this->assertStringContainsString('external.example.com', $result);
        $this->assertStringContainsString('my-event', $result);
    }

    /**
     * Test build_external_event_permalink returns hash for empty source
     */
    public function testBuildExternalEventPermalinkReturnsHashForEmptySource(): void {
        $result = Announcement::build_external_event_permalink([], 'my-event');

        $this->assertEquals('#', $result);
    }

    /**
     * Test build_external_event_permalink returns hash for empty slug
     */
    public function testBuildExternalEventPermalinkReturnsHashForEmptySlug(): void {
        $source = ['url' => 'https://external.example.com'];

        $result = Announcement::build_external_event_permalink($source, '');

        $this->assertEquals('#', $result);
    }

    /**
     * Test handle_post_status_transition ignores non-mayo_announcement
     */
    public function testHandlePostStatusTransitionIgnoresNonMayoAnnouncement(): void {
        $post = $this->createMockPost([
            'ID' => 300,
            'post_type' => 'post'
        ]);

        // Should not throw or call Subscriber::send_announcement_email
        Announcement::handle_post_status_transition('publish', 'pending', $post);

        // If we get here without error, the test passes
        $this->assertTrue(true);
    }

    /**
     * Test handle_post_status_transition ignores non-publish transitions
     */
    public function testHandlePostStatusTransitionIgnoresNonPublishTransitions(): void {
        $post = $this->createMockPost([
            'ID' => 301,
            'post_type' => 'mayo_announcement'
        ]);

        // draft to pending should not trigger email
        Announcement::handle_post_status_transition('pending', 'draft', $post);

        $this->assertTrue(true);
    }

    /**
     * Test render_custom_columns outputs service body from BMLT
     */
    public function testRenderCustomColumnsOutputsServiceBodyFromBmlt(): void {
        $this->setPostMeta(114, [
            'service_body' => '5'
        ]);

        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [['id' => '5', 'name' => 'Test Area']]
            ]
        ]);

        ob_start();
        Announcement::render_custom_columns('service_body', 114);
        $output = ob_get_clean();

        $this->assertStringContainsString('Test Area', $output);
    }

    /**
     * Test render_custom_columns outputs service body fallback
     */
    public function testRenderCustomColumnsOutputsServiceBodyFallback(): void {
        $this->setPostMeta(115, [
            'service_body' => '999'
        ]);

        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [] // No matching service body
            ]
        ]);

        ob_start();
        Announcement::render_custom_columns('service_body', 115);
        $output = ob_get_clean();

        $this->assertStringContainsString('Service Body (999)', $output);
    }

    /**
     * Test render_custom_columns linked_events with local event
     */
    public function testRenderCustomColumnsLinkedEventsWithLocalEvent(): void {
        $this->setPostMeta(116, [
            'linked_event_refs' => [
                ['type' => 'local', 'id' => 500]
            ]
        ]);

        $event = $this->createMockPost([
            'ID' => 500,
            'post_title' => 'Local Test Event',
            'post_type' => 'mayo_event'
        ]);

        Functions\when('get_post')->justReturn($event);

        ob_start();
        Announcement::render_custom_columns('linked_events', 116);
        $output = ob_get_clean();

        $this->assertStringContainsString('Local Test Event', $output);
    }

    /**
     * Test render_custom_columns linked_events with external event
     */
    public function testRenderCustomColumnsLinkedEventsWithExternalEvent(): void {
        $this->setPostMeta(117, [
            'linked_event_refs' => [
                ['type' => 'external', 'id' => 1, 'source_id' => 'source1']
            ]
        ]);

        // get_post returns null for external events
        Functions\when('get_post')->justReturn(null);

        ob_start();
        Announcement::render_custom_columns('linked_events', 117);
        $output = ob_get_clean();

        $this->assertStringContainsString('External Site', $output);
    }

    /**
     * Test handle_custom_orderby returns unchanged for non-admin
     */
    public function testHandleCustomOrderbyReturnsUnchangedForNonAdmin(): void {
        Functions\when('is_admin')->justReturn(false);

        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(true);

        $result = Announcement::handle_custom_orderby('original_orderby', $mockQuery);

        $this->assertEquals('original_orderby', $result);
    }

    /**
     * Test handle_custom_orderby returns unchanged for non-main query
     */
    public function testHandleCustomOrderbyReturnsUnchangedForNonMainQuery(): void {
        Functions\when('is_admin')->justReturn(true);

        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(false);

        $result = Announcement::handle_custom_orderby('original_orderby', $mockQuery);

        $this->assertEquals('original_orderby', $result);
    }

    /**
     * Test handle_custom_orderby for priority
     */
    public function testHandleCustomOrderbyForPriority(): void {
        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->postmeta = 'wp_postmeta';
        $wpdb->posts = 'wp_posts';

        Functions\when('is_admin')->justReturn(true);

        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(true);
        $mockQuery->shouldReceive('get')->with('orderby')->andReturn('priority');
        $mockQuery->shouldReceive('get')->with('order', 'ASC')->andReturn('ASC');

        $result = Announcement::handle_custom_orderby('original_orderby', $mockQuery);

        $this->assertStringContainsString('FIELD', $result);
        $this->assertStringContainsString('priority', $result);
        $this->assertStringContainsString('urgent', $result);
        $this->assertStringContainsString('high', $result);
        $this->assertStringContainsString('normal', $result);
        $this->assertStringContainsString('low', $result);
    }

    /**
     * Test handle_custom_orderby for display_start_date
     */
    public function testHandleCustomOrderbyForDisplayStartDate(): void {
        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->postmeta = 'wp_postmeta';
        $wpdb->posts = 'wp_posts';

        Functions\when('is_admin')->justReturn(true);

        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(true);
        $mockQuery->shouldReceive('get')->with('orderby')->andReturn('display_start_date');
        $mockQuery->shouldReceive('get')->with('order', 'ASC')->andReturn('DESC');

        $result = Announcement::handle_custom_orderby('original_orderby', $mockQuery);

        $this->assertStringContainsString('display_start_date', $result);
        $this->assertStringContainsString('DESC', $result);
    }

    /**
     * Test filter_announcements_by_status returns for non-admin
     */
    public function testFilterAnnouncementsByStatusReturnsForNonAdmin(): void {
        global $pagenow;
        $pagenow = 'edit.php';

        Functions\when('is_admin')->justReturn(false);

        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(true);

        Announcement::filter_announcements_by_status($mockQuery);

        $this->assertTrue(true);
    }

    /**
     * Test filter_announcements_by_status returns for non-edit.php
     */
    public function testFilterAnnouncementsByStatusReturnsForNonEditPage(): void {
        global $pagenow;
        $pagenow = 'post.php';

        Functions\when('is_admin')->justReturn(true);

        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(true);

        Announcement::filter_announcements_by_status($mockQuery);

        $this->assertTrue(true);
    }

    /**
     * Test filter_announcements_by_status returns for non-main query
     */
    public function testFilterAnnouncementsByStatusReturnsForNonMainQuery(): void {
        global $pagenow;
        $pagenow = 'edit.php';

        Functions\when('is_admin')->justReturn(true);

        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(false);

        Announcement::filter_announcements_by_status($mockQuery);

        $this->assertTrue(true);
    }

    /**
     * Test filter_announcements_by_status returns for non-mayo_announcement
     */
    public function testFilterAnnouncementsByStatusReturnsForNonMayoAnnouncement(): void {
        global $pagenow;
        $pagenow = 'edit.php';
        $_GET['post_type'] = 'post';

        Functions\when('is_admin')->justReturn(true);

        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(true);

        Announcement::filter_announcements_by_status($mockQuery);

        $this->assertTrue(true);

        unset($_GET['post_type']);
    }

    /**
     * Test filter_announcements_by_status returns when no filter selected
     */
    public function testFilterAnnouncementsByStatusReturnsWhenNoFilter(): void {
        global $pagenow;
        $pagenow = 'edit.php';
        $_GET['post_type'] = 'mayo_announcement';

        Functions\when('is_admin')->justReturn(true);

        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(true);

        Announcement::filter_announcements_by_status($mockQuery);

        $this->assertTrue(true);

        unset($_GET['post_type']);
    }

    /**
     * Test filter_announcements_by_status with active filter
     */
    public function testFilterAnnouncementsByStatusWithActiveFilter(): void {
        global $pagenow;
        $pagenow = 'edit.php';
        $_GET['post_type'] = 'mayo_announcement';
        $_GET['announcement_status'] = 'active';

        Functions\when('is_admin')->justReturn(true);

        $metaQuerySet = null;
        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(true);
        $mockQuery->shouldReceive('get')->with('meta_query')->andReturn([]);
        $mockQuery->shouldReceive('set')->with('meta_query', \Mockery::any())->andReturnUsing(function($key, $value) use (&$metaQuerySet) {
            $metaQuerySet = $value;
        });

        Announcement::filter_announcements_by_status($mockQuery);

        $this->assertIsArray($metaQuerySet);
        $this->assertEquals('AND', $metaQuerySet['relation']);

        unset($_GET['post_type'], $_GET['announcement_status']);
    }

    /**
     * Test filter_announcements_by_status with scheduled filter
     */
    public function testFilterAnnouncementsByStatusWithScheduledFilter(): void {
        global $pagenow;
        $pagenow = 'edit.php';
        $_GET['post_type'] = 'mayo_announcement';
        $_GET['announcement_status'] = 'scheduled';

        Functions\when('is_admin')->justReturn(true);

        $metaQuerySet = null;
        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(true);
        $mockQuery->shouldReceive('get')->with('meta_query')->andReturn([]);
        $mockQuery->shouldReceive('set')->with('meta_query', \Mockery::any())->andReturnUsing(function($key, $value) use (&$metaQuerySet) {
            $metaQuerySet = $value;
        });

        Announcement::filter_announcements_by_status($mockQuery);

        $this->assertIsArray($metaQuerySet);
        $this->assertArrayHasKey('key', $metaQuerySet[0]);
        $this->assertEquals('display_start_date', $metaQuerySet[0]['key']);
        $this->assertEquals('>', $metaQuerySet[0]['compare']);

        unset($_GET['post_type'], $_GET['announcement_status']);
    }

    /**
     * Test filter_announcements_by_status with expired filter
     */
    public function testFilterAnnouncementsByStatusWithExpiredFilter(): void {
        global $pagenow;
        $pagenow = 'edit.php';
        $_GET['post_type'] = 'mayo_announcement';
        $_GET['announcement_status'] = 'expired';

        Functions\when('is_admin')->justReturn(true);

        $metaQuerySet = null;
        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(true);
        $mockQuery->shouldReceive('get')->with('meta_query')->andReturn([]);
        $mockQuery->shouldReceive('set')->with('meta_query', \Mockery::any())->andReturnUsing(function($key, $value) use (&$metaQuerySet) {
            $metaQuerySet = $value;
        });

        Announcement::filter_announcements_by_status($mockQuery);

        $this->assertIsArray($metaQuerySet);

        unset($_GET['post_type'], $_GET['announcement_status']);
    }

    /**
     * Test add_announcement_status_filter with selected value
     */
    public function testAddAnnouncementStatusFilterWithSelectedValue(): void {
        $_GET['announcement_status'] = 'active';

        ob_start();
        Announcement::add_announcement_status_filter('mayo_announcement');
        $output = ob_get_clean();

        $this->assertStringContainsString('selected="selected"', $output);

        unset($_GET['announcement_status']);
    }

    /**
     * Test render_custom_columns display_window with only start date
     */
    public function testRenderCustomColumnsDisplayWindowWithOnlyStartDate(): void {
        $this->setPostMeta(118, [
            'display_start_date' => '2025-01-15',
            'display_start_time' => '',
            'display_end_date' => '',
            'display_end_time' => ''
        ]);

        Functions\when('date_i18n')->alias(function($format, $timestamp) {
            return date($format, $timestamp);
        });

        ob_start();
        Announcement::render_custom_columns('display_window', 118);
        $output = ob_get_clean();

        $this->assertStringContainsString('Jan', $output);
        $this->assertStringContainsString('Indefinite', $output);
    }

    /**
     * Test render_custom_columns display_window with only end date
     */
    public function testRenderCustomColumnsDisplayWindowWithOnlyEndDate(): void {
        $this->setPostMeta(119, [
            'display_start_date' => '',
            'display_start_time' => '',
            'display_end_date' => '2025-01-31',
            'display_end_time' => ''
        ]);

        Functions\when('date_i18n')->alias(function($format, $timestamp) {
            return date($format, $timestamp);
        });

        ob_start();
        Announcement::render_custom_columns('display_window', 119);
        $output = ob_get_clean();

        $this->assertStringContainsString('Now', $output);
        $this->assertStringContainsString('Jan', $output);
    }

    /**
     * Test render_custom_columns low priority
     */
    public function testRenderCustomColumnsOutputsLowPriority(): void {
        $this->setPostMeta(120, [
            'priority' => 'low'
        ]);

        ob_start();
        Announcement::render_custom_columns('priority', 120);
        $output = ob_get_clean();

        $this->assertStringContainsString('Low', $output);
        $this->assertStringContainsString('6c757d', $output);
    }

    /**
     * Test handle_rest_insert stores previous status when already published
     */
    public function testHandleRestInsertStoresPreviousStatusWhenAlreadyPublished(): void {
        $post = $this->createMockPost([
            'ID' => 401,
            'post_status' => 'publish',
            'post_type' => 'mayo_announcement'
        ]);

        $this->setPostMeta(401, [
            '_mayo_previous_status' => 'publish'
        ]);

        $updatedMeta = [];
        Functions\when('update_post_meta')->alias(function($post_id, $key, $value) use (&$updatedMeta) {
            $updatedMeta[$key] = $value;
            return true;
        });

        Announcement::handle_rest_insert($post, null, false);

        // Should store current status for next comparison
        $this->assertEquals('publish', $updatedMeta['_mayo_previous_status']);
        // Should not set email sent flag since it was already published
        $this->assertArrayNotHasKey('_mayo_email_sent_via_rest', $updatedMeta);
    }

    /**
     * Test handle_rest_insert does not send email when not publishing
     */
    public function testHandleRestInsertDoesNotSendEmailWhenNotPublishing(): void {
        $post = $this->createMockPost([
            'ID' => 402,
            'post_status' => 'draft',
            'post_type' => 'mayo_announcement'
        ]);

        $this->setPostMeta(402, [
            '_mayo_previous_status' => 'draft'
        ]);

        $updatedMeta = [];
        Functions\when('update_post_meta')->alias(function($post_id, $key, $value) use (&$updatedMeta) {
            $updatedMeta[$key] = $value;
            return true;
        });

        Announcement::handle_rest_insert($post, null, false);

        // Should store current status for next comparison
        $this->assertEquals('draft', $updatedMeta['_mayo_previous_status']);
        // Should not set email sent flag since it's not being published
        $this->assertArrayNotHasKey('_mayo_email_sent_via_rest', $updatedMeta);
    }

    /**
     * Test resolve_event_ref with external event source
     */
    public function testResolveEventRefWithExternalEvent(): void {
        $this->mockWpRemoteGet([
            'event-manager/v1/events' => [
                'code' => 200,
                'body' => [
                    'id' => 1,
                    'title' => 'External Event',
                    'link' => 'https://external.example.com/event/1'
                ]
            ]
        ]);
        $this->mockTrailingslashit();
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);

        $ref = ['type' => 'external', 'id' => 1, 'source_id' => 'source1'];
        $result = Announcement::resolve_event_ref($ref);

        $this->assertNotNull($result);
        $this->assertEquals('external', $result['source']['type']);
    }

    /**
     * Test fetch_external_event returns null on WP_Error
     */
    public function testFetchExternalEventReturnsNullOnWpError(): void {
        Functions\when('wp_remote_get')->justReturn(new \WP_Error('error', 'Connection failed'));
        $this->mockTrailingslashit();

        $result = Announcement::fetch_external_event('source1', 1);

        $this->assertNull($result);
    }

    /**
     * Test fetch_external_event returns null on non-200 response
     */
    public function testFetchExternalEventReturnsNullOnNon200Response(): void {
        $this->mockWpRemoteGet([
            'event-manager/v1/events' => [
                'code' => 404,
                'body' => 'Not Found'
            ]
        ]);
        $this->mockTrailingslashit();
        Functions\when('wp_remote_retrieve_response_code')->justReturn(404);

        $result = Announcement::fetch_external_event('source1', 1);

        $this->assertNull($result);
    }

    /**
     * Test fetch_external_event returns null on invalid JSON
     */
    public function testFetchExternalEventReturnsNullOnInvalidJson(): void {
        Functions\when('wp_remote_get')->justReturn(['body' => 'not json']);
        Functions\when('wp_remote_retrieve_body')->justReturn('not json');
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        $this->mockTrailingslashit();

        $result = Announcement::fetch_external_event('source1', 1);

        $this->assertNull($result);
    }

    /**
     * Test resolve_event_ref returns null for local/external without id
     */
    public function testResolveEventRefReturnsNullWithoutId(): void {
        $ref = ['type' => 'local']; // No id
        $this->assertNull(Announcement::resolve_event_ref($ref));

        $ref2 = ['type' => 'external', 'source_id' => 'source1']; // No id
        $this->assertNull(Announcement::resolve_event_ref($ref2));
    }
}
