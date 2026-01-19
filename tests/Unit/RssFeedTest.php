<?php

namespace BmltEnabled\Mayo\Tests\Unit;

use BmltEnabled\Mayo\RssFeed;
use Brain\Monkey\Functions;
use Mockery;
use ReflectionClass;

class RssFeedTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Functions\when('get_bloginfo')->alias(function($show) {
            if ($show === 'name') return 'Test Site';
            if ($show === 'description') return 'Test Description';
            return '';
        });
        Functions\when('get_locale')->justReturn('en_US');
    }

    /**
     * Test init registers feed action
     */
    public function testInitRegistersFeedAction(): void {
        $actionsAdded = [];

        Functions\when('add_action')->alias(function($tag, $callback) use (&$actionsAdded) {
            $actionsAdded[] = $tag;
        });

        RssFeed::init();

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

        RssFeed::register_feed();

        $this->assertContains('mayo_rss', $feedsAdded);
    }

    /**
     * Test build_feed_description with no parameters
     */
    public function testBuildFeedDescriptionWithNoParameters(): void {
        $method = $this->getPrivateMethod('build_feed_description');

        $result = $method->invoke(null, 'Test Site');

        $this->assertStringContainsString('Event listings from Test Site', $result);
    }

    /**
     * Test build_feed_description with event_type
     */
    public function testBuildFeedDescriptionWithEventType(): void {
        $method = $this->getPrivateMethod('build_feed_description');

        $result = $method->invoke(null, 'Test Site', 'Service');

        $this->assertStringContainsString('event_type=Service', $result);
    }

    /**
     * Test build_feed_description with service_body
     */
    public function testBuildFeedDescriptionWithServiceBody(): void {
        $method = $this->getPrivateMethod('build_feed_description');

        $result = $method->invoke(null, 'Test Site', '', '10');

        $this->assertStringContainsString('service_body=10', $result);
    }

    /**
     * Test build_feed_description with multiple parameters
     */
    public function testBuildFeedDescriptionWithMultipleParameters(): void {
        $method = $this->getPrivateMethod('build_feed_description');

        $result = $method->invoke(null, 'Test Site', 'Activity', '5', 'source1,source2', 'OR', 'news,events', 'featured', 20);

        $this->assertStringContainsString('event_type=Activity', $result);
        $this->assertStringContainsString('service_body=5', $result);
        $this->assertStringContainsString('source_ids=source1,source2', $result);
        $this->assertStringContainsString('relation=OR', $result);
        $this->assertStringContainsString('categories=news,events', $result);
        $this->assertStringContainsString('tags=featured', $result);
        $this->assertStringContainsString('per_page=20', $result);
    }

    /**
     * Test build_feed_description with default relation (AND) does not include it
     */
    public function testBuildFeedDescriptionDoesNotIncludeDefaultRelation(): void {
        $method = $this->getPrivateMethod('build_feed_description');

        $result = $method->invoke(null, 'Test Site', '', '', '', 'AND');

        $this->assertStringNotContainsString('relation=', $result);
    }

    /**
     * Test build_feed_description with non-default per_page
     */
    public function testBuildFeedDescriptionWithNonDefaultPerPage(): void {
        $method = $this->getPrivateMethod('build_feed_description');

        $result = $method->invoke(null, 'Test Site', '', '', '', 'AND', '', '', 50);

        $this->assertStringContainsString('per_page=50', $result);
    }

    /**
     * Test escape_xml_text escapes special characters
     */
    public function testEscapeXmlTextEscapesSpecialCharacters(): void {
        $method = $this->getPrivateMethod('escape_xml_text');

        $this->assertEquals('&amp;', $method->invoke(null, '&'));
        $this->assertEquals('&lt;', $method->invoke(null, '<'));
        $this->assertEquals('&gt;', $method->invoke(null, '>'));
        $this->assertEquals('&quot;', $method->invoke(null, '"'));
    }

    /**
     * Test escape_xml_text handles normal text
     */
    public function testEscapeXmlTextHandlesNormalText(): void {
        $method = $this->getPrivateMethod('escape_xml_text');

        $result = $method->invoke(null, 'Test Event Title');

        $this->assertEquals('Test Event Title', $result);
    }

    /**
     * Test escape_xml_text handles combined special characters
     */
    public function testEscapeXmlTextHandlesCombinedSpecialCharacters(): void {
        $method = $this->getPrivateMethod('escape_xml_text');

        $result = $method->invoke(null, 'Event <Test> & "Special"');

        $this->assertEquals('Event &lt;Test&gt; &amp; &quot;Special&quot;', $result);
    }

    /**
     * Test get_shortcode_params_from_current_page with no post
     */
    public function testGetShortcodeParamsWithNoPost(): void {
        global $post;
        $post = null;

        Functions\when('get_queried_object')->justReturn(null);

        $method = $this->getPrivateMethod('get_shortcode_params_from_current_page');
        $result = $method->invoke(null);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test get_shortcode_params_from_current_page with post containing shortcode
     */
    public function testGetShortcodeParamsWithShortcodeInPost(): void {
        global $post;
        $post = $this->createMockPost([
            'ID' => 1,
            'post_content' => '[mayo_event_list categories="news" event_type="Service"]'
        ]);

        Functions\when('has_shortcode')->justReturn(true);
        Functions\when('get_shortcode_regex')->justReturn(
            '\\[\\[?'
            . '(mayo_event_list)'
            . '(?![\\w-])'
            . '('
            . '[^\\]\\/]*'
            . '(?:'
            . '\\/(?!\\])'
            . '[^\\]\\/]*'
            . ')*?'
            . ')'
            . '(?:'
            . '(\\/)'
            . '\\]'
            . '|'
            . '\\]'
            . '(?:'
            . '('
            . '[^\\[]*+'
            . '(?:'
            . '\\[(?!\\/\\1\\])'
            . '[^\\[]*+'
            . ')*+'
            . ')'
            . '\\[\\/\\1\\]'
            . ')?'
            . ')'
            . '(?:\\])?'
        );
        Functions\when('shortcode_parse_atts')->alias(function($text) {
            // Simple parser for test
            $attrs = [];
            preg_match_all('/(\w+)="([^"]*)"/', $text, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $attrs[$match[1]] = $match[2];
            }
            return $attrs ?: [];
        });

        $method = $this->getPrivateMethod('get_shortcode_params_from_current_page');
        $result = $method->invoke(null);

        $this->assertIsArray($result);
    }

    /**
     * Test get_shortcode_params with post without shortcode
     */
    public function testGetShortcodeParamsWithPostWithoutShortcode(): void {
        global $post;
        $post = $this->createMockPost([
            'ID' => 2,
            'post_content' => 'Just some content without shortcodes'
        ]);

        Functions\when('has_shortcode')->justReturn(false);

        $method = $this->getPrivateMethod('get_shortcode_params_from_current_page');
        $result = $method->invoke(null);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test build_feed_description with only source_ids
     */
    public function testBuildFeedDescriptionWithOnlySourceIds(): void {
        $method = $this->getPrivateMethod('build_feed_description');

        $result = $method->invoke(null, 'Test Site', '', '', 'external1,external2');

        $this->assertStringContainsString('source_ids=external1,external2', $result);
    }

    /**
     * Test build_feed_description with only categories
     */
    public function testBuildFeedDescriptionWithOnlyCategories(): void {
        $method = $this->getPrivateMethod('build_feed_description');

        $result = $method->invoke(null, 'Test Site', '', '', '', 'AND', 'news,events');

        $this->assertStringContainsString('categories=news,events', $result);
    }

    /**
     * Test build_feed_description with only tags
     */
    public function testBuildFeedDescriptionWithOnlyTags(): void {
        $method = $this->getPrivateMethod('build_feed_description');

        $result = $method->invoke(null, 'Test Site', '', '', '', 'AND', '', 'featured,urgent');

        $this->assertStringContainsString('tags=featured,urgent', $result);
    }

    /**
     * Test escape_xml_text handles apostrophes
     */
    public function testEscapeXmlTextHandlesApostrophes(): void {
        $method = $this->getPrivateMethod('escape_xml_text');

        $result = $method->invoke(null, "It's a test");

        // Apostrophe is escaped as &apos; in XML1 mode
        $this->assertStringContainsString('&apos;', $result);
    }

    /**
     * Test escape_xml_text handles unicode
     */
    public function testEscapeXmlTextHandlesUnicode(): void {
        $method = $this->getPrivateMethod('escape_xml_text');

        $result = $method->invoke(null, 'Événement spécial');

        // Unicode should be preserved
        $this->assertStringContainsString('Événement', $result);
    }

    /**
     * Test escape_xml_text handles empty string
     */
    public function testEscapeXmlTextHandlesEmptyString(): void {
        $method = $this->getPrivateMethod('escape_xml_text');

        $result = $method->invoke(null, '');

        $this->assertEquals('', $result);
    }

    /**
     * Test build_feed_description with default per_page (10)
     */
    public function testBuildFeedDescriptionWithDefaultPerPage(): void {
        $method = $this->getPrivateMethod('build_feed_description');

        $result = $method->invoke(null, 'Test Site', '', '', '', 'AND', '', '', 10);

        // Default per_page of 10 should not be included
        $this->assertStringNotContainsString('per_page=10', $result);
    }

    /**
     * Test get_shortcode_params with multiple shortcodes uses first
     */
    public function testGetShortcodeParamsWithMultipleShortcodes(): void {
        global $post;
        $post = $this->createMockPost([
            'ID' => 3,
            'post_content' => '[mayo_event_list event_type="Service"][mayo_event_list event_type="Activity"]'
        ]);

        Functions\when('has_shortcode')->justReturn(true);
        Functions\when('get_shortcode_regex')->justReturn(
            '\\[\\[?'
            . '(mayo_event_list)'
            . '(?![\\w-])'
            . '('
            . '[^\\]\\/]*'
            . '(?:'
            . '\\/(?!\\])'
            . '[^\\]\\/]*'
            . ')*?'
            . ')'
            . '(?:'
            . '(\\/)'
            . '\\]'
            . '|'
            . '\\]'
            . '(?:'
            . '('
            . '[^\\[]*+'
            . '(?:'
            . '\\[(?!\\/\\1\\])'
            . '[^\\[]*+'
            . ')*+'
            . ')'
            . '\\[\\/\\1\\]'
            . ')?'
            . ')'
            . '(?:\\])?'
        );
        Functions\when('shortcode_parse_atts')->alias(function($text) {
            $attrs = [];
            preg_match_all('/(\w+)="([^"]*)"/', $text, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $attrs[$match[1]] = $match[2];
            }
            return $attrs ?: [];
        });

        $method = $this->getPrivateMethod('get_shortcode_params_from_current_page');
        $result = $method->invoke(null);

        $this->assertIsArray($result);
    }

    /**
     * Test build_rss_xml returns valid XML structure
     */
    public function testBuildRssXmlReturnsValidXmlStructure(): void {
        $events = [];
        $xml = RssFeed::build_rss_xml(
            $events,
            'Test Site',
            'https://example.com',
            'Test description',
            'en_US',
            'Mon, 01 Jan 2024 12:00:00 +0000'
        );

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<rss version="2.0"', $xml);
        $this->assertStringContainsString('<channel>', $xml);
        $this->assertStringContainsString('</channel>', $xml);
        $this->assertStringContainsString('</rss>', $xml);
    }

    /**
     * Test build_rss_xml includes channel elements
     */
    public function testBuildRssXmlIncludesChannelElements(): void {
        $events = [];
        $xml = RssFeed::build_rss_xml(
            $events,
            'My Test Site',
            'https://mysite.example.com',
            'Events from our site',
            'fr_FR',
            'Tue, 15 Jan 2024 10:30:00 +0000'
        );

        $this->assertStringContainsString('<title>My Test Site - Events</title>', $xml);
        $this->assertStringContainsString('<link>https://mysite.example.com</link>', $xml);
        $this->assertStringContainsString('<description>Events from our site</description>', $xml);
        $this->assertStringContainsString('<lastBuildDate>Tue, 15 Jan 2024 10:30:00 +0000</lastBuildDate>', $xml);
        $this->assertStringContainsString('<language>fr_FR</language>', $xml);
        $this->assertStringContainsString('<generator>Mayo Events Manager</generator>', $xml);
    }

    /**
     * Test build_rss_xml formats events correctly
     */
    public function testBuildRssXmlFormatsEventsCorrectly(): void {
        $events = [
            [
                'title' => 'Test Event',
                'link' => 'https://example.com/event/1',
                'pub_date' => 'Wed, 20 Jan 2024 14:00:00 +0000',
                'description' => 'Event on January 20',
                'content' => '<p>Full event content here</p>',
                'categories' => []
            ]
        ];

        $xml = RssFeed::build_rss_xml(
            $events,
            'Test Site',
            'https://example.com',
            'Test description',
            'en_US',
            'Mon, 01 Jan 2024 12:00:00 +0000'
        );

        $this->assertStringContainsString('<item>', $xml);
        $this->assertStringContainsString('<title>Test Event</title>', $xml);
        $this->assertStringContainsString('<link>https://example.com/event/1</link>', $xml);
        $this->assertStringContainsString('<guid isPermaLink="true">https://example.com/event/1</guid>', $xml);
        $this->assertStringContainsString('<pubDate>Wed, 20 Jan 2024 14:00:00 +0000</pubDate>', $xml);
        $this->assertStringContainsString('<description><![CDATA[Event on January 20]]></description>', $xml);
        $this->assertStringContainsString('<content:encoded><![CDATA[<p>Full event content here</p>]]></content:encoded>', $xml);
        $this->assertStringContainsString('</item>', $xml);
    }

    /**
     * Test build_rss_xml includes multiple items
     */
    public function testBuildRssXmlIncludesMultipleItems(): void {
        $events = [
            [
                'title' => 'First Event',
                'link' => 'https://example.com/event/1',
                'pub_date' => 'Wed, 20 Jan 2024 14:00:00 +0000',
                'description' => 'First event',
                'content' => 'Content 1',
                'categories' => []
            ],
            [
                'title' => 'Second Event',
                'link' => 'https://example.com/event/2',
                'pub_date' => 'Thu, 21 Jan 2024 14:00:00 +0000',
                'description' => 'Second event',
                'content' => 'Content 2',
                'categories' => []
            ],
            [
                'title' => 'Third Event',
                'link' => 'https://example.com/event/3',
                'pub_date' => 'Fri, 22 Jan 2024 14:00:00 +0000',
                'description' => 'Third event',
                'content' => 'Content 3',
                'categories' => []
            ]
        ];

        $xml = RssFeed::build_rss_xml(
            $events,
            'Test Site',
            'https://example.com',
            'Test description',
            'en_US'
        );

        $this->assertStringContainsString('<title>First Event</title>', $xml);
        $this->assertStringContainsString('<title>Second Event</title>', $xml);
        $this->assertStringContainsString('<title>Third Event</title>', $xml);
        $this->assertEquals(3, substr_count($xml, '<item>'));
        $this->assertEquals(3, substr_count($xml, '</item>'));
    }

    /**
     * Test build_rss_xml includes categories
     */
    public function testBuildRssXmlIncludesCategories(): void {
        $events = [
            [
                'title' => 'Categorized Event',
                'link' => 'https://example.com/event/1',
                'pub_date' => 'Wed, 20 Jan 2024 14:00:00 +0000',
                'description' => 'Event with categories',
                'content' => 'Content',
                'categories' => ['News', 'Events', 'Featured']
            ]
        ];

        $xml = RssFeed::build_rss_xml(
            $events,
            'Test Site',
            'https://example.com',
            'Test description',
            'en_US'
        );

        $this->assertStringContainsString('<category>News</category>', $xml);
        $this->assertStringContainsString('<category>Events</category>', $xml);
        $this->assertStringContainsString('<category>Featured</category>', $xml);
    }

    /**
     * Test build_rss_xml escapes XML special characters in title
     */
    public function testBuildRssXmlEscapesSpecialCharactersInTitle(): void {
        $events = [
            [
                'title' => 'Event & "Special" <Test>',
                'link' => 'https://example.com/event/1',
                'pub_date' => 'Wed, 20 Jan 2024 14:00:00 +0000',
                'description' => 'Description',
                'content' => 'Content',
                'categories' => []
            ]
        ];

        $xml = RssFeed::build_rss_xml(
            $events,
            'Site & "Name"',
            'https://example.com',
            'Description with <tags>',
            'en_US'
        );

        $this->assertStringContainsString('&amp;', $xml);
        $this->assertStringContainsString('&quot;', $xml);
        $this->assertStringContainsString('&lt;', $xml);
        $this->assertStringContainsString('&gt;', $xml);
    }

    /**
     * Test build_rss_xml handles empty events array
     */
    public function testBuildRssXmlHandlesEmptyEventsArray(): void {
        $xml = RssFeed::build_rss_xml(
            [],
            'Test Site',
            'https://example.com',
            'Test description',
            'en_US'
        );

        $this->assertStringContainsString('<channel>', $xml);
        $this->assertStringContainsString('</channel>', $xml);
        $this->assertStringNotContainsString('<item>', $xml);
    }

    /**
     * Test build_rss_xml uses current date when build_date is null
     */
    public function testBuildRssXmlUsesCurrentDateWhenBuildDateIsNull(): void {
        $xml = RssFeed::build_rss_xml(
            [],
            'Test Site',
            'https://example.com',
            'Test description',
            'en_US',
            null
        );

        // Should contain a date in RFC 2822 format
        $this->assertMatchesRegularExpression('/<lastBuildDate>[A-Za-z]{3}, \d{2} [A-Za-z]{3} \d{4} \d{2}:\d{2}:\d{2} [+-]\d{4}<\/lastBuildDate>/', $xml);
    }

    /**
     * Test build_rss_xml is well-formed XML
     */
    public function testBuildRssXmlIsWellFormedXml(): void {
        $events = [
            [
                'title' => 'Test Event',
                'link' => 'https://example.com/event/1',
                'pub_date' => 'Wed, 20 Jan 2024 14:00:00 +0000',
                'description' => 'Event description',
                'content' => '<p>Content</p>',
                'categories' => ['Category1']
            ]
        ];

        $xml = RssFeed::build_rss_xml(
            $events,
            'Test Site',
            'https://example.com',
            'Test description',
            'en_US',
            'Mon, 01 Jan 2024 12:00:00 +0000'
        );

        // Parse XML to verify it's well-formed
        $doc = new \DOMDocument();
        $result = @$doc->loadXML($xml);
        $this->assertTrue($result, 'XML should be well-formed and parseable');
    }

    /**
     * Test build_rss_xml RSS namespace for content:encoded
     */
    public function testBuildRssXmlHasContentNamespace(): void {
        $xml = RssFeed::build_rss_xml(
            [],
            'Test Site',
            'https://example.com',
            'Test description',
            'en_US'
        );

        $this->assertStringContainsString('xmlns:content="http://purl.org/rss/1.0/modules/content/"', $xml);
        $this->assertStringContainsString('xmlns:dc="http://purl.org/dc/elements/1.1/"', $xml);
    }

    /**
     * Test build_rss_xml handles event with empty categories
     */
    public function testBuildRssXmlHandlesEventWithEmptyCategories(): void {
        $events = [
            [
                'title' => 'Event Without Categories',
                'link' => 'https://example.com/event/1',
                'pub_date' => 'Wed, 20 Jan 2024 14:00:00 +0000',
                'description' => 'Description',
                'content' => 'Content',
                'categories' => []
            ]
        ];

        $xml = RssFeed::build_rss_xml(
            $events,
            'Test Site',
            'https://example.com',
            'Test description',
            'en_US'
        );

        $this->assertStringContainsString('<item>', $xml);
        $this->assertStringNotContainsString('<category>', $xml);
    }

    /**
     * Helper to get private method
     */
    private function getPrivateMethod(string $methodName): \ReflectionMethod {
        $reflection = new ReflectionClass(RssFeed::class);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }
}
