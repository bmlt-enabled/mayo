<?php

namespace BmltEnabled\Mayo\Tests\Unit\Widgets;

use BmltEnabled\Mayo\Tests\Unit\TestCase;
use BmltEnabled\Mayo\Widgets\AnnouncementWidget;
use Brain\Monkey\Functions;

class AnnouncementWidgetTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Functions\when('wp_enqueue_script')->justReturn(true);
        Functions\when('wp_enqueue_style')->justReturn(true);
        Functions\when('wp_localize_script')->justReturn(true);
    }

    /**
     * Test widget can be instantiated
     */
    public function testWidgetCanBeInstantiated(): void {
        $widget = new AnnouncementWidget();

        $this->assertEquals('mayo_announcement_widget', $widget->id_base);
        $this->assertEquals('Mayo Event Announcements', $widget->name);
    }

    /**
     * Test widget options are set
     */
    public function testWidgetOptionsAreSet(): void {
        $widget = new AnnouncementWidget();

        $this->assertArrayHasKey('description', $widget->widget_options);
        $this->assertArrayHasKey('classname', $widget->widget_options);
        $this->assertEquals('mayo-announcement-widget', $widget->widget_options['classname']);
    }

    /**
     * Test widget method outputs div with default settings
     */
    public function testWidgetMethodOutputsDiv(): void {
        $widget = new AnnouncementWidget();
        $args = [
            'before_widget' => '<div class="widget">',
            'after_widget' => '</div>',
            'before_title' => '<h2>',
            'after_title' => '</h2>'
        ];
        $instance = [];

        ob_start();
        $widget->widget($args, $instance);
        $output = ob_get_clean();

        $this->assertStringContainsString('<div class="widget">', $output);
        $this->assertStringContainsString('mayo-announcement-container', $output);
        $this->assertStringContainsString('data-instance="widget_', $output);
        $this->assertStringContainsString('</div>', $output);
    }

    /**
     * Test widget method with custom instance settings
     */
    public function testWidgetMethodWithCustomSettings(): void {
        $widget = new AnnouncementWidget();
        $args = [
            'before_widget' => '<aside>',
            'after_widget' => '</aside>',
            'before_title' => '<h3>',
            'after_title' => '</h3>'
        ];
        $instance = [
            'mode' => 'modal',
            'categories' => 'announcements,alerts',
            'tags' => 'featured,urgent',
            'time_format' => '24hour',
            'background_color' => '#ff0000',
            'text_color' => '#ffffff',
            'orderby' => 'title',
            'order' => 'ASC'
        ];

        $localizedScripts = [];
        Functions\when('wp_localize_script')->alias(function($handle, $name, $data) use (&$localizedScripts) {
            $localizedScripts[$name] = $data;
            return true;
        });

        ob_start();
        $widget->widget($args, $instance);
        $output = ob_get_clean();

        $this->assertStringContainsString('<aside>', $output);
        $this->assertStringContainsString('mayo-announcement-container', $output);
    }

    /**
     * Test widget enqueues scripts
     */
    public function testWidgetEnqueuesScripts(): void {
        $widget = new AnnouncementWidget();
        $args = [
            'before_widget' => '',
            'after_widget' => '',
            'before_title' => '',
            'after_title' => ''
        ];
        $instance = [];

        $enqueuedScripts = [];
        $enqueuedStyles = [];

        Functions\when('wp_enqueue_script')->alias(function($handle) use (&$enqueuedScripts) {
            $enqueuedScripts[] = $handle;
            return true;
        });

        Functions\when('wp_enqueue_style')->alias(function($handle) use (&$enqueuedStyles) {
            $enqueuedStyles[] = $handle;
            return true;
        });

        ob_start();
        $widget->widget($args, $instance);
        ob_get_clean();

        $this->assertContains('mayo-public', $enqueuedScripts);
        $this->assertContains('mayo-public', $enqueuedStyles);
    }

    /**
     * Test form method outputs form fields
     */
    public function testFormMethodOutputsFields(): void {
        $widget = new AnnouncementWidget();
        $instance = [
            'mode' => 'banner',
            'categories' => '',
            'tags' => '',
            'time_format' => '12hour',
            'orderby' => 'date',
            'order' => ''
        ];

        ob_start();
        $widget->form($instance);
        $output = ob_get_clean();

        $this->assertStringContainsString('Display Mode:', $output);
        $this->assertStringContainsString('Categories', $output);
        $this->assertStringContainsString('Tags', $output);
        $this->assertStringContainsString('Time Format:', $output);
        $this->assertStringContainsString('Background Color', $output);
        $this->assertStringContainsString('Text Color', $output);
        $this->assertStringContainsString('Sort By:', $output);
        $this->assertStringContainsString('Order:', $output);
    }

    /**
     * Test form method with modal mode selected
     */
    public function testFormMethodWithModalMode(): void {
        $widget = new AnnouncementWidget();
        $instance = [
            'mode' => 'modal',
            'categories' => 'test-cat',
            'tags' => 'test-tag',
            'time_format' => '24hour'
        ];

        ob_start();
        $widget->form($instance);
        $output = ob_get_clean();

        $this->assertStringContainsString('test-cat', $output);
        $this->assertStringContainsString('test-tag', $output);
    }

    /**
     * Test update method sanitizes input
     */
    public function testUpdateMethodSanitizesInput(): void {
        $widget = new AnnouncementWidget();

        // Mock sanitize_hex_color
        Functions\when('sanitize_hex_color')->alias(function($color) {
            if (preg_match('/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/', $color)) {
                return $color;
            }
            return '';
        });

        $new_instance = [
            'mode' => 'banner',
            'categories' => 'test-cat',
            'tags' => 'test-tag',
            'time_format' => '12hour',
            'background_color' => '#ffffff',
            'text_color' => '#000000',
            'orderby' => 'date',
            'order' => 'ASC'
        ];

        $old_instance = [];

        $result = $widget->update($new_instance, $old_instance);

        $this->assertEquals('banner', $result['mode']);
        $this->assertEquals('test-cat', $result['categories']);
        $this->assertEquals('test-tag', $result['tags']);
        $this->assertEquals('12hour', $result['time_format']);
        $this->assertEquals('#ffffff', $result['background_color']);
        $this->assertEquals('#000000', $result['text_color']);
        $this->assertEquals('date', $result['orderby']);
        $this->assertEquals('ASC', $result['order']);
    }

    /**
     * Test update method with empty values
     */
    public function testUpdateMethodWithEmptyValues(): void {
        $widget = new AnnouncementWidget();

        Functions\when('sanitize_hex_color')->justReturn('');

        $new_instance = [];
        $old_instance = [];

        $result = $widget->update($new_instance, $old_instance);

        $this->assertEquals('banner', $result['mode']);
        $this->assertEquals('', $result['categories']);
        $this->assertEquals('', $result['tags']);
        $this->assertEquals('12hour', $result['time_format']);
        $this->assertEquals('', $result['background_color']);
        $this->assertEquals('', $result['text_color']);
        $this->assertEquals('date', $result['orderby']);
        $this->assertEquals('', $result['order']);
    }

    /**
     * Test update method with invalid hex color
     */
    public function testUpdateMethodWithInvalidHexColor(): void {
        $widget = new AnnouncementWidget();

        Functions\when('sanitize_hex_color')->alias(function($color) {
            if (preg_match('/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/', $color)) {
                return $color;
            }
            return '';
        });

        $new_instance = [
            'background_color' => 'invalid-color',
            'text_color' => 'not-a-hex'
        ];

        $old_instance = [];

        $result = $widget->update($new_instance, $old_instance);

        $this->assertEquals('', $result['background_color']);
        $this->assertEquals('', $result['text_color']);
    }

    /**
     * Test multiple widget instances get unique IDs
     */
    public function testMultipleWidgetInstancesGetUniqueIds(): void {
        $widget1 = new AnnouncementWidget();
        $widget2 = new AnnouncementWidget();

        $args = [
            'before_widget' => '',
            'after_widget' => '',
            'before_title' => '',
            'after_title' => ''
        ];
        $instance = [];

        ob_start();
        $widget1->widget($args, $instance);
        $output1 = ob_get_clean();

        ob_start();
        $widget2->widget($args, $instance);
        $output2 = ob_get_clean();

        // Extract instance numbers
        preg_match('/data-instance="widget_(\d+)"/', $output1, $matches1);
        preg_match('/data-instance="widget_(\d+)"/', $output2, $matches2);

        // They should be different (incrementing)
        $this->assertNotEquals($matches1[1], $matches2[1]);
    }
}
