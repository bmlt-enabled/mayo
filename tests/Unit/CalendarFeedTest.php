<?php

namespace BmltEnabled\Mayo\Tests\Unit;

use BmltEnabled\Mayo\CalendarFeed;
use Brain\Monkey\Functions;
use ReflectionClass;

class CalendarFeedTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        $this->mockPostMeta();
        Functions\when('get_bloginfo')->justReturn('Test Site');
    }

    /**
     * Test init registers feed action
     */
    public function testInitRegistersFeedAction(): void {
        $actionsAdded = [];

        Functions\when('add_action')->alias(function($tag, $callback) use (&$actionsAdded) {
            $actionsAdded[] = $tag;
        });

        CalendarFeed::init();

        $this->assertContains('init', $actionsAdded);
    }

    /**
     * Test register_feed adds the feed
     */
    public function testRegisterFeedAddsFeed(): void {
        $feedsAdded = [];

        Functions\when('add_feed')->alias(function($feedname, $callback) use (&$feedsAdded) {
            $feedsAdded[] = $feedname;
        });

        CalendarFeed::register_feed();

        $this->assertContains('mayo_events', $feedsAdded);
    }

    /**
     * Test escape_ical_text escapes newlines
     */
    public function testEscapeIcalTextEscapesNewlines(): void {
        $method = $this->getPrivateMethod('escape_ical_text');

        $result = $method->invoke(null, "Line 1\nLine 2");

        // The escape produces a literal backslash-n
        $this->assertStringContainsString('n', $result);
        $this->assertStringNotContainsString("\n", $result);
    }

    /**
     * Test escape_ical_text escapes commas
     */
    public function testEscapeIcalTextEscapesCommas(): void {
        $method = $this->getPrivateMethod('escape_ical_text');

        $result = $method->invoke(null, "Test, event");

        // The result will have an escaped comma (backslash before comma)
        $this->assertStringContainsString('\\,', $result);
    }

    /**
     * Test escape_ical_text escapes semicolons
     */
    public function testEscapeIcalTextEscapesSemicolons(): void {
        $method = $this->getPrivateMethod('escape_ical_text');

        $result = $method->invoke(null, "Test; event");

        // The result will have an escaped semicolon
        $this->assertStringContainsString('\\;', $result);
    }

    /**
     * Test escape_ical_text escapes backslashes
     */
    public function testEscapeIcalTextEscapesBackslashes(): void {
        $method = $this->getPrivateMethod('escape_ical_text');

        $input = "Test" . chr(92) . "event"; // Single backslash
        $result = $method->invoke(null, $input);

        // Should have doubled the backslash
        $this->assertGreaterThan(strlen($input), strlen($result));
    }

    /**
     * Test escape_ical_text handles combined special characters
     */
    public function testEscapeIcalTextHandlesCombined(): void {
        $method = $this->getPrivateMethod('escape_ical_text');

        $result = $method->invoke(null, "Event: Test, with; special\nchars");

        // Verify special characters are escaped with backslashes
        $this->assertStringContainsString('\\,', $result);
        $this->assertStringContainsString('\\;', $result);
        $this->assertStringNotContainsString("\n", $result);
    }

    /**
     * Test escape_ical_text handles carriage returns
     */
    public function testEscapeIcalTextHandlesCarriageReturns(): void {
        $method = $this->getPrivateMethod('escape_ical_text');

        $result = $method->invoke(null, "Line 1\r\nLine 2");

        // Should not contain actual CRLF
        $this->assertStringNotContainsString("\r\n", $result);
    }

    /**
     * Test get_ics_items returns empty array when no events
     */
    public function testGetIcsItemsReturnsEmptyWhenNoEvents(): void {
        Functions\when('get_posts')->justReturn([]);

        $method = $this->getPrivateMethod('get_ics_items');
        $result = $method->invoke(null);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test get_ics_items returns formatted events
     */
    public function testGetIcsItemsReturnsFormattedEvents(): void {
        $post = $this->createMockPost([
            'ID' => 100,
            'post_title' => 'Test Event',
            'post_content' => 'Test description',
            'post_type' => 'mayo_event'
        ]);

        $this->setPostMeta(100, [
            'event_type' => 'Meeting',
            'event_start_date' => date('Y-m-d', strtotime('+7 days')),
            'event_end_date' => date('Y-m-d', strtotime('+7 days')),
            'event_start_time' => '10:00:00',
            'event_end_time' => '12:00:00',
            'timezone' => 'America/New_York',
            'location_name' => 'Test Location'
        ]);

        Functions\when('get_posts')->justReturn([$post]);

        $method = $this->getPrivateMethod('get_ics_items');
        $result = $method->invoke(null);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('uid', $result[0]);
        $this->assertArrayHasKey('dtstamp', $result[0]);
        $this->assertArrayHasKey('dtstart', $result[0]);
        $this->assertArrayHasKey('dtend', $result[0]);
        $this->assertArrayHasKey('summary', $result[0]);
        $this->assertArrayHasKey('description', $result[0]);
        $this->assertArrayHasKey('location', $result[0]);
        $this->assertArrayHasKey('url', $result[0]);

        $this->assertEquals('Test Event', $result[0]['summary']);
        $this->assertEquals('Test Location', $result[0]['location']);
    }

    /**
     * Test get_ics_items with event_type filter
     */
    public function testGetIcsItemsWithEventTypeFilter(): void {
        Functions\when('get_posts')->justReturn([]);

        $method = $this->getPrivateMethod('get_ics_items');
        $result = $method->invoke(null, 'Service');

        // Just verify it runs without error
        $this->assertIsArray($result);
    }

    /**
     * Test get_ics_items with service_body filter
     */
    public function testGetIcsItemsWithServiceBodyFilter(): void {
        Functions\when('get_posts')->justReturn([]);

        $method = $this->getPrivateMethod('get_ics_items');
        $result = $method->invoke(null, '', '10');

        $this->assertIsArray($result);
    }

    /**
     * Test get_ics_items with categories filter
     */
    public function testGetIcsItemsWithCategoriesFilter(): void {
        Functions\when('get_posts')->justReturn([]);

        $method = $this->getPrivateMethod('get_ics_items');
        $result = $method->invoke(null, '', '', 'AND', 'news,events');

        $this->assertIsArray($result);
    }

    /**
     * Test get_ics_items with tags filter
     */
    public function testGetIcsItemsWithTagsFilter(): void {
        Functions\when('get_posts')->justReturn([]);

        $method = $this->getPrivateMethod('get_ics_items');
        $result = $method->invoke(null, '', '', 'AND', '', 'featured');

        $this->assertIsArray($result);
    }

    /**
     * Test get_ics_items handles get_posts error
     */
    public function testGetIcsItemsHandlesGetPostsError(): void {
        Functions\when('get_posts')->justReturn(new \WP_Error('error', 'Database error'));

        $method = $this->getPrivateMethod('get_ics_items');
        $result = $method->invoke(null);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test get_ics_items skips invalid post objects
     */
    public function testGetIcsItemsSkipsInvalidPosts(): void {
        $validPost = $this->createMockPost([
            'ID' => 100,
            'post_title' => 'Valid Event',
            'post_content' => 'Test',
            'post_type' => 'mayo_event'
        ]);

        $this->setPostMeta(100, [
            'event_type' => 'Meeting',
            'event_start_date' => date('Y-m-d', strtotime('+7 days')),
            'event_end_date' => date('Y-m-d', strtotime('+7 days')),
            'event_start_time' => '10:00:00',
            'event_end_time' => '12:00:00',
            'timezone' => 'UTC'
        ]);

        // Include an invalid item (string instead of object)
        Functions\when('get_posts')->justReturn([$validPost, 'invalid']);

        $method = $this->getPrivateMethod('get_ics_items');
        $result = $method->invoke(null);

        // Should only have 1 event (the valid one)
        $this->assertCount(1, $result);
    }

    /**
     * Test get_ics_items formats UID correctly
     */
    public function testGetIcsItemsFormatsUidCorrectly(): void {
        $post = $this->createMockPost([
            'ID' => 123,
            'post_title' => 'Test Event',
            'post_content' => 'Test',
            'post_type' => 'mayo_event'
        ]);

        $this->setPostMeta(123, [
            'event_type' => 'Meeting',
            'event_start_date' => date('Y-m-d', strtotime('+7 days')),
            'event_end_date' => date('Y-m-d', strtotime('+7 days')),
            'event_start_time' => '10:00:00',
            'event_end_time' => '12:00:00',
            'timezone' => 'UTC'
        ]);

        Functions\when('get_posts')->justReturn([$post]);

        $method = $this->getPrivateMethod('get_ics_items');
        $result = $method->invoke(null);

        $this->assertStringContainsString('123@', $result[0]['uid']);
        $this->assertStringContainsString('example.com', $result[0]['uid']);
    }

    /**
     * Test get_ics_items uses default timezone when not specified
     */
    public function testGetIcsItemsUsesDefaultTimezone(): void {
        $post = $this->createMockPost([
            'ID' => 124,
            'post_title' => 'Event No Timezone',
            'post_content' => 'Test',
            'post_type' => 'mayo_event'
        ]);

        // Note: no timezone in meta
        $this->setPostMeta(124, [
            'event_type' => 'Meeting',
            'event_start_date' => date('Y-m-d', strtotime('+7 days')),
            'event_end_date' => date('Y-m-d', strtotime('+7 days')),
            'event_start_time' => '10:00:00',
            'event_end_time' => '12:00:00'
        ]);

        Functions\when('get_posts')->justReturn([$post]);

        $method = $this->getPrivateMethod('get_ics_items');
        $result = $method->invoke(null);

        // Should not throw an error
        $this->assertCount(1, $result);
    }

    /**
     * Test get_ics_items with both categories and tags filters
     */
    public function testGetIcsItemsWithCategoriesAndTags(): void {
        Functions\when('get_posts')->justReturn([]);

        $method = $this->getPrivateMethod('get_ics_items');
        $result = $method->invoke(null, '', '', 'AND', 'news,events', 'featured,important');

        $this->assertIsArray($result);
    }

    /**
     * Test get_ics_items with OR relation
     */
    public function testGetIcsItemsWithOrRelation(): void {
        Functions\when('get_posts')->justReturn([]);

        $method = $this->getPrivateMethod('get_ics_items');
        $result = $method->invoke(null, 'Service', '5', 'OR');

        $this->assertIsArray($result);
    }

    /**
     * Test get_ics_items handles DateTime exception gracefully
     */
    public function testGetIcsItemsHandlesDateTimeException(): void {
        $post = $this->createMockPost([
            'ID' => 200,
            'post_title' => 'Event With Bad Date',
            'post_content' => 'Test',
            'post_type' => 'mayo_event'
        ]);

        // Set invalid date that will cause DateTime exception
        $this->setPostMeta(200, [
            'event_type' => 'Meeting',
            'event_start_date' => 'invalid-date-format',
            'event_end_date' => 'also-invalid',
            'event_start_time' => 'not-a-time',
            'event_end_time' => 'also-not-time',
            'timezone' => 'Invalid/Timezone'
        ]);

        Functions\when('get_posts')->justReturn([$post]);

        $method = $this->getPrivateMethod('get_ics_items');
        $result = $method->invoke(null);

        // Should handle the exception gracefully and return empty or skip the event
        $this->assertIsArray($result);
    }

    /**
     * Test get_ics_items handles events with missing meta
     */
    public function testGetIcsItemsHandlesMissingMeta(): void {
        $post = $this->createMockPost([
            'ID' => 201,
            'post_title' => 'Event Without Meta',
            'post_content' => '',
            'post_type' => 'mayo_event'
        ]);

        // Empty meta - all defaults should kick in
        $this->setPostMeta(201, []);

        Functions\when('get_posts')->justReturn([$post]);

        $method = $this->getPrivateMethod('get_ics_items');
        $result = $method->invoke(null);

        // Should handle gracefully
        $this->assertIsArray($result);
    }

    /**
     * Test get_ics_items builds correct description with location
     */
    public function testGetIcsItemsBuildsCorrectDescriptionWithLocation(): void {
        $post = $this->createMockPost([
            'ID' => 202,
            'post_title' => 'Event With Location',
            'post_content' => 'This is the event description.',
            'post_type' => 'mayo_event'
        ]);

        $this->setPostMeta(202, [
            'event_type' => 'Convention',
            'event_start_date' => date('Y-m-d', strtotime('+10 days')),
            'event_end_date' => date('Y-m-d', strtotime('+12 days')),
            'event_start_time' => '09:00:00',
            'event_end_time' => '17:00:00',
            'timezone' => 'America/Los_Angeles',
            'location_name' => 'Grand Convention Center'
        ]);

        Functions\when('get_posts')->justReturn([$post]);

        $method = $this->getPrivateMethod('get_ics_items');
        $result = $method->invoke(null);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('Convention', $result[0]['description']);
        $this->assertStringContainsString('Grand Convention Center', $result[0]['description']);
        $this->assertStringContainsString('This is the event description', $result[0]['description']);
    }

    /**
     * Test get_ics_items handles multiple events
     */
    public function testGetIcsItemsHandlesMultipleEvents(): void {
        $post1 = $this->createMockPost([
            'ID' => 203,
            'post_title' => 'First Event',
            'post_content' => 'Description 1',
            'post_type' => 'mayo_event'
        ]);

        $post2 = $this->createMockPost([
            'ID' => 204,
            'post_title' => 'Second Event',
            'post_content' => 'Description 2',
            'post_type' => 'mayo_event'
        ]);

        $this->setPostMeta(203, [
            'event_type' => 'Meeting',
            'event_start_date' => date('Y-m-d', strtotime('+5 days')),
            'event_end_date' => date('Y-m-d', strtotime('+5 days')),
            'event_start_time' => '18:00:00',
            'event_end_time' => '20:00:00',
            'timezone' => 'UTC'
        ]);

        $this->setPostMeta(204, [
            'event_type' => 'Service',
            'event_start_date' => date('Y-m-d', strtotime('+14 days')),
            'event_end_date' => date('Y-m-d', strtotime('+14 days')),
            'event_start_time' => '10:00:00',
            'event_end_time' => '14:00:00',
            'timezone' => 'UTC'
        ]);

        Functions\when('get_posts')->justReturn([$post1, $post2]);

        $method = $this->getPrivateMethod('get_ics_items');
        $result = $method->invoke(null);

        $this->assertCount(2, $result);
        $this->assertEquals('First Event', $result[0]['summary']);
        $this->assertEquals('Second Event', $result[1]['summary']);
    }

    /**
     * Test escape_ical_text handles empty string
     */
    public function testEscapeIcalTextHandlesEmptyString(): void {
        $method = $this->getPrivateMethod('escape_ical_text');

        $result = $method->invoke(null, '');

        $this->assertEquals('', $result);
    }

    /**
     * Test escape_ical_text handles multiple line breaks
     */
    public function testEscapeIcalTextHandlesMultipleLineBreaks(): void {
        $method = $this->getPrivateMethod('escape_ical_text');

        $result = $method->invoke(null, "Line 1\n\nLine 2\n\n\nLine 3");

        // All newlines should be escaped
        $this->assertStringNotContainsString("\n", $result);
    }

    /**
     * Test get_ics_items strips HTML from content
     */
    public function testGetIcsItemsStripsHtmlFromContent(): void {
        $post = $this->createMockPost([
            'ID' => 205,
            'post_title' => 'Event With HTML',
            'post_content' => '<p>This is <strong>bold</strong> text with <a href="#">links</a>.</p>',
            'post_type' => 'mayo_event'
        ]);

        $this->setPostMeta(205, [
            'event_type' => 'Meeting',
            'event_start_date' => date('Y-m-d', strtotime('+8 days')),
            'event_end_date' => date('Y-m-d', strtotime('+8 days')),
            'event_start_time' => '19:00:00',
            'event_end_time' => '21:00:00',
            'timezone' => 'UTC'
        ]);

        Functions\when('get_posts')->justReturn([$post]);

        $method = $this->getPrivateMethod('get_ics_items');
        $result = $method->invoke(null);

        $this->assertCount(1, $result);
        // HTML should be stripped
        $this->assertStringNotContainsString('<p>', $result[0]['description']);
        $this->assertStringNotContainsString('<strong>', $result[0]['description']);
        $this->assertStringContainsString('bold', $result[0]['description']);
    }

    /**
     * Helper to get private method
     */
    private function getPrivateMethod(string $methodName): \ReflectionMethod {
        $reflection = new ReflectionClass(CalendarFeed::class);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }
}
