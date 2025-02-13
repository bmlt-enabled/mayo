<?php
/**
 * Template for displaying single mayo event
 */

get_header(); ?>

<div class="mayo-single-container">
    <article id="post-<?php the_ID(); ?>" <?php post_class('mayo-single-event'); ?>>
        <div class="mayo-single-event-content">
            <?php
            $event_type = get_post_meta(get_the_ID(), 'event_type', true);
            $start_date = get_post_meta(get_the_ID(), 'event_start_date', true);
            $end_date = get_post_meta(get_the_ID(), 'event_end_date', true);
            $start_time = get_post_meta(get_the_ID(), 'event_start_time', true);
            $end_time = get_post_meta(get_the_ID(), 'event_end_time', true);
            $timezone = get_post_meta(get_the_ID(), 'timezone', true);
            $location_name = get_post_meta(get_the_ID(), 'location_name', true);
            $location_address = get_post_meta(get_the_ID(), 'location_address', true);
            $location_details = get_post_meta(get_the_ID(), 'location_details', true);
            $flyer_url = get_post_meta(get_the_ID(), 'flyer_url', true);
            $recurring_pattern = get_post_meta(get_the_ID(), 'recurring_pattern', true);
            ?>

            <header class="mayo-single-event-header">
                <h1 class="mayo-single-event-title"><?php the_title(); ?></h1>
            </header>

            <div class="mayo-single-event-meta">
                <?php if ($event_type) : ?>
                    <div class="mayo-single-event-type">
                        <h3>Event Type</h3>
                        <p><?php echo esc_html($event_type); ?></p>
                    </div>
                <?php endif; ?>

                <div class="mayo-single-event-datetime">
                    <h3>Date & Time</h3>
                    <p>
                        <strong>Start:</strong> <?php echo esc_html($start_date); ?>
                        <?php if ($start_time) echo ' at ' . esc_html($start_time); ?>
                        <?php if ($timezone) echo ' (' . esc_html($timezone) . ')'; ?>
                    </p>
                    <?php if ($end_date || $end_time) : ?>
                        <p>
                            <strong>End:</strong> 
                            <?php 
                            echo esc_html($end_date ?: $start_date);
                            if ($end_time) echo ' at ' . esc_html($end_time);
                            ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if ($recurring_pattern && $recurring_pattern['type'] !== 'none') : ?>
                    <div class="mayo-single-event-recurrence">
                        <h3>Recurring Event</h3>
                        <p>
                            <?php
                            $type = $recurring_pattern['type'];
                            $interval = $recurring_pattern['interval'];
                            echo "This event repeats ";
                            switch ($type) {
                                case 'daily':
                                    echo $interval > 1 ? "every $interval days" : "daily";
                                    break;
                                case 'weekly':
                                    echo $interval > 1 ? "every $interval weeks" : "weekly";
                                    if (!empty($recurring_pattern['weekdays'])) {
                                        $days = array_map(function($day) {
                                            $weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                                            return $weekdays[$day];
                                        }, $recurring_pattern['weekdays']);
                                        echo " on " . implode(', ', $days);
                                    }
                                    break;
                                case 'monthly':
                                    echo $interval > 1 ? "every $interval months" : "monthly";
                                    break;
                            }
                            if (!empty($recurring_pattern['endDate'])) {
                                echo " until " . esc_html($recurring_pattern['endDate']);
                            }
                            ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ($location_name || $location_address || $location_details) : ?>
                    <div class="mayo-single-event-location">
                        <h3>Location</h3>
                        <?php if ($location_name) : ?>
                            <p class="mayo-location-name"><?php echo esc_html($location_name); ?></p>
                        <?php endif; ?>
                        <?php if ($location_address) : ?>
                            <p class="mayo-location-address">
                                <a href="https://maps.google.com?q=<?php echo urlencode($location_address); ?>" 
                                   target="_blank" rel="noopener noreferrer">
                                    <?php echo esc_html($location_address); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                        <?php if ($location_details) : ?>
                            <p class="mayo-location-details"><?php echo esc_html($location_details); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="mayo-single-event-taxonomies">
                    <?php if (has_category()) : ?>
                        <div class="mayo-single-event-categories">
                            <h3>Categories</h3>
                            <?php the_category(', '); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (has_tag()) : ?>
                        <div class="mayo-single-event-tags">
                            <h3>Tags</h3>
                            <?php the_tags('', ', '); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($flyer_url) : ?>
                <div class="mayo-single-event-image">
                    <img src="<?php echo esc_url($flyer_url); ?>" alt="<?php the_title_attribute(); ?>">
                </div>
            <?php endif; ?>

            <div class="mayo-single-event-description">
                <h3>Description</h3>
                <?php the_content(); ?>
            </div>
        </div>
    </article>
</div>

<?php get_footer(); ?> 