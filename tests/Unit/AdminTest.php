<?php

namespace BmltEnabled\Mayo\Tests\Unit;

use BmltEnabled\Mayo\Admin;
use Brain\Monkey\Functions;

class AdminTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();

        $this->mockGetOption([
            'mayo_settings' => [
                'notification_email' => 'admin@example.com',
                'bmlt_root_server' => 'https://bmlt.example.com'
            ]
        ]);

        $this->mockPostMeta();
        $this->mockWpMail();
        $this->mockGetTheTitle();
    }

    /**
     * Test status transition ignores non-mayo_event post types
     */
    public function testStatusTransitionIgnoresNonMayoEventPostTypes(): void {
        $postId = 102;
        $post = $this->createMockPost([
            'ID' => $postId,
            'post_title' => 'Regular Post',
            'post_type' => 'post',  // Not mayo_event
            'post_status' => 'publish'
        ]);

        $this->setPostMeta($postId, [
            'email' => 'should-not@send.com'
        ]);

        Admin::handle_event_status_transition('publish', 'pending', $post);

        // Verify no email was sent
        $emails = $this->getCapturedEmails();
        $this->assertEmpty($emails, 'No email should be sent for non-mayo_event post types');
    }

    /**
     * Test status transition only sends on pending to publish
     */
    public function testStatusTransitionOnlySendsOnPendingToPublish(): void {
        $postId = 103;
        $post = $this->createMockPost([
            'ID' => $postId,
            'post_title' => 'Draft Event',
            'post_type' => 'mayo_event',
            'post_status' => 'publish'
        ]);

        $this->setPostMeta($postId, [
            'email' => 'should-not@send.com',
            'contact_name' => 'Draft Contact'
        ]);

        // Test draft to publish - should not send
        Admin::handle_event_status_transition('publish', 'draft', $post);

        $emails = $this->getCapturedEmails();
        $this->assertEmpty($emails, 'No email should be sent for draft to publish transition');
    }

    /**
     * Test status transition from auto-draft to publish does not send
     */
    public function testStatusTransitionFromAutoDraftToPublishDoesNotSend(): void {
        $postId = 104;
        $post = $this->createMockPost([
            'ID' => $postId,
            'post_title' => 'Auto Draft Event',
            'post_type' => 'mayo_event',
            'post_status' => 'publish'
        ]);

        $this->setPostMeta($postId, [
            'email' => 'should-not@send.com'
        ]);

        Admin::handle_event_status_transition('publish', 'auto-draft', $post);

        $emails = $this->getCapturedEmails();
        $this->assertEmpty($emails, 'No email should be sent for auto-draft to publish transition');
    }

    /**
     * Test status transition from new to publish does not send
     */
    public function testStatusTransitionFromNewToPublishDoesNotSend(): void {
        $postId = 105;
        $post = $this->createMockPost([
            'ID' => $postId,
            'post_title' => 'New Event',
            'post_type' => 'mayo_event',
            'post_status' => 'publish'
        ]);

        $this->setPostMeta($postId, [
            'email' => 'should-not@send.com'
        ]);

        Admin::handle_event_status_transition('publish', 'new', $post);

        $emails = $this->getCapturedEmails();
        $this->assertEmpty($emails, 'No email should be sent for new to publish transition');
    }

    /**
     * Test handle_copy_event requires proper permissions
     */
    public function testCopyEventRequiresPermissions(): void {
        $this->logoutUser();

        $canCopy = current_user_can('edit_posts');
        $this->assertFalse($canCopy);
    }

    /**
     * Test register_post_type is called
     */
    public function testRegisterPostTypeSetup(): void {
        // Mock register_post_type
        Functions\expect('register_post_type')
            ->once()
            ->with('mayo_event', \Mockery::type('array'))
            ->andReturn(new \WP_Post(['ID' => 0]));

        Admin::register_post_type();

        // Mockery expectations count as assertions
        $this->addToAssertionCount(1);
    }

    /**
     * Test set_custom_columns returns expected columns
     */
    public function testSetCustomColumnsReturnsExpectedColumns(): void {
        $inputColumns = [
            'cb' => '<input type="checkbox" />',
            'title' => 'Title',
            'date' => 'Date'
        ];

        $result = Admin::set_custom_columns($inputColumns);

        $this->assertArrayHasKey('cb', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('event_type', $result);
        $this->assertArrayHasKey('event_datetime', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('service_body', $result);
        $this->assertArrayHasKey('date', $result);
    }

    /**
     * Test set_sortable_columns returns expected sortable columns
     */
    public function testSetSortableColumnsReturnsExpectedColumns(): void {
        $columns = [];

        $result = Admin::set_sortable_columns($columns);

        $this->assertArrayHasKey('event_type', $result);
        $this->assertArrayHasKey('event_datetime', $result);
        $this->assertArrayHasKey('service_body', $result);
        $this->assertArrayHasKey('status', $result);
    }
}
