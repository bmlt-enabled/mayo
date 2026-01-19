<?php

namespace BmltEnabled\Mayo\Tests\Unit;

use BmltEnabled\Mayo\Frontend;
use Brain\Monkey\Functions;

class FrontendTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();

        // Mock WordPress script/style functions
        Functions\when('wp_enqueue_script')->justReturn(true);
        Functions\when('wp_enqueue_style')->justReturn(true);
        Functions\when('wp_localize_script')->justReturn(true);
        Functions\when('wp_register_script')->justReturn(true);
        Functions\when('wp_create_nonce')->justReturn('test-nonce');
        Functions\when('plugin_dir_url')->justReturn('https://example.com/wp-content/plugins/mayo/');
        Functions\when('has_shortcode')->justReturn(false);
        Functions\when('is_post_type_archive')->justReturn(false);
        Functions\when('is_singular')->justReturn(false);
        Functions\when('doing_filter')->justReturn(false);
        Functions\when('current_filter')->justReturn('the_content');
        Functions\when('is_active_sidebar')->justReturn(false);
        Functions\when('wp_get_sidebars_widgets')->justReturn([]);
    }

    /**
     * Test init registers shortcodes
     */
    public function testInitRegistersShortcodes(): void {
        $shortcodesRegistered = [];

        Functions\when('add_shortcode')->alias(function($tag, $callback) use (&$shortcodesRegistered) {
            $shortcodesRegistered[] = $tag;
        });

        Functions\when('add_action')->justReturn(true);

        Frontend::init();

        $this->assertContains('mayo_event_form', $shortcodesRegistered);
        $this->assertContains('mayo_event_list', $shortcodesRegistered);
        $this->assertContains('mayo_announcement', $shortcodesRegistered);
        $this->assertContains('mayo_announcement_form', $shortcodesRegistered);
        $this->assertContains('mayo_subscribe', $shortcodesRegistered);
    }

    /**
     * Test render_event_form returns div with correct attributes
     */
    public function testRenderEventFormReturnsDiv(): void {
        Functions\when('shortcode_atts')->alias(function($defaults, $atts) {
            return array_merge($defaults, $atts);
        });

        $result = Frontend::render_event_form([]);

        $this->assertStringContainsString('<div id="mayo-event-form"', $result);
        $this->assertStringContainsString('data-settings=', $result);
        $this->assertStringContainsString('data-categories=', $result);
        $this->assertStringContainsString('data-tags=', $result);
    }

    /**
     * Test render_event_form with custom attributes
     */
    public function testRenderEventFormWithCustomAttributes(): void {
        Functions\when('shortcode_atts')->alias(function($defaults, $atts) {
            return array_merge($defaults, $atts);
        });

        $result = Frontend::render_event_form([
            'categories' => 'events,news',
            'tags' => 'featured',
            'additional_required_fields' => 'location_name,email'
        ]);

        $this->assertStringContainsString('data-categories="events,news"', $result);
        $this->assertStringContainsString('data-tags="featured"', $result);
    }

    /**
     * Test render_event_list returns div with instance ID
     */
    public function testRenderEventListReturnsDiv(): void {
        Functions\when('shortcode_atts')->alias(function($defaults, $atts) {
            return array_merge($defaults, $atts);
        });

        $result = Frontend::render_event_list([]);

        $this->assertStringContainsString('<div id="mayo-event-list-', $result);
        $this->assertStringContainsString('data-instance=', $result);
    }

    /**
     * Test render_event_list with custom attributes
     */
    public function testRenderEventListWithCustomAttributes(): void {
        Functions\when('shortcode_atts')->alias(function($defaults, $atts) {
            return array_merge($defaults, $atts);
        });

        $result = Frontend::render_event_list([
            'time_format' => '24hour',
            'per_page' => '20',
            'categories' => 'meetings',
            'order' => 'DESC',
            'view' => 'calendar'
        ]);

        $this->assertStringContainsString('<div id="mayo-event-list-', $result);
    }

    /**
     * Test render_event_list in widget context
     */
    public function testRenderEventListInWidgetContext(): void {
        Functions\when('shortcode_atts')->alias(function($defaults, $atts) {
            return array_merge($defaults, $atts);
        });
        Functions\when('current_filter')->justReturn('widget_text');

        $result = Frontend::render_event_list([]);

        $this->assertStringContainsString('class="mayo-widget-list"', $result);
    }

    /**
     * Test render_announcement returns div with instance ID
     */
    public function testRenderAnnouncementReturnsDiv(): void {
        Functions\when('shortcode_atts')->alias(function($defaults, $atts) {
            return array_merge($defaults, $atts);
        });

        $result = Frontend::render_announcement([]);

        $this->assertStringContainsString('<div class="mayo-announcement-container"', $result);
        $this->assertStringContainsString('data-instance=', $result);
    }

    /**
     * Test render_announcement with custom attributes
     */
    public function testRenderAnnouncementWithCustomAttributes(): void {
        Functions\when('shortcode_atts')->alias(function($defaults, $atts) {
            return array_merge($defaults, $atts);
        });

        $result = Frontend::render_announcement([
            'mode' => 'modal',
            'priority' => 'high',
            'categories' => 'urgent',
            'orderby' => 'title',
            'order' => 'ASC'
        ]);

        $this->assertStringContainsString('data-instance=', $result);
    }

    /**
     * Test render_subscribe_form returns div
     */
    public function testRenderSubscribeFormReturnsDiv(): void {
        $result = Frontend::render_subscribe_form([]);

        $this->assertStringContainsString('<div class="mayo-subscribe-container"', $result);
        $this->assertStringContainsString('data-instance=', $result);
    }

    /**
     * Test render_announcement_form returns div with attributes
     */
    public function testRenderAnnouncementFormReturnsDiv(): void {
        Functions\when('shortcode_atts')->alias(function($defaults, $atts) {
            return array_merge($defaults, $atts);
        });

        $result = Frontend::render_announcement_form([]);

        $this->assertStringContainsString('<div id="mayo-announcement-form"', $result);
        $this->assertStringContainsString('data-settings=', $result);
        $this->assertStringContainsString('data-categories=', $result);
        $this->assertStringContainsString('data-tags=', $result);
    }

    /**
     * Test render_announcement_form with custom attributes
     */
    public function testRenderAnnouncementFormWithCustomAttributes(): void {
        Functions\when('shortcode_atts')->alias(function($defaults, $atts) {
            return array_merge($defaults, $atts);
        });

        $result = Frontend::render_announcement_form([
            'categories' => 'announcements',
            'tags' => 'important',
            'show_flyer' => 'true'
        ]);

        $this->assertStringContainsString('data-categories="announcements"', $result);
        $this->assertStringContainsString('data-tags="important"', $result);
    }

    /**
     * Test enqueue_scripts when post has shortcode
     */
    public function testEnqueueScriptsWhenPostHasShortcode(): void {
        global $wp_registered_sidebars, $wp_registered_widgets;
        $wp_registered_sidebars = [];
        $wp_registered_widgets = [];

        $post = $this->createMockPost([
            'ID' => 1,
            'post_content' => '[mayo_event_list]'
        ]);

        Functions\when('get_post')->justReturn($post);
        Functions\when('has_shortcode')->alias(function($content, $tag) {
            return $tag === 'mayo_event_list';
        });

        $enqueuedScripts = [];
        Functions\when('wp_enqueue_script')->alias(function($handle) use (&$enqueuedScripts) {
            $enqueuedScripts[] = $handle;
        });

        Frontend::enqueue_scripts();

        $this->assertContains('mayo-public', $enqueuedScripts);
    }

    /**
     * Test enqueue_scripts when no shortcode present
     */
    public function testEnqueueScriptsWhenNoShortcode(): void {
        global $wp_registered_sidebars, $wp_registered_widgets;
        $wp_registered_sidebars = [];
        $wp_registered_widgets = [];

        $post = $this->createMockPost([
            'ID' => 1,
            'post_content' => 'Regular content'
        ]);

        Functions\when('get_post')->justReturn($post);
        Functions\when('has_shortcode')->justReturn(false);
        Functions\when('is_post_type_archive')->justReturn(false);
        Functions\when('is_singular')->justReturn(false);

        // Should still call wp_localize_script for API settings
        $localizedScripts = [];
        Functions\when('wp_localize_script')->alias(function($handle, $name, $data) use (&$localizedScripts) {
            $localizedScripts[$name] = $data;
        });

        Frontend::enqueue_scripts();

        $this->assertArrayHasKey('mayoApiSettings', $localizedScripts);
    }

    /**
     * Test enqueue_scripts on mayo_event archive
     */
    public function testEnqueueScriptsOnEventArchive(): void {
        global $wp_registered_sidebars, $wp_registered_widgets;
        $wp_registered_sidebars = [];
        $wp_registered_widgets = [];

        Functions\when('get_post')->justReturn(null);
        Functions\when('is_post_type_archive')->alias(function($type) {
            return $type === 'mayo_event';
        });

        $enqueuedScripts = [];
        Functions\when('wp_enqueue_script')->alias(function($handle) use (&$enqueuedScripts) {
            $enqueuedScripts[] = $handle;
        });

        Frontend::enqueue_scripts();

        $this->assertContains('mayo-public', $enqueuedScripts);
    }

    /**
     * Test enqueue_scripts on single mayo_event
     */
    public function testEnqueueScriptsOnSingleEvent(): void {
        global $wp_registered_sidebars, $wp_registered_widgets;
        $wp_registered_sidebars = [];
        $wp_registered_widgets = [];

        Functions\when('get_post')->justReturn(null);
        Functions\when('is_post_type_archive')->justReturn(false);
        Functions\when('is_singular')->alias(function($type) {
            return $type === 'mayo_event';
        });

        $enqueuedScripts = [];
        Functions\when('wp_enqueue_script')->alias(function($handle) use (&$enqueuedScripts) {
            $enqueuedScripts[] = $handle;
        });

        Frontend::enqueue_scripts();

        $this->assertContains('mayo-public', $enqueuedScripts);
    }

    /**
     * Test multiple shortcode instances get unique IDs
     */
    public function testMultipleShortcodeInstancesGetUniqueIds(): void {
        Functions\when('shortcode_atts')->alias(function($defaults, $atts) {
            return array_merge($defaults, $atts);
        });

        // Reset static instance counter by calling multiple times
        $result1 = Frontend::render_subscribe_form([]);
        $result2 = Frontend::render_subscribe_form([]);

        // Extract instance numbers
        preg_match('/data-instance="(\d+)"/', $result1, $matches1);
        preg_match('/data-instance="(\d+)"/', $result2, $matches2);

        // Instances should be different
        $this->assertNotEquals($matches1[1], $matches2[1]);
    }

    /**
     * Test is_shortcode_present_in_widgets returns false when no sidebars
     */
    public function testIsShortcodePresentInWidgetsReturnsFalseWhenNoSidebars(): void {
        global $wp_registered_sidebars;
        $wp_registered_sidebars = [];

        $method = new \ReflectionMethod(Frontend::class, 'is_shortcode_present_in_widgets');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'mayo_event_list');

        $this->assertFalse($result);
    }

    /**
     * Test is_shortcode_present_in_widgets returns false for inactive sidebar
     */
    public function testIsShortcodePresentInWidgetsReturnsFalseForInactiveSidebar(): void {
        global $wp_registered_sidebars;
        $wp_registered_sidebars = ['sidebar-1' => ['id' => 'sidebar-1', 'name' => 'Test Sidebar']];

        Functions\when('is_active_sidebar')->justReturn(false);

        $method = new \ReflectionMethod(Frontend::class, 'is_shortcode_present_in_widgets');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'mayo_event_list');

        $this->assertFalse($result);
    }

    /**
     * Test is_shortcode_present_in_widgets returns true when shortcode found
     */
    public function testIsShortcodePresentInWidgetsReturnsTrueWhenShortcodeFound(): void {
        global $wp_registered_sidebars, $wp_registered_widgets;

        $widget_obj = new \stdClass();
        $widget_obj->id_base = 'text';

        $wp_registered_sidebars = ['sidebar-1' => ['id' => 'sidebar-1', 'name' => 'Test Sidebar']];
        $wp_registered_widgets = [
            'text-1' => [
                'callback' => [$widget_obj, 'widget']
            ]
        ];

        Functions\when('is_active_sidebar')->justReturn(true);
        Functions\when('wp_get_sidebars_widgets')->justReturn([
            'sidebar-1' => ['text-1']
        ]);
        Functions\when('get_option')->justReturn([
            1 => ['content' => '[mayo_event_list]']
        ]);
        Functions\when('has_shortcode')->justReturn(true);

        $method = new \ReflectionMethod(Frontend::class, 'is_shortcode_present_in_widgets');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'mayo_event_list');

        $this->assertTrue($result);
    }

    /**
     * Test is_shortcode_present_in_widgets returns false when shortcode not in content
     */
    public function testIsShortcodePresentInWidgetsReturnsFalseWhenNoShortcode(): void {
        global $wp_registered_sidebars, $wp_registered_widgets;

        $widget_obj = new \stdClass();
        $widget_obj->id_base = 'text';

        $wp_registered_sidebars = ['sidebar-1' => ['id' => 'sidebar-1', 'name' => 'Test Sidebar']];
        $wp_registered_widgets = [
            'text-1' => [
                'callback' => [$widget_obj, 'widget']
            ]
        ];

        Functions\when('is_active_sidebar')->justReturn(true);
        Functions\when('wp_get_sidebars_widgets')->justReturn([
            'sidebar-1' => ['text-1']
        ]);
        Functions\when('get_option')->justReturn([
            1 => ['content' => 'Just some text']
        ]);
        Functions\when('has_shortcode')->justReturn(false);

        $method = new \ReflectionMethod(Frontend::class, 'is_shortcode_present_in_widgets');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'mayo_event_list');

        $this->assertFalse($result);
    }

    /**
     * Test enqueue_scripts when shortcode present in widgets
     */
    public function testEnqueueScriptsWhenShortcodeInWidgets(): void {
        global $wp_registered_sidebars, $wp_registered_widgets;

        $widget_obj = new \stdClass();
        $widget_obj->id_base = 'text';

        $wp_registered_sidebars = ['sidebar-1' => ['id' => 'sidebar-1', 'name' => 'Test Sidebar']];
        $wp_registered_widgets = [
            'text-1' => [
                'callback' => [$widget_obj, 'widget']
            ]
        ];

        $enqueuedScripts = [];

        Functions\when('get_post')->justReturn(null);
        Functions\when('is_active_sidebar')->justReturn(true);
        Functions\when('wp_get_sidebars_widgets')->justReturn([
            'sidebar-1' => ['text-1']
        ]);
        Functions\when('get_option')->justReturn([
            1 => ['content' => '[mayo_event_list]']
        ]);
        Functions\when('has_shortcode')->justReturn(true);
        Functions\when('is_post_type_archive')->justReturn(false);
        Functions\when('is_singular')->justReturn(false);
        Functions\when('wp_enqueue_script')->alias(function($name) use (&$enqueuedScripts) {
            $enqueuedScripts[] = $name;
        });
        Functions\when('wp_enqueue_style')->justReturn(true);
        Functions\when('plugin_dir_url')->justReturn('https://example.com/plugins/mayo/');
        Functions\when('wp_localize_script')->justReturn(true);
        Functions\when('rest_url')->justReturn('https://example.com/wp-json/');
        Functions\when('esc_url_raw')->alias(function($url) { return $url; });
        Functions\when('wp_create_nonce')->justReturn('test-nonce');

        Frontend::enqueue_scripts();

        $this->assertContains('mayo-public', $enqueuedScripts);
    }
}
