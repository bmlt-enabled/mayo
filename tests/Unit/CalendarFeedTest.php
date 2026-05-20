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
        Functions\when('wp_strip_all_tags')->alias(function ($text) {
            return trim(strip_tags($text));
        });
    }

    public function testInitRegistersFeedAction(): void {
        $actionsAdded = [];
        Functions\when('add_action')->alias(function ($tag) use (&$actionsAdded) {
            $actionsAdded[] = $tag;
        });

        CalendarFeed::init();

        $this->assertContains('init', $actionsAdded);
    }

    public function testRegisterFeedAddsFeed(): void {
        $feedsAdded = [];
        Functions\when('add_feed')->alias(function ($feedname) use (&$feedsAdded) {
            $feedsAdded[] = $feedname;
        });

        CalendarFeed::register_feed();

        $this->assertContains('mayo_events', $feedsAdded);
    }

    public function testEscapeIcalTextEscapesNewlines(): void {
        $method = $this->getPrivateMethod('escape_ical_text');
        $result = $method->invoke(null, "Line 1\nLine 2");

        $this->assertStringContainsString('\\n', $result);
        $this->assertStringNotContainsString("\n", $result);
    }

    public function testEscapeIcalTextEscapesCommas(): void {
        $method = $this->getPrivateMethod('escape_ical_text');
        $result = $method->invoke(null, "Test, event");

        $this->assertStringContainsString('\\,', $result);
    }

    public function testEscapeIcalTextEscapesSemicolons(): void {
        $method = $this->getPrivateMethod('escape_ical_text');
        $result = $method->invoke(null, "Test; event");

        $this->assertStringContainsString('\\;', $result);
    }

    public function testEscapeIcalTextEscapesBackslashes(): void {
        $method = $this->getPrivateMethod('escape_ical_text');
        $input = 'Test' . chr(92) . 'event';
        $result = $method->invoke(null, $input);

        // Single backslash should be escaped to two backslashes
        $this->assertStringContainsString('\\\\', $result);
    }

    public function testEscapeIcalTextHandlesCombined(): void {
        $method = $this->getPrivateMethod('escape_ical_text');
        $result = $method->invoke(null, "Event: Test, with; special\nchars");

        $this->assertStringContainsString('\\,', $result);
        $this->assertStringContainsString('\\;', $result);
        $this->assertStringContainsString('\\n', $result);
        $this->assertStringNotContainsString("\n", $result);
    }

    public function testEscapeIcalTextHandlesCarriageReturns(): void {
        $method = $this->getPrivateMethod('escape_ical_text');
        $result = $method->invoke(null, "Line 1\r\nLine 2");

        $this->assertStringNotContainsString("\r\n", $result);
        $this->assertStringContainsString('\\n', $result);
    }

    public function testEscapeIcalTextHandlesEmptyString(): void {
        $method = $this->getPrivateMethod('escape_ical_text');
        $this->assertEquals('', $method->invoke(null, ''));
    }

    public function testEscapeIcalTextDoesNotDoubleEscapeNewlines(): void {
        // Regression guard: ordering must be backslash-first so the literal "\n"
        // we emit for newlines isn't subsequently re-escaped into "\\n".
        $method = $this->getPrivateMethod('escape_ical_text');
        $result = $method->invoke(null, "Line 1\nLine 2");

        $this->assertStringContainsString('\\n', $result);
        $this->assertStringNotContainsString('\\\\n', $result);
    }

    public function testBuildVeventReturnsNullWithoutStartDate(): void {
        $post = $this->createMockPost(['ID' => 100]);
        $this->setPostMeta(100, []);

        $result = $this->buildVevent($post);
        $this->assertNull($result);
    }

    public function testBuildVeventEmitsTzidAnchoredDtstart(): void {
        $post = $this->createMockPost([
            'ID' => 101,
            'post_title' => 'Sample Event',
            'post_content' => 'Body',
            'post_date_gmt' => '2024-01-01 12:00:00',
            'post_modified_gmt' => '2024-01-02 13:00:00',
        ]);
        $this->setPostMeta(101, [
            'event_type'       => 'Meeting',
            'event_start_date' => '2030-06-15',
            'event_end_date'   => '2030-06-15',
            'event_start_time' => '10:00:00',
            'event_end_time'   => '12:00:00',
            'timezone'         => 'America/New_York',
            'location_name'    => 'Main Hall',
        ]);

        $result = $this->buildVevent($post);

        $this->assertIsArray($result);
        $this->assertEquals('America/New_York', $result['tzid']);
        $this->assertStringContainsString('DTSTART;TZID=America/New_York:20300615T100000', $result['ics']);
        $this->assertStringContainsString('DTEND;TZID=America/New_York:20300615T120000',   $result['ics']);
        $this->assertStringContainsString('SUMMARY:Sample Event', $result['ics']);
        $this->assertStringContainsString('LOCATION:Main Hall', $result['ics']);
        $this->assertStringContainsString('UID:mayo-101@example.com', $result['ics']);
        $this->assertStringContainsString('CREATED:', $result['ics']);
        $this->assertStringContainsString('LAST-MODIFIED:', $result['ics']);
        $this->assertStringNotContainsString('RRULE:', $result['ics']);
    }

    public function testBuildVeventEmitsRruleForWeeklyPattern(): void {
        $post = $this->createMockPost([
            'ID' => 102,
            'post_title' => 'Weekly Meeting',
            'post_content' => '',
        ]);
        $this->setPostMeta(102, [
            'event_start_date' => '2030-01-06',
            'event_end_date'   => '2030-01-06',
            'event_start_time' => '19:00:00',
            'event_end_time'   => '20:00:00',
            'timezone'         => 'UTC',
            'recurring_pattern' => [
                'type'     => 'weekly',
                'interval' => 1,
                'weekdays' => [1, 3], // Mon, Wed
            ],
        ]);

        $result = $this->buildVevent($post);

        $this->assertStringContainsString('RRULE:FREQ=WEEKLY;BYDAY=MO,WE', $result['ics']);
    }

    public function testBuildVeventEmitsRruleForMonthlyByDate(): void {
        $post = $this->createMockPost(['ID' => 103]);
        $this->setPostMeta(103, [
            'event_start_date' => '2030-01-15',
            'event_end_date'   => '2030-01-15',
            'event_start_time' => '09:00:00',
            'event_end_time'   => '10:00:00',
            'timezone'         => 'UTC',
            'recurring_pattern' => [
                'type'         => 'monthly',
                'interval'     => 1,
                'monthlyType'  => 'date',
                'monthlyDate'  => 15,
            ],
        ]);

        $result = $this->buildVevent($post);
        $this->assertStringContainsString('RRULE:FREQ=MONTHLY;BYMONTHDAY=15', $result['ics']);
    }

    public function testBuildVeventEmitsRruleForMonthlyByWeekday(): void {
        $post = $this->createMockPost(['ID' => 104]);
        $this->setPostMeta(104, [
            'event_start_date' => '2030-01-08',
            'event_end_date'   => '2030-01-08',
            'event_start_time' => '18:00:00',
            'event_end_time'   => '19:30:00',
            'timezone'         => 'UTC',
            'recurring_pattern' => [
                'type'            => 'monthly',
                'interval'        => 1,
                'monthlyType'     => 'weekday',
                'monthlyWeekday'  => '2,3', // 2nd Wednesday
            ],
        ]);

        $result = $this->buildVevent($post);
        $this->assertStringContainsString('RRULE:FREQ=MONTHLY;BYDAY=2WE', $result['ics']);
    }

    public function testBuildVeventEmitsRruleForMonthlyLastWeekday(): void {
        $post = $this->createMockPost(['ID' => 105]);
        $this->setPostMeta(105, [
            'event_start_date' => '2030-01-26',
            'event_end_date'   => '2030-01-26',
            'event_start_time' => '14:00:00',
            'event_end_time'   => '15:00:00',
            'timezone'         => 'UTC',
            'recurring_pattern' => [
                'type'            => 'monthly',
                'interval'        => 1,
                'monthlyType'     => 'weekday',
                'monthlyWeekday'  => '0,6', // last Saturday (week=0 means last)
            ],
        ]);

        $result = $this->buildVevent($post);
        $this->assertStringContainsString('BYDAY=-1SA', $result['ics']);
    }

    public function testBuildVeventEmitsExdateForSkippedOccurrences(): void {
        $post = $this->createMockPost(['ID' => 106]);
        $this->setPostMeta(106, [
            'event_start_date' => '2030-01-06',
            'event_end_date'   => '2030-01-06',
            'event_start_time' => '10:00:00',
            'event_end_time'   => '11:00:00',
            'timezone'         => 'America/New_York',
            'recurring_pattern' => [
                'type'     => 'weekly',
                'interval' => 1,
                'weekdays' => [0], // Sunday
            ],
            'skipped_occurrences' => ['2030-01-20', '2030-02-10'],
        ]);

        $result = $this->buildVevent($post);
        $this->assertStringContainsString('EXDATE;TZID=America/New_York:20300120T100000', $result['ics']);
        $this->assertStringContainsString('EXDATE;TZID=America/New_York:20300210T100000', $result['ics']);
    }

    public function testBuildVeventEmitsUntilWhenEndDateSet(): void {
        $post = $this->createMockPost(['ID' => 107]);
        $this->setPostMeta(107, [
            'event_start_date' => '2030-01-01',
            'event_end_date'   => '2030-01-01',
            'event_start_time' => '08:00:00',
            'event_end_time'   => '09:00:00',
            'timezone'         => 'UTC',
            'recurring_pattern' => [
                'type'     => 'daily',
                'interval' => 1,
                'endDate'  => '2030-01-31',
            ],
        ]);

        $result = $this->buildVevent($post);
        $this->assertMatchesRegularExpression('/RRULE:[^\r\n]*UNTIL=20300131T\d{6}Z/', $result['ics']);
    }

    public function testBuildVeventFallsBackToUtcWhenTimezoneInvalid(): void {
        $post = $this->createMockPost(['ID' => 108]);
        $this->setPostMeta(108, [
            'event_start_date' => '2030-01-01',
            'event_end_date'   => '2030-01-01',
            'event_start_time' => '08:00:00',
            'event_end_time'   => '09:00:00',
            'timezone'         => 'Not/AReal_Zone',
        ]);

        $result = $this->buildVevent($post);
        $this->assertEquals('UTC', $result['tzid']);
        $this->assertStringContainsString('DTSTART;TZID=UTC:', $result['ics']);
    }

    public function testBuildVeventDescriptionStripsHtmlAndIncludesContext(): void {
        $post = $this->createMockPost([
            'ID' => 109,
            'post_title' => 'HTML Event',
            'post_content' => '<p>This is <strong>bold</strong> text.</p>',
        ]);
        $this->setPostMeta(109, [
            'event_type'       => 'Convention',
            'event_start_date' => '2030-01-01',
            'event_end_date'   => '2030-01-01',
            'event_start_time' => '10:00:00',
            'event_end_time'   => '12:00:00',
            'timezone'         => 'UTC',
            'location_name'    => 'Big Hall',
        ]);

        $result = $this->buildVevent($post);
        $this->assertStringContainsString('Event Type: Convention', $result['ics']);
        $this->assertStringContainsString('Location: Big Hall', $result['ics']);
        $this->assertStringContainsString('This is bold text', $result['ics']);
        $this->assertStringNotContainsString('<strong>', $result['ics']);
    }

    public function testBuildRruleReturnsNullForUnknownType(): void {
        $method = $this->getPrivateMethod('build_rrule');
        $tz = new \DateTimeZone('UTC');
        $this->assertNull($method->invoke(null, ['type' => 'mystery'], $tz));
        $this->assertNull($method->invoke(null, ['type' => 'none'],    $tz));
    }

    public function testBuildVtimezoneIncludesTzidLine(): void {
        $method = $this->getPrivateMethod('build_vtimezone');
        $out = $method->invoke(null, 'America/New_York');

        $this->assertStringContainsString('BEGIN:VTIMEZONE', $out);
        $this->assertStringContainsString('TZID:America/New_York', $out);
        $this->assertStringContainsString('END:VTIMEZONE', $out);
        // America/New_York observes DST, so we should get both components.
        $this->assertStringContainsString('BEGIN:STANDARD', $out);
        $this->assertStringContainsString('BEGIN:DAYLIGHT', $out);
    }

    public function testFormatOffsetProducesIcsOffset(): void {
        $method = $this->getPrivateMethod('format_offset');
        $this->assertEquals('+0530', $method->invoke(null, 5 * 3600 + 30 * 60));
        $this->assertEquals('-0500', $method->invoke(null, -5 * 3600));
        $this->assertEquals('+0000', $method->invoke(null, 0));
    }

    public function testQueryEventsHandlesGetPostsError(): void {
        Functions\when('get_posts')->justReturn(new \WP_Error('error', 'Database error'));

        $method = $this->getPrivateMethod('query_events');
        $result = $method->invoke(null, '', '', 'AND', '', '');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testBuildVeventSkipsPastNonRecurringEvent(): void {
        $post = $this->createMockPost(['ID' => 200]);
        $this->setPostMeta(200, [
            'event_start_date' => '2020-01-01',
            'event_end_date'   => '2020-01-01',
            'event_start_time' => '10:00:00',
            'event_end_time'   => '11:00:00',
            'timezone'         => 'UTC',
        ]);

        $this->assertNull($this->buildVevent($post));
    }

    public function testBuildVeventSkipsPastEventWithTypeNonePattern(): void {
        $post = $this->createMockPost(['ID' => 201]);
        $this->setPostMeta(201, [
            'event_start_date'   => '2020-01-01',
            'event_end_date'     => '2020-01-01',
            'event_start_time'   => '10:00:00',
            'event_end_time'     => '11:00:00',
            'timezone'           => 'UTC',
            'recurring_pattern'  => ['type' => 'none', 'interval' => 1],
        ]);

        $this->assertNull($this->buildVevent($post));
    }

    public function testBuildVeventKeepsRealRecurrenceWithPastStartDate(): void {
        $post = $this->createMockPost(['ID' => 202]);
        $this->setPostMeta(202, [
            'event_start_date'   => '2020-01-06',
            'event_end_date'     => '2020-01-06',
            'event_start_time'   => '19:00:00',
            'event_end_time'     => '20:00:00',
            'timezone'           => 'UTC',
            'recurring_pattern'  => ['type' => 'weekly', 'interval' => 1, 'weekdays' => [1]],
        ]);

        $result = $this->buildVevent($post);
        $this->assertNotNull($result);
        $this->assertStringContainsString('RRULE:FREQ=WEEKLY;BYDAY=MO', $result['ics']);
    }

    public function testBuildVeventLocationWithOnlyAddressHasNoLeadingComma(): void {
        $post = $this->createMockPost(['ID' => 203]);
        $this->setPostMeta(203, [
            'event_start_date' => '2030-01-01',
            'event_end_date'   => '2030-01-01',
            'event_start_time' => '10:00:00',
            'event_end_time'   => '11:00:00',
            'timezone'         => 'UTC',
            'location_name'    => '',
            'location_address' => 'https://us06web.zoom.us/j/12345',
        ]);

        $result = $this->buildVevent($post);
        $this->assertStringContainsString('LOCATION:https://us06web.zoom.us/j/12345', $result['ics']);
        $this->assertStringNotContainsString('LOCATION:\\, ', $result['ics']);
        $this->assertStringNotContainsString('LOCATION:, ',   $result['ics']);
    }

    public function testBuildVeventLocationCombinesNameAndAddress(): void {
        $post = $this->createMockPost(['ID' => 204]);
        $this->setPostMeta(204, [
            'event_start_date' => '2030-01-01',
            'event_end_date'   => '2030-01-01',
            'event_start_time' => '10:00:00',
            'event_end_time'   => '11:00:00',
            'timezone'         => 'UTC',
            'location_name'    => 'Main Hall',
            'location_address' => '123 Main St',
        ]);

        $result = $this->buildVevent($post);
        $this->assertStringContainsString('LOCATION:Main Hall\\, 123 Main St', $result['ics']);
    }

    /**
     * Helper: invoke build_vevent with the standard host.
     */
    private function buildVevent($post): ?array {
        $method = $this->getPrivateMethod('build_vevent');
        return $method->invoke(null, $post, 'example.com');
    }

    private function getPrivateMethod(string $methodName): \ReflectionMethod {
        $reflection = new ReflectionClass(CalendarFeed::class);
        return $reflection->getMethod($methodName);
    }
}
