<?php

namespace BmltEnabled\Mayo;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class RssFeed {
    public static function init() {
        add_action('init', [__CLASS__, 'register_feed']);
    }

    public static function register_feed() {
        add_feed('mayo_events', [__CLASS__, 'generate_rss_feed']);
    }

    public static function generate_rss_feed() {
        header('Content-Type: application/rss+xml; charset=' . get_option('blog_charset'), true);

        $rss_items = self::get_rss_items();

        echo '<?xml version="1.0" encoding="' . esc_html(get_option('blog_charset')) . '"?' . '>';
        ?>
        <rss version="2.0">
            <channel>
                <title><?php echo esc_html(get_bloginfo('name')); ?> - Events</title>
                <link><?php echo esc_url(get_bloginfo('url')); ?></link>
                <description><?php echo esc_html(get_bloginfo('description')); ?></description>
                <language><?php echo esc_html(get_option('rss_language')); ?></language>
                <pubDate><?php echo esc_html(mysql2date('D, d M Y H:i:s +0000', get_lastpostmodified('GMT'), false)); ?></pubDate>
                <lastBuildDate><?php echo esc_html(mysql2date('D, d M Y H:i:s +0000', get_lastpostmodified('GMT'), false)); ?></lastBuildDate>
                <generator>WordPress</generator>
                <?php foreach ($rss_items as $item): ?>
                    <item>
                        <title><?php echo esc_html($item['title']); ?></title>
                        <link><?php echo esc_url($item['link']); ?></link>
                        <description><![CDATA[<?php echo wp_kses_post($item['description']); ?>]]></description>
                        <pubDate><?php echo esc_html(mysql2date('D, d M Y H:i:s +0000', $item['pubDate'], false)); ?></pubDate>
                        <guid><?php echo esc_url($item['link']); ?></guid>
                    </item>
                <?php endforeach; ?>
            </channel>
        </rss>
        <?php
    }

    private static function get_rss_items() {
        $events = get_posts([
            'post_type' => 'mayo_event',
            'posts_per_page' => 10, // Adjust the number of events as needed
            'post_status' => 'publish',
        ]);

        $rss_items = [];
        foreach ($events as $event) {
            $rss_items[] = [
                'title' => $event->post_title,
                'link' => get_permalink($event->ID),
                'description' => apply_filters('the_content', $event->post_content),
                'pubDate' => $event->post_date_gmt,
            ];
        }

        return $rss_items;
    }
}

RssFeed::init(); 