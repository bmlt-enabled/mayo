<?php

register_deactivation_hook(__FILE__, 'mayo_events_deactivate');

function mayo_events_deactivate() {
    // Convert any existing custom taxonomy terms to standard categories/tags
    $events = get_posts([
        'post_type' => 'mayo_event',
        'posts_per_page' => -1,
        'post_status' => 'any'
    ]);

    foreach ($events as $event) {
        // Get existing custom taxonomy terms
        $event_cats = wp_get_object_terms($event->ID, 'mayo_event_category');
        $event_tags = wp_get_object_terms($event->ID, 'mayo_event_tag');

        // Convert categories
        if (!is_wp_error($event_cats)) {
            $cat_names = wp_list_pluck($event_cats, 'name');
            wp_set_object_terms($event->ID, $cat_names, 'category');
        }

        // Convert tags
        if (!is_wp_error($event_tags)) {
            $tag_names = wp_list_pluck($event_tags, 'name');
            wp_set_object_terms($event->ID, $tag_names, 'post_tag');
        }
    }

    // Clean up custom taxonomies
    unregister_taxonomy('mayo_event_category');
    unregister_taxonomy('mayo_event_tag');
} 