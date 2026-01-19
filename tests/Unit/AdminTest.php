<?php

namespace BmltEnabled\Mayo\Tests\Unit;

use BmltEnabled\Mayo\Admin;
use Brain\Monkey\Functions;

class AdminTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        $this->mockPostMeta();
        $this->mockWpMail();
        $this->mockGetOption([
            'mayo_settings' => [
                'bmlt_root_server' => 'https://bmlt.example.com'
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

        Admin::init();

        // Verify key actions are registered
        $this->assertContains('init', $actionsAdded);
        $this->assertContains('admin_menu', $actionsAdded);
        $this->assertContains('admin_enqueue_scripts', $actionsAdded);
        $this->assertContains('restrict_manage_posts', $actionsAdded);
        $this->assertContains('wp_ajax_copy_event', $actionsAdded);
        $this->assertContains('transition_post_status', $actionsAdded);

        // Verify key filters are registered
        $this->assertContains('manage_mayo_event_posts_columns', $filtersAdded);
        $this->assertContains('manage_edit-mayo_event_sortable_columns', $filtersAdded);
        $this->assertContains('posts_orderby', $filtersAdded);
        $this->assertContains('post_row_actions', $filtersAdded);
        $this->assertContains('pre_get_posts', $filtersAdded);
    }

    /**
     * Test register_post_type registers mayo_event CPT
     */
    public function testRegisterPostTypeRegistersCPT(): void {
        $registeredTypes = [];

        Functions\when('register_post_type')->alias(function($post_type, $args) use (&$registeredTypes) {
            $registeredTypes[$post_type] = $args;
        });

        Admin::register_post_type();

        $this->assertArrayHasKey('mayo_event', $registeredTypes);
        $this->assertTrue($registeredTypes['mayo_event']['public']);
        $this->assertTrue($registeredTypes['mayo_event']['show_in_rest']);
        $this->assertContains('title', $registeredTypes['mayo_event']['supports']);
        $this->assertContains('editor', $registeredTypes['mayo_event']['supports']);
    }

    /**
     * Test add_menu adds all menu pages
     */
    public function testAddMenuAddsAllPages(): void {
        $menuPages = [];
        $subMenuPages = [];

        Functions\when('add_menu_page')->alias(function($page_title, $menu_title, $capability, $menu_slug) use (&$menuPages) {
            $menuPages[] = $menu_slug;
        });
        Functions\when('add_submenu_page')->alias(function($parent_slug, $page_title, $menu_title, $capability, $menu_slug) use (&$subMenuPages) {
            $subMenuPages[] = $menu_slug;
        });

        Admin::add_menu();

        $this->assertContains('mayo-events', $menuPages);
        $this->assertContains('mayo-subscribers', $subMenuPages);
        $this->assertContains('mayo-shortcodes', $subMenuPages);
        $this->assertContains('mayo-settings', $subMenuPages);
        $this->assertContains('mayo-css-classes', $subMenuPages);
        $this->assertContains('mayo-api-docs', $subMenuPages);
    }

    /**
     * Test render_admin_page outputs div
     */
    public function testRenderAdminPageOutputsDiv(): void {
        ob_start();
        Admin::render_admin_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('<div id="mayo-admin">', $output);
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

        $result = Admin::set_custom_columns($inputColumns);

        $this->assertArrayHasKey('cb', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('event_type', $result);
        $this->assertArrayHasKey('event_datetime', $result);
        $this->assertArrayHasKey('attachments', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('service_body', $result);
        $this->assertArrayHasKey('date', $result);
    }

    /**
     * Test set_sortable_columns adds expected sortable columns
     */
    public function testSetSortableColumnsAddsSortableColumns(): void {
        $inputColumns = [];

        $result = Admin::set_sortable_columns($inputColumns);

        $this->assertArrayHasKey('event_type', $result);
        $this->assertArrayHasKey('event_datetime', $result);
        $this->assertArrayHasKey('service_body', $result);
        $this->assertArrayHasKey('status', $result);

        $this->assertEquals('event_type', $result['event_type']);
        $this->assertEquals('event_start_date', $result['event_datetime']);
        $this->assertEquals('service_body', $result['service_body']);
        $this->assertEquals('post_status', $result['status']);
    }

    /**
     * Test handle_event_status_transition ignores non-mayo_event posts
     */
    public function testHandleEventStatusTransitionIgnoresNonMayoEventPosts(): void {
        $post = $this->createMockPost([
            'ID' => 1,
            'post_type' => 'post'
        ]);

        // Should not throw any errors
        Admin::handle_event_status_transition('publish', 'pending', $post);

        // No email should be sent
        $emails = $this->getCapturedEmails();
        $this->assertEmpty($emails);
    }

    /**
     * Test handle_event_status_transition ignores non-pending to publish transitions
     */
    public function testHandleEventStatusTransitionIgnoresNonPendingToPublish(): void {
        $post = $this->createMockPost([
            'ID' => 100,
            'post_type' => 'mayo_event'
        ]);

        // draft to publish should not trigger email
        Admin::handle_event_status_transition('publish', 'draft', $post);

        $emails = $this->getCapturedEmails();
        $this->assertEmpty($emails);
    }

    /**
     * Test handle_event_status_transition sends email when pending to publish
     */
    public function testHandleEventStatusTransitionSendsEmailWhenPendingToPublish(): void {
        $post = $this->createMockPost([
            'ID' => 200,
            'post_type' => 'mayo_event',
            'post_title' => 'Test Event',
            'post_content' => 'Event description'
        ]);

        $this->setPostMeta(200, [
            'email' => 'submitter@example.com',
            'contact_name' => 'John Doe',
            'event_type' => 'Meeting',
            'event_start_date' => '2025-01-15',
            'event_end_date' => '2025-01-15',
            'event_start_time' => '10:00',
            'event_end_time' => '12:00',
            'timezone' => 'America/New_York',
            'service_body' => '1',
            'location_name' => 'Test Location',
            'location_address' => '123 Main St'
        ]);

        Functions\when('get_the_title')->justReturn('Test Event');
        Functions\when('wp_get_post_categories')->justReturn([]);
        Functions\when('wp_get_post_tags')->justReturn([]);
        Functions\when('get_bloginfo')->justReturn('Test Site');

        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [['id' => '1', 'name' => 'Test Region']]
            ]
        ]);
        $this->mockTrailingslashit();

        Admin::handle_event_status_transition('publish', 'pending', $post);

        $emails = $this->getCapturedEmails();
        $this->assertNotEmpty($emails);
        $this->assertEquals('submitter@example.com', $emails[0]['to']);
        $this->assertStringContainsString('Test Event', $emails[0]['subject']);
    }

    /**
     * Test handle_event_status_transition does not send email without valid submitter email
     */
    public function testHandleEventStatusTransitionDoesNotSendWithoutValidEmail(): void {
        $post = $this->createMockPost([
            'ID' => 300,
            'post_type' => 'mayo_event'
        ]);

        // No email in post meta
        $this->setPostMeta(300, [
            'contact_name' => 'John Doe'
            // no email
        ]);

        Admin::handle_event_status_transition('publish', 'pending', $post);

        $emails = $this->getCapturedEmails();
        $this->assertEmpty($emails);
    }

    /**
     * Test render_custom_columns outputs event type
     */
    public function testRenderCustomColumnsOutputsEventType(): void {
        $this->setPostMeta(400, [
            'event_type' => 'Service'
        ]);

        ob_start();
        Admin::render_custom_columns('event_type', 400);
        $output = ob_get_clean();

        $this->assertStringContainsString('Service', $output);
    }

    /**
     * Test render_custom_columns outputs service body
     */
    public function testRenderCustomColumnsOutputsServiceBodyUnaffiliated(): void {
        $this->setPostMeta(401, [
            'service_body' => '0'
        ]);

        ob_start();
        Admin::render_custom_columns('service_body', 401);
        $output = ob_get_clean();

        $this->assertStringContainsString('Unaffiliated', $output);
    }

    /**
     * Test render_custom_columns outputs service body fallback
     */
    public function testRenderCustomColumnsOutputsServiceBodyFallback(): void {
        $this->setPostMeta(402, [
            'service_body' => '999'
        ]);

        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => []
            ]
        ]);

        ob_start();
        Admin::render_custom_columns('service_body', 402);
        $output = ob_get_clean();

        $this->assertStringContainsString('999', $output);
    }

    /**
     * Test render_custom_columns outputs empty service body
     */
    public function testRenderCustomColumnsOutputsEmptyServiceBody(): void {
        $this->setPostMeta(403, [
            'service_body' => ''
        ]);

        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => []
            ]
        ]);

        ob_start();
        Admin::render_custom_columns('service_body', 403);
        $output = ob_get_clean();

        // Should output dash for empty
        $this->assertStringContainsString('—', $output);
    }

    /**
     * Test render_custom_columns outputs attachments count
     */
    public function testRenderCustomColumnsOutputsAttachments(): void {
        Functions\when('get_attached_media')->justReturn([
            (object)['ID' => 1],
            (object)['ID' => 2]
        ]);
        Functions\when('has_post_thumbnail')->justReturn(true);
        Functions\when('get_the_post_thumbnail_url')->justReturn('https://example.com/image.jpg');

        ob_start();
        Admin::render_custom_columns('attachments', 404);
        $output = ob_get_clean();

        // Should contain attachment info
        $this->assertNotEmpty($output);
    }

    /**
     * Test render_custom_columns outputs status
     */
    public function testRenderCustomColumnsOutputsStatus(): void {
        Functions\when('get_post_status')->justReturn('publish');

        ob_start();
        Admin::render_custom_columns('status', 405);
        $output = ob_get_clean();

        $this->assertStringContainsString('publish', strtolower($output));
    }

    /**
     * Test enqueue_scripts returns early for non-mayo pages
     */
    public function testEnqueueScriptsReturnsEarlyForNonMayoPages(): void {
        global $post_type;
        $post_type = 'post';

        $enqueuedScripts = [];
        Functions\when('wp_register_script')->alias(function($handle) use (&$enqueuedScripts) {
            $enqueuedScripts[] = $handle;
        });
        Functions\when('wp_enqueue_script')->justReturn(true);
        Functions\when('wp_enqueue_style')->justReturn(true);
        Functions\when('wp_add_inline_script')->justReturn(true);
        Functions\when('wp_localize_script')->justReturn(true);

        Admin::enqueue_scripts('some-random-page');

        // Should not enqueue mayo-admin
        $this->assertNotContains('mayo-admin', $enqueuedScripts);
    }

    /**
     * Test enqueue_scripts enqueues for mayo pages
     */
    public function testEnqueueScriptsEnqueuesForMayoPages(): void {
        global $post_type;
        $post_type = 'mayo_event';

        $enqueuedScripts = [];
        Functions\when('wp_register_script')->alias(function($handle) use (&$enqueuedScripts) {
            $enqueuedScripts[] = $handle;
        });
        Functions\when('wp_enqueue_script')->justReturn(true);
        Functions\when('wp_enqueue_style')->justReturn(true);
        Functions\when('wp_add_inline_script')->justReturn(true);
        Functions\when('wp_localize_script')->justReturn(true);
        Functions\when('plugin_dir_url')->justReturn('https://example.com/wp-content/plugins/mayo/');
        Functions\when('wp_create_nonce')->justReturn('test-nonce');

        Admin::enqueue_scripts('toplevel_page_mayo-events');

        $this->assertContains('mayo-admin', $enqueuedScripts);
    }

    /**
     * Test enqueue_scripts adds inline script for edit.php mayo_event page
     */
    public function testEnqueueScriptsAddsInlineScriptForEditPage(): void {
        global $post_type;
        $post_type = 'mayo_event';
        $_GET['post_type'] = 'mayo_event';

        $inlineScripts = [];
        Functions\when('wp_register_script')->justReturn(true);
        Functions\when('wp_enqueue_script')->justReturn(true);
        Functions\when('wp_enqueue_style')->justReturn(true);
        Functions\when('wp_add_inline_script')->alias(function($handle, $script) use (&$inlineScripts) {
            $inlineScripts[$handle] = $script;
        });
        Functions\when('wp_localize_script')->justReturn(true);
        Functions\when('plugin_dir_url')->justReturn('https://example.com/wp-content/plugins/mayo/');
        Functions\when('wp_create_nonce')->justReturn('test-nonce');

        Admin::enqueue_scripts('edit.php');

        $this->assertArrayHasKey('mayo-admin', $inlineScripts);
        $this->assertStringContainsString('copy_event', $inlineScripts['mayo-admin']);

        // Cleanup
        unset($_GET['post_type']);
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

        Admin::register_meta_fields();

        $this->assertArrayHasKey('mayo_event', $registeredMeta);
        $this->assertContains('event_type', $registeredMeta['mayo_event']);
        $this->assertContains('service_body', $registeredMeta['mayo_event']);
        $this->assertContains('event_start_date', $registeredMeta['mayo_event']);
        $this->assertContains('event_end_date', $registeredMeta['mayo_event']);
        $this->assertContains('event_start_time', $registeredMeta['mayo_event']);
        $this->assertContains('event_end_time', $registeredMeta['mayo_event']);
        $this->assertContains('timezone', $registeredMeta['mayo_event']);
        $this->assertContains('location_name', $registeredMeta['mayo_event']);
        $this->assertContains('location_address', $registeredMeta['mayo_event']);
        $this->assertContains('location_details', $registeredMeta['mayo_event']);
        $this->assertContains('recurring_pattern', $registeredMeta['mayo_event']);
        $this->assertContains('skipped_occurrences', $registeredMeta['mayo_event']);
        $this->assertContains('contact_name', $registeredMeta['mayo_event']);
        $this->assertContains('email', $registeredMeta['mayo_event']);
        $this->assertContains('flyer_id', $registeredMeta['mayo_event']);
    }

    /**
     * Test render_shortcodes_page outputs div
     */
    public function testRenderShortcodesPageOutputsDiv(): void {
        ob_start();
        Admin::render_shortcodes_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('<div id="mayo-shortcode-root"', $output);
    }

    /**
     * Test render_settings_page outputs div
     */
    public function testRenderSettingsPageOutputsDiv(): void {
        ob_start();
        Admin::render_settings_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('<div id="mayo-settings-root"', $output);
    }

    /**
     * Test render_css_classes_page outputs div
     */
    public function testRenderCssClassesPageOutputsDiv(): void {
        ob_start();
        Admin::render_css_classes_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('<div id="mayo-css-classes-root"', $output);
    }

    /**
     * Test render_api_docs_page outputs div
     */
    public function testRenderApiDocsPageOutputsDiv(): void {
        ob_start();
        Admin::render_api_docs_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('<div id="mayo-api-docs-root"', $output);
    }

    /**
     * Test render_subscribers_page outputs div
     */
    public function testRenderSubscribersPageOutputsDiv(): void {
        ob_start();
        Admin::render_subscribers_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('<div id="mayo-subscribers-root"', $output);
    }

    /**
     * Test add_row_actions adds copy action for mayo_event
     */
    public function testAddRowActionsAddsCopyForMayoEvent(): void {
        $post = $this->createMockPost([
            'ID' => 500,
            'post_type' => 'mayo_event'
        ]);

        $actions = ['edit' => 'Edit', 'delete' => 'Delete'];

        $result = Admin::add_row_actions($actions, $post);

        $this->assertArrayHasKey('copy', $result);
        $this->assertStringContainsString('copy_event', $result['copy']);
    }

    /**
     * Test add_row_actions does not add copy for non-mayo_event
     */
    public function testAddRowActionsDoesNotAddCopyForNonMayoEvent(): void {
        $post = $this->createMockPost([
            'ID' => 501,
            'post_type' => 'post'
        ]);

        $actions = ['edit' => 'Edit', 'delete' => 'Delete'];

        $result = Admin::add_row_actions($actions, $post);

        $this->assertArrayNotHasKey('copy', $result);
    }

    /**
     * Test render_custom_columns outputs event_datetime with full date range
     */
    public function testRenderCustomColumnsOutputsEventDatetime(): void {
        $this->setPostMeta(410, [
            'event_start_date' => '2025-01-15',
            'event_end_date' => '2025-01-16',
            'event_start_time' => '10:00:00',
            'event_end_time' => '12:00:00',
            'timezone' => 'America/New_York',
            'recurring_pattern' => ['type' => 'none']
        ]);

        ob_start();
        Admin::render_custom_columns('event_datetime', 410);
        $output = ob_get_clean();

        $this->assertStringContainsString('Jan', $output);
        $this->assertStringContainsString('2025', $output);
    }

    /**
     * Test render_custom_columns outputs recurring indicator
     */
    public function testRenderCustomColumnsOutputsRecurringIndicator(): void {
        $this->setPostMeta(411, [
            'event_start_date' => '2025-01-15',
            'event_start_time' => '10:00:00',
            'timezone' => 'America/New_York',
            'recurring_pattern' => ['type' => 'weekly', 'interval' => 1],
            'skipped_occurrences' => ['2025-01-22', '2025-01-29']
        ]);

        ob_start();
        Admin::render_custom_columns('event_datetime', 411);
        $output = ob_get_clean();

        $this->assertStringContainsString('Recurring', $output);
        $this->assertStringContainsString('2 skipped', $output);
    }

    /**
     * Test render_custom_columns outputs service body with BMLT lookup
     */
    public function testRenderCustomColumnsOutputsServiceBodyFromBmlt(): void {
        $this->setPostMeta(412, [
            'service_body' => '5'
        ]);

        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [['id' => '5', 'name' => 'Test Area']]
            ]
        ]);

        ob_start();
        Admin::render_custom_columns('service_body', 412);
        $output = ob_get_clean();

        $this->assertStringContainsString('Test Area', $output);
    }

    /**
     * Test add_event_date_filter outputs select for mayo_event
     */
    public function testAddEventDateFilterOutputsSelect(): void {
        ob_start();
        Admin::add_event_date_filter('mayo_event');
        $output = ob_get_clean();

        $this->assertStringContainsString('<select name="event_date_filter">', $output);
        $this->assertStringContainsString('Upcoming events', $output);
        $this->assertStringContainsString('Past events', $output);
        $this->assertStringContainsString('Recurring events', $output);
    }

    /**
     * Test add_event_date_filter returns early for non-mayo_event
     */
    public function testAddEventDateFilterReturnsEarlyForNonMayoEvent(): void {
        ob_start();
        Admin::add_event_date_filter('post');
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    /**
     * Test add_event_date_filter with selected filter
     */
    public function testAddEventDateFilterWithSelectedValue(): void {
        $_GET['event_date_filter'] = 'upcoming';

        ob_start();
        Admin::add_event_date_filter('mayo_event');
        $output = ob_get_clean();

        $this->assertStringContainsString('selected="selected"', $output);

        // Cleanup
        unset($_GET['event_date_filter']);
    }

    /**
     * Test render_custom_columns outputs datetime without end date
     */
    public function testRenderCustomColumnsOutputsDatetimeWithoutEndDate(): void {
        $this->setPostMeta(413, [
            'event_start_date' => '2025-02-20',
            'event_start_time' => '14:00:00',
            'timezone' => 'UTC',
            'recurring_pattern' => ['type' => 'none']
        ]);

        ob_start();
        Admin::render_custom_columns('event_datetime', 413);
        $output = ob_get_clean();

        $this->assertStringContainsString('Feb', $output);
        $this->assertStringContainsString('20', $output);
        $this->assertStringContainsString('2025', $output);
    }

    /**
     * Test render_custom_columns with no attachments
     */
    public function testRenderCustomColumnsOutputsNoAttachments(): void {
        Functions\when('get_attached_media')->justReturn([]);
        Functions\when('has_post_thumbnail')->justReturn(false);

        ob_start();
        Admin::render_custom_columns('attachments', 414);
        $output = ob_get_clean();

        // Should be empty when no attachments
        $this->assertEmpty(trim($output));
    }

    /**
     * Test enqueue_scripts for announcement edit page
     */
    public function testEnqueueScriptsForAnnouncementEditPage(): void {
        global $post_type;
        $post_type = 'mayo_announcement';

        $enqueuedScripts = [];
        Functions\when('wp_register_script')->alias(function($handle) use (&$enqueuedScripts) {
            $enqueuedScripts[] = $handle;
        });
        Functions\when('wp_enqueue_script')->justReturn(true);
        Functions\when('wp_enqueue_style')->justReturn(true);
        Functions\when('wp_add_inline_script')->justReturn(true);
        Functions\when('wp_localize_script')->justReturn(true);
        Functions\when('plugin_dir_url')->justReturn('https://example.com/wp-content/plugins/mayo/');
        Functions\when('wp_create_nonce')->justReturn('test-nonce');

        Admin::enqueue_scripts('post.php');

        $this->assertContains('mayo-admin', $enqueuedScripts);
    }

    /**
     * Test handle_custom_orderby returns unchanged for non-admin
     */
    public function testHandleCustomOrderbyReturnsUnchangedForNonAdmin(): void {
        Functions\when('is_admin')->justReturn(false);

        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(true);

        $result = Admin::handle_custom_orderby('original_orderby', $mockQuery);

        $this->assertEquals('original_orderby', $result);
    }

    /**
     * Test handle_custom_orderby returns unchanged for non-main query
     */
    public function testHandleCustomOrderbyReturnsUnchangedForNonMainQuery(): void {
        Functions\when('is_admin')->justReturn(true);

        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(false);

        $result = Admin::handle_custom_orderby('original_orderby', $mockQuery);

        $this->assertEquals('original_orderby', $result);
    }

    /**
     * Test handle_custom_orderby for event_type
     */
    public function testHandleCustomOrderbyForEventType(): void {
        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->postmeta = 'wp_postmeta';
        $wpdb->posts = 'wp_posts';

        Functions\when('is_admin')->justReturn(true);

        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(true);
        $mockQuery->shouldReceive('get')->with('orderby')->andReturn('event_type');
        $mockQuery->shouldReceive('get')->with('order', 'ASC')->andReturn('ASC');

        $result = Admin::handle_custom_orderby('original_orderby', $mockQuery);

        $this->assertStringContainsString('event_type', $result);
        $this->assertStringContainsString('ASC', $result);
    }

    /**
     * Test handle_custom_orderby for event_start_date
     */
    public function testHandleCustomOrderbyForEventStartDate(): void {
        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->postmeta = 'wp_postmeta';
        $wpdb->posts = 'wp_posts';

        Functions\when('is_admin')->justReturn(true);

        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(true);
        $mockQuery->shouldReceive('get')->with('orderby')->andReturn('event_start_date');
        $mockQuery->shouldReceive('get')->with('order', 'ASC')->andReturn('DESC');

        $result = Admin::handle_custom_orderby('original_orderby', $mockQuery);

        $this->assertStringContainsString('event_start_date', $result);
        $this->assertStringContainsString('DESC', $result);
    }

    /**
     * Test handle_custom_orderby for service_body
     */
    public function testHandleCustomOrderbyForServiceBody(): void {
        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->postmeta = 'wp_postmeta';
        $wpdb->posts = 'wp_posts';

        Functions\when('is_admin')->justReturn(true);

        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(true);
        $mockQuery->shouldReceive('get')->with('orderby')->andReturn('service_body');
        $mockQuery->shouldReceive('get')->with('order', 'ASC')->andReturn('ASC');

        $result = Admin::handle_custom_orderby('original_orderby', $mockQuery);

        $this->assertStringContainsString('service_body', $result);
    }

    /**
     * Test handle_custom_orderby for post_status
     */
    public function testHandleCustomOrderbyForPostStatus(): void {
        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->postmeta = 'wp_postmeta';
        $wpdb->posts = 'wp_posts';

        Functions\when('is_admin')->justReturn(true);

        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(true);
        $mockQuery->shouldReceive('get')->with('orderby')->andReturn('post_status');
        $mockQuery->shouldReceive('get')->with('order', 'ASC')->andReturn('DESC');

        $result = Admin::handle_custom_orderby('original_orderby', $mockQuery);

        $this->assertStringContainsString('post_status', $result);
        $this->assertStringContainsString('DESC', $result);
    }

    /**
     * Test filter_events_by_date returns for non-admin
     */
    public function testFilterEventsByDateReturnsForNonAdmin(): void {
        global $pagenow;
        $pagenow = 'edit.php';

        Functions\when('is_admin')->justReturn(false);

        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(true);

        // Should not modify the query
        Admin::filter_events_by_date($mockQuery);

        // Query should not have set called
        $this->assertTrue(true); // No exception means success
    }

    /**
     * Test filter_events_by_date returns for non-edit.php
     */
    public function testFilterEventsByDateReturnsForNonEditPage(): void {
        global $pagenow;
        $pagenow = 'post.php';

        Functions\when('is_admin')->justReturn(true);

        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(true);

        Admin::filter_events_by_date($mockQuery);

        $this->assertTrue(true); // No exception means success
    }

    /**
     * Test filter_events_by_date returns for non-main query
     */
    public function testFilterEventsByDateReturnsForNonMainQuery(): void {
        global $pagenow;
        $pagenow = 'edit.php';

        Functions\when('is_admin')->justReturn(true);

        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(false);

        Admin::filter_events_by_date($mockQuery);

        $this->assertTrue(true);
    }

    /**
     * Test filter_events_by_date returns for non-mayo_event post type
     */
    public function testFilterEventsByDateReturnsForNonMayoEvent(): void {
        global $pagenow;
        $pagenow = 'edit.php';
        $_GET['post_type'] = 'post';

        Functions\when('is_admin')->justReturn(true);

        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(true);

        Admin::filter_events_by_date($mockQuery);

        $this->assertTrue(true);

        unset($_GET['post_type']);
    }

    /**
     * Test filter_events_by_date returns when no filter selected
     */
    public function testFilterEventsByDateReturnsWhenNoFilter(): void {
        global $pagenow;
        $pagenow = 'edit.php';
        $_GET['post_type'] = 'mayo_event';
        // no event_date_filter set

        Functions\when('is_admin')->justReturn(true);

        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(true);

        Admin::filter_events_by_date($mockQuery);

        $this->assertTrue(true);

        unset($_GET['post_type']);
    }

    /**
     * Test filter_events_by_date with upcoming filter
     */
    public function testFilterEventsByDateWithUpcomingFilter(): void {
        global $pagenow;
        $pagenow = 'edit.php';
        $_GET['post_type'] = 'mayo_event';
        $_GET['event_date_filter'] = 'upcoming';

        Functions\when('is_admin')->justReturn(true);
        Functions\when('current_time')->justReturn('2025-01-15');
        Functions\when('add_filter')->justReturn(true);

        $metaQuerySet = null;
        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(true);
        $mockQuery->shouldReceive('get')->with('meta_query')->andReturn([]);
        $mockQuery->shouldReceive('set')->with('meta_query', \Mockery::any())->andReturnUsing(function($key, $value) use (&$metaQuerySet) {
            $metaQuerySet = $value;
        });

        Admin::filter_events_by_date($mockQuery);

        $this->assertIsArray($metaQuerySet);
        $this->assertEquals('AND', $metaQuerySet['relation']);

        unset($_GET['post_type'], $_GET['event_date_filter']);
    }

    /**
     * Test filter_events_by_date with past filter
     */
    public function testFilterEventsByDateWithPastFilter(): void {
        global $pagenow;
        $pagenow = 'edit.php';
        $_GET['post_type'] = 'mayo_event';
        $_GET['event_date_filter'] = 'past';

        Functions\when('is_admin')->justReturn(true);
        Functions\when('current_time')->justReturn('2025-01-15');
        Functions\when('add_filter')->justReturn(true);

        $metaQuerySet = null;
        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(true);
        $mockQuery->shouldReceive('get')->with('meta_query')->andReturn([]);
        $mockQuery->shouldReceive('set')->with('meta_query', \Mockery::any())->andReturnUsing(function($key, $value) use (&$metaQuerySet) {
            $metaQuerySet = $value;
        });

        Admin::filter_events_by_date($mockQuery);

        $this->assertIsArray($metaQuerySet);
        $this->assertEquals('AND', $metaQuerySet['relation']);

        unset($_GET['post_type'], $_GET['event_date_filter']);
    }

    /**
     * Test filter_events_by_date with recurring filter
     */
    public function testFilterEventsByDateWithRecurringFilter(): void {
        global $pagenow;
        $pagenow = 'edit.php';
        $_GET['post_type'] = 'mayo_event';
        $_GET['event_date_filter'] = 'recurring';

        Functions\when('is_admin')->justReturn(true);
        Functions\when('current_time')->justReturn('2025-01-15');
        Functions\when('add_filter')->justReturn(true);

        $mockQuery = \Mockery::mock('WP_Query');
        $mockQuery->shouldReceive('is_main_query')->andReturn(true);
        $mockQuery->shouldReceive('get')->with('meta_query')->andReturn([]);
        $mockQuery->shouldReceive('set')->with('meta_query', \Mockery::any());

        Admin::filter_events_by_date($mockQuery);

        $this->assertTrue(true); // Recurring just adds filter, no meta_query changes

        unset($_GET['post_type'], $_GET['event_date_filter']);
    }

    /**
     * Test render_custom_columns with end time but no end date
     */
    public function testRenderCustomColumnsWithEndTimeNoEndDate(): void {
        $this->setPostMeta(420, [
            'event_start_date' => '2025-03-10',
            'event_end_date' => '',
            'event_start_time' => '09:00:00',
            'event_end_time' => '17:00:00',
            'timezone' => 'America/New_York',
            'recurring_pattern' => ['type' => 'none']
        ]);

        ob_start();
        Admin::render_custom_columns('event_datetime', 420);
        $output = ob_get_clean();

        $this->assertStringContainsString('Mar', $output);
        $this->assertStringContainsString('10', $output);
    }

    /**
     * Test render_custom_columns with no timezone
     */
    public function testRenderCustomColumnsWithNoTimezone(): void {
        $this->setPostMeta(421, [
            'event_start_date' => '2025-04-15',
            'event_start_time' => '10:00:00',
            'timezone' => '',
            'recurring_pattern' => ['type' => 'none']
        ]);

        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('UTC'));

        ob_start();
        Admin::render_custom_columns('event_datetime', 421);
        $output = ob_get_clean();

        $this->assertStringContainsString('Apr', $output);
        $this->assertStringContainsString('15', $output);
    }

    /**
     * Test render_custom_columns service body with BMLT error response
     */
    public function testRenderCustomColumnsServiceBodyWithBmltError(): void {
        $this->setPostMeta(422, [
            'service_body' => '123'
        ]);

        // wp_remote_get returns WP_Error which is_wp_error() will detect
        Functions\when('wp_remote_get')->justReturn(new \WP_Error('http_error', 'Connection failed'));

        ob_start();
        Admin::render_custom_columns('service_body', 422);
        $output = ob_get_clean();

        // Should show fallback
        $this->assertStringContainsString('123', $output);
    }

    /**
     * Test handle_event_status_transition with invalid email
     */
    public function testHandleEventStatusTransitionWithInvalidEmail(): void {
        $post = $this->createMockPost([
            'ID' => 350,
            'post_type' => 'mayo_event'
        ]);

        $this->setPostMeta(350, [
            'email' => 'invalid-email',
            'contact_name' => 'Test User'
        ]);

        Admin::handle_event_status_transition('publish', 'pending', $post);

        $emails = $this->getCapturedEmails();
        $this->assertEmpty($emails);
    }

    /**
     * Test handle_event_status_transition with categories and tags
     */
    public function testHandleEventStatusTransitionWithCategoriesAndTags(): void {
        $post = $this->createMockPost([
            'ID' => 360,
            'post_type' => 'mayo_event',
            'post_title' => 'Event With Taxonomies',
            'post_content' => 'Description'
        ]);

        $this->setPostMeta(360, [
            'email' => 'submitter@example.com',
            'contact_name' => 'John Doe',
            'event_type' => 'Service',
            'event_start_date' => '2025-02-20',
            'event_start_time' => '14:00',
            'timezone' => 'America/New_York',
            'location_name' => 'Test Hall',
            'location_address' => '456 Main St'
        ]);

        Functions\when('get_the_title')->justReturn('Event With Taxonomies');
        Functions\when('wp_get_post_categories')->justReturn(['News', 'Events']);
        Functions\when('wp_get_post_tags')->justReturn(['Featured', 'Important']);
        Functions\when('get_bloginfo')->justReturn('Test Site');
        $this->mockTrailingslashit();

        Admin::handle_event_status_transition('publish', 'pending', $post);

        $emails = $this->getCapturedEmails();
        $this->assertNotEmpty($emails);
        $this->assertEquals('submitter@example.com', $emails[0]['to']);
    }

    /**
     * Test render_custom_columns with same start and end date
     */
    public function testRenderCustomColumnsWithSameDates(): void {
        $this->setPostMeta(430, [
            'event_start_date' => '2025-05-01',
            'event_end_date' => '2025-05-01',
            'event_start_time' => '09:00:00',
            'event_end_time' => '17:00:00',
            'timezone' => 'America/New_York',
            'recurring_pattern' => ['type' => 'none']
        ]);

        ob_start();
        Admin::render_custom_columns('event_datetime', 430);
        $output = ob_get_clean();

        $this->assertStringContainsString('May', $output);
        $this->assertStringContainsString('1', $output);
        $this->assertStringContainsString('9:00 AM', $output);
        $this->assertStringContainsString('5:00 PM', $output);
    }

    /**
     * Test render_custom_columns with recurring but no skipped
     */
    public function testRenderCustomColumnsRecurringNoSkipped(): void {
        $this->setPostMeta(431, [
            'event_start_date' => '2025-05-01',
            'event_start_time' => '10:00:00',
            'timezone' => 'America/New_York',
            'recurring_pattern' => ['type' => 'daily', 'interval' => 1],
            'skipped_occurrences' => []
        ]);

        ob_start();
        Admin::render_custom_columns('event_datetime', 431);
        $output = ob_get_clean();

        $this->assertStringContainsString('Recurring', $output);
        $this->assertStringNotContainsString('skipped', $output);
    }

    /**
     * Test enqueue_scripts for settings page
     */
    public function testEnqueueScriptsForSettingsPage(): void {
        global $post_type;
        $post_type = '';

        $enqueuedScripts = [];
        Functions\when('wp_register_script')->alias(function($handle) use (&$enqueuedScripts) {
            $enqueuedScripts[] = $handle;
        });
        Functions\when('wp_enqueue_script')->justReturn(true);
        Functions\when('wp_enqueue_style')->justReturn(true);
        Functions\when('wp_add_inline_script')->justReturn(true);
        Functions\when('wp_localize_script')->justReturn(true);
        Functions\when('plugin_dir_url')->justReturn('https://example.com/wp-content/plugins/mayo/');
        Functions\when('wp_create_nonce')->justReturn('test-nonce');

        Admin::enqueue_scripts('mayo_page_mayo-settings');

        $this->assertContains('mayo-admin', $enqueuedScripts);
    }

    /**
     * Test enqueue_scripts for subscribers page
     */
    public function testEnqueueScriptsForSubscribersPage(): void {
        global $post_type;
        $post_type = '';

        $enqueuedScripts = [];
        Functions\when('wp_register_script')->alias(function($handle) use (&$enqueuedScripts) {
            $enqueuedScripts[] = $handle;
        });
        Functions\when('wp_enqueue_script')->justReturn(true);
        Functions\when('wp_enqueue_style')->justReturn(true);
        Functions\when('wp_add_inline_script')->justReturn(true);
        Functions\when('wp_localize_script')->justReturn(true);
        Functions\when('plugin_dir_url')->justReturn('https://example.com/wp-content/plugins/mayo/');
        Functions\when('wp_create_nonce')->justReturn('test-nonce');

        Admin::enqueue_scripts('mayo_page_mayo-subscribers');

        $this->assertContains('mayo-admin', $enqueuedScripts);
    }

    /**
     * Test enqueue_scripts for api docs page
     */
    public function testEnqueueScriptsForApiDocsPage(): void {
        global $post_type;
        $post_type = '';

        $enqueuedScripts = [];
        Functions\when('wp_register_script')->alias(function($handle) use (&$enqueuedScripts) {
            $enqueuedScripts[] = $handle;
        });
        Functions\when('wp_enqueue_script')->justReturn(true);
        Functions\when('wp_enqueue_style')->justReturn(true);
        Functions\when('wp_add_inline_script')->justReturn(true);
        Functions\when('wp_localize_script')->justReturn(true);
        Functions\when('plugin_dir_url')->justReturn('https://example.com/wp-content/plugins/mayo/');
        Functions\when('wp_create_nonce')->justReturn('test-nonce');

        Admin::enqueue_scripts('mayo_page_mayo-api-docs');

        $this->assertContains('mayo-admin', $enqueuedScripts);
    }

    /**
     * Test enqueue_scripts for css classes page
     */
    public function testEnqueueScriptsForCssClassesPage(): void {
        global $post_type;
        $post_type = '';

        $enqueuedScripts = [];
        Functions\when('wp_register_script')->alias(function($handle) use (&$enqueuedScripts) {
            $enqueuedScripts[] = $handle;
        });
        Functions\when('wp_enqueue_script')->justReturn(true);
        Functions\when('wp_enqueue_style')->justReturn(true);
        Functions\when('wp_add_inline_script')->justReturn(true);
        Functions\when('wp_localize_script')->justReturn(true);
        Functions\when('plugin_dir_url')->justReturn('https://example.com/wp-content/plugins/mayo/');
        Functions\when('wp_create_nonce')->justReturn('test-nonce');

        Admin::enqueue_scripts('mayo_page_mayo-css-classes');

        $this->assertContains('mayo-admin', $enqueuedScripts);
    }

    /**
     * Test enqueue_scripts for new announcement page
     */
    public function testEnqueueScriptsForNewAnnouncementPage(): void {
        global $post_type;
        $post_type = 'mayo_announcement';

        $enqueuedScripts = [];
        Functions\when('wp_register_script')->alias(function($handle) use (&$enqueuedScripts) {
            $enqueuedScripts[] = $handle;
        });
        Functions\when('wp_enqueue_script')->justReturn(true);
        Functions\when('wp_enqueue_style')->justReturn(true);
        Functions\when('wp_add_inline_script')->justReturn(true);
        Functions\when('wp_localize_script')->justReturn(true);
        Functions\when('plugin_dir_url')->justReturn('https://example.com/wp-content/plugins/mayo/');
        Functions\when('wp_create_nonce')->justReturn('test-nonce');

        Admin::enqueue_scripts('post-new.php');

        $this->assertContains('mayo-admin', $enqueuedScripts);
    }
}
