<?php

namespace BmltEnabled\Mayo\Widgets;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AnnouncementWidget extends \WP_Widget {

    public function __construct() {
        parent::__construct(
            'mayo_announcement_widget',
            __('Mayo Event Announcements', 'mayo-events-manager'),
            [
                'description' => __('Display event-based announcements as a banner or modal.', 'mayo-events-manager'),
                'classname' => 'mayo-announcement-widget',
            ]
        );
    }

    /**
     * Front-end display of widget.
     */
    public function widget( $args, $instance ) {
        static $widget_instance = 0;
        $widget_instance++;

        $mode = ! empty( $instance['mode'] ) ? $instance['mode'] : 'banner';
        $categories = ! empty( $instance['categories'] ) ? $instance['categories'] : '';
        $tags = ! empty( $instance['tags'] ) ? $instance['tags'] : '';
        $time_format = ! empty( $instance['time_format'] ) ? $instance['time_format'] : '12hour';
        $background_color = ! empty( $instance['background_color'] ) ? $instance['background_color'] : '';
        $text_color = ! empty( $instance['text_color'] ) ? $instance['text_color'] : '';
        $orderby = ! empty( $instance['orderby'] ) ? $instance['orderby'] : 'date';
        $order = ! empty( $instance['order'] ) ? $instance['order'] : '';

        // Enqueue scripts and styles
        wp_enqueue_script('mayo-public');
        wp_enqueue_style('mayo-public');

        // Create unique settings for this widget instance
        $settings_key = "mayoAnnouncementSettings_widget_$widget_instance";
        wp_localize_script('mayo-public', $settings_key, [
            'mode' => $mode,
            'categories' => $categories,
            'tags' => $tags,
            'timeFormat' => $time_format,
            'backgroundColor' => $background_color,
            'textColor' => $text_color,
            'orderBy' => $orderby,
            'order' => strtoupper($order),
        ]);

        echo $args['before_widget'];

        printf(
            '<div class="mayo-announcement-container" data-instance="widget_%d"></div>',
            $widget_instance
        );

        echo $args['after_widget'];
    }

    /**
     * Back-end widget form.
     */
    public function form( $instance ) {
        $mode = ! empty( $instance['mode'] ) ? $instance['mode'] : 'banner';
        $categories = ! empty( $instance['categories'] ) ? $instance['categories'] : '';
        $tags = ! empty( $instance['tags'] ) ? $instance['tags'] : '';
        $time_format = ! empty( $instance['time_format'] ) ? $instance['time_format'] : '12hour';
        $orderby = ! empty( $instance['orderby'] ) ? $instance['orderby'] : 'date';
        $order = ! empty( $instance['order'] ) ? $instance['order'] : '';
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'mode' ) ); ?>">
                <?php esc_html_e( 'Display Mode:', 'mayo-events-manager' ); ?>
            </label>
            <select
                class="widefat"
                id="<?php echo esc_attr( $this->get_field_id( 'mode' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'mode' ) ); ?>"
            >
                <option value="banner" <?php selected( $mode, 'banner' ); ?>>
                    <?php esc_html_e( 'Banner (sticky top)', 'mayo-events-manager' ); ?>
                </option>
                <option value="modal" <?php selected( $mode, 'modal' ); ?>>
                    <?php esc_html_e( 'Modal (popup)', 'mayo-events-manager' ); ?>
                </option>
            </select>
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'categories' ) ); ?>">
                <?php esc_html_e( 'Categories (comma-separated slugs):', 'mayo-events-manager' ); ?>
            </label>
            <input
                class="widefat"
                id="<?php echo esc_attr( $this->get_field_id( 'categories' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'categories' ) ); ?>"
                type="text"
                value="<?php echo esc_attr( $categories ); ?>"
                placeholder="<?php esc_attr_e( 'e.g., announcements, alerts', 'mayo-events-manager' ); ?>"
            />
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'tags' ) ); ?>">
                <?php esc_html_e( 'Tags (comma-separated slugs):', 'mayo-events-manager' ); ?>
            </label>
            <input
                class="widefat"
                id="<?php echo esc_attr( $this->get_field_id( 'tags' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'tags' ) ); ?>"
                type="text"
                value="<?php echo esc_attr( $tags ); ?>"
                placeholder="<?php esc_attr_e( 'e.g., featured, urgent', 'mayo-events-manager' ); ?>"
            />
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'time_format' ) ); ?>">
                <?php esc_html_e( 'Time Format:', 'mayo-events-manager' ); ?>
            </label>
            <select
                class="widefat"
                id="<?php echo esc_attr( $this->get_field_id( 'time_format' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'time_format' ) ); ?>"
            >
                <option value="12hour" <?php selected( $time_format, '12hour' ); ?>>
                    <?php esc_html_e( '12-hour (e.g., 2:30 PM)', 'mayo-events-manager' ); ?>
                </option>
                <option value="24hour" <?php selected( $time_format, '24hour' ); ?>>
                    <?php esc_html_e( '24-hour (e.g., 14:30)', 'mayo-events-manager' ); ?>
                </option>
            </select>
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'background_color' ) ); ?>">
                <?php esc_html_e( 'Background Color (hex):', 'mayo-events-manager' ); ?>
            </label>
            <input
                class="widefat"
                id="<?php echo esc_attr( $this->get_field_id( 'background_color' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'background_color' ) ); ?>"
                type="text"
                value="<?php echo esc_attr( ! empty( $instance['background_color'] ) ? $instance['background_color'] : '' ); ?>"
                placeholder="#0073aa"
            />
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'text_color' ) ); ?>">
                <?php esc_html_e( 'Text Color (hex):', 'mayo-events-manager' ); ?>
            </label>
            <input
                class="widefat"
                id="<?php echo esc_attr( $this->get_field_id( 'text_color' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'text_color' ) ); ?>"
                type="text"
                value="<?php echo esc_attr( ! empty( $instance['text_color'] ) ? $instance['text_color'] : '' ); ?>"
                placeholder="#ffffff"
            />
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'orderby' ) ); ?>">
                <?php esc_html_e( 'Sort By:', 'mayo-events-manager' ); ?>
            </label>
            <select
                class="widefat"
                id="<?php echo esc_attr( $this->get_field_id( 'orderby' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'orderby' ) ); ?>"
            >
                <option value="date" <?php selected( $orderby, 'date' ); ?>>
                    <?php esc_html_e( 'Display Start Date', 'mayo-events-manager' ); ?>
                </option>
                <option value="title" <?php selected( $orderby, 'title' ); ?>>
                    <?php esc_html_e( 'Title', 'mayo-events-manager' ); ?>
                </option>
                <option value="created" <?php selected( $orderby, 'created' ); ?>>
                    <?php esc_html_e( 'Created Date', 'mayo-events-manager' ); ?>
                </option>
            </select>
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'order' ) ); ?>">
                <?php esc_html_e( 'Order:', 'mayo-events-manager' ); ?>
            </label>
            <select
                class="widefat"
                id="<?php echo esc_attr( $this->get_field_id( 'order' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'order' ) ); ?>"
            >
                <option value="" <?php selected( $order, '' ); ?>>
                    <?php esc_html_e( 'Default', 'mayo-events-manager' ); ?>
                </option>
                <option value="ASC" <?php selected( $order, 'ASC' ); ?>>
                    <?php esc_html_e( 'Ascending', 'mayo-events-manager' ); ?>
                </option>
                <option value="DESC" <?php selected( $order, 'DESC' ); ?>>
                    <?php esc_html_e( 'Descending', 'mayo-events-manager' ); ?>
                </option>
            </select>
        </p>
        <p class="description">
            <?php esc_html_e( "Events will show as announcements when today's date is between the event's start and end dates.", 'mayo-events-manager' ); ?>
        </p>
        <?php
    }

    /**
     * Sanitize widget form values as they are saved.
     */
    public function update( $new_instance, $old_instance ) {
        $instance = [];
        $instance['mode'] = ( ! empty( $new_instance['mode'] ) ) ? sanitize_text_field( $new_instance['mode'] ) : 'banner';
        $instance['categories'] = ( ! empty( $new_instance['categories'] ) ) ? sanitize_text_field( $new_instance['categories'] ) : '';
        $instance['tags'] = ( ! empty( $new_instance['tags'] ) ) ? sanitize_text_field( $new_instance['tags'] ) : '';
        $instance['time_format'] = ( ! empty( $new_instance['time_format'] ) ) ? sanitize_text_field( $new_instance['time_format'] ) : '12hour';
        $instance['background_color'] = ( ! empty( $new_instance['background_color'] ) ) ? sanitize_hex_color( $new_instance['background_color'] ) : '';
        $instance['text_color'] = ( ! empty( $new_instance['text_color'] ) ) ? sanitize_hex_color( $new_instance['text_color'] ) : '';
        $instance['orderby'] = ( ! empty( $new_instance['orderby'] ) ) ? sanitize_text_field( $new_instance['orderby'] ) : 'date';
        $instance['order'] = ( ! empty( $new_instance['order'] ) ) ? sanitize_text_field( $new_instance['order'] ) : '';
        return $instance;
    }
}
