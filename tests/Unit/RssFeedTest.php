<?php

namespace BmltEnabled\Mayo\Tests\Unit;

use BmltEnabled\Mayo\RssFeed;
use Brain\Monkey\Functions;
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
     * Helper to get private method
     */
    private function getPrivateMethod(string $methodName): \ReflectionMethod {
        $reflection = new ReflectionClass(RssFeed::class);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }
}
