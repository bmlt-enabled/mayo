<?php

namespace BmltEnabled\Mayo;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Announcement {
    public static function init() {
        // Register post type on init
        add_action('init', [__CLASS__, 'register_post_type'], 0);
        add_action('init', [__CLASS__, 'register_meta_fields']);

        // Custom columns
        add_filter('manage_mayo_announcement_posts_columns', [__CLASS__, 'set_custom_columns']);
        add_action('manage_mayo_announcement_posts_custom_column', [__CLASS__, 'render_custom_columns'], 10, 2);
        add_filter('manage_edit-mayo_announcement_sortable_columns', [__CLASS__, 'set_sortable_columns']);
        add_filter('posts_orderby', [__CLASS__, 'handle_custom_orderby'], 10, 2);

        // Custom filters
        add_action('restrict_manage_posts', [__CLASS__, 'add_announcement_status_filter']);
        add_filter('pre_get_posts', [__CLASS__, 'filter_announcements_by_status']);
    }

    public static function register_post_type() {
        register_post_type('mayo_announcement', [
            'labels' => [
                'name' => 'Announcements',
                'singular_name' => 'Announcement',
                'add_new' => 'Add New Announcement',
                'add_new_item' => 'Add New Announcement',
                'edit_item' => 'Edit Announcement',
                'view_item' => 'View Announcement',
                'all_items' => 'Announcements',
                'search_items' => 'Search Announcements',
                'not_found' => 'No announcements found',
                'not_found_in_trash' => 'No announcements found in trash',
            ],
            'public' => true,
            'show_in_menu' => 'mayo-events',
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'taxonomies' => ['category', 'post_tag'],
            'has_archive' => false,
            'publicly_queryable' => true,
            'rewrite' => [
                'slug' => 'announcement',
                'with_front' => true,
            ],
            'menu_icon' => 'dashicons-megaphone',
            'show_in_rest' => true,
        ]);
    }

    public static function register_meta_fields() {
        register_post_meta('mayo_announcement', 'display_start_date', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ]);

        register_post_meta('mayo_announcement', 'display_end_date', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ]);

        register_post_meta('mayo_announcement', 'priority', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => 'normal',
            'sanitize_callback' => function($value) {
                $allowed = ['low', 'normal', 'high', 'urgent'];
                return in_array($value, $allowed) ? $value : 'normal';
            },
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ]);

        register_post_meta('mayo_announcement', 'linked_events', [
            'show_in_rest' => [
                'schema' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer']
                ]
            ],
            'single' => true,
            'type' => 'array',
            'default' => [],
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ]);

        // Future: notification settings placeholder
        register_post_meta('mayo_announcement', 'notification_settings', [
            'show_in_rest' => [
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'email_enabled' => ['type' => 'boolean'],
                        'push_enabled' => ['type' => 'boolean'],
                    ]
                ]
            ],
            'single' => true,
            'type' => 'object',
            'default' => [
                'email_enabled' => false,
                'push_enabled' => false,
            ],
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ]);
    }

    public static function set_custom_columns($columns) {
        return [
            'cb' => $columns['cb'],
            'title' => __('Title', 'mayo-events-manager'),
            'priority' => __('Priority', 'mayo-events-manager'),
            'display_window' => __('Display Window', 'mayo-events-manager'),
            'status_indicator' => __('Status', 'mayo-events-manager'),
            'linked_events' => __('Linked Events', 'mayo-events-manager'),
            'date' => $columns['date']
        ];
    }

    public static function render_custom_columns($column, $post_id) {
        switch ($column) {
            case 'priority':
                $priority = get_post_meta($post_id, 'priority', true) ?: 'normal';
                $priority_colors = [
                    'low' => '#6c757d',
                    'normal' => '#0073aa',
                    'high' => '#ff9800',
                    'urgent' => '#dc3545'
                ];
                $color = $priority_colors[$priority] ?? '#0073aa';
                echo '<span style="color: ' . esc_attr($color) . '; font-weight: 600;">' . esc_html(ucfirst($priority)) . '</span>';
                break;

            case 'display_window':
                $start_date = get_post_meta($post_id, 'display_start_date', true);
                $end_date = get_post_meta($post_id, 'display_end_date', true);

                if ($start_date || $end_date) {
                    $start_formatted = $start_date ? date_i18n('M j, Y', strtotime($start_date)) : 'Now';
                    $end_formatted = $end_date ? date_i18n('M j, Y', strtotime($end_date)) : 'Indefinite';
                    echo esc_html($start_formatted . ' - ' . $end_formatted);
                } else {
                    echo '<em>Always visible</em>';
                }
                break;

            case 'status_indicator':
                $start_date = get_post_meta($post_id, 'display_start_date', true);
                $end_date = get_post_meta($post_id, 'display_end_date', true);
                $today = current_time('Y-m-d');

                $is_active = true;
                $status_label = 'Active';
                $status_color = '#46b450';

                if ($start_date && $start_date > $today) {
                    $is_active = false;
                    $status_label = 'Scheduled';
                    $status_color = '#0073aa';
                } elseif ($end_date && $end_date < $today) {
                    $is_active = false;
                    $status_label = 'Expired';
                    $status_color = '#dc3545';
                }

                echo '<span style="color: ' . esc_attr($status_color) . '; font-weight: 600;">' . esc_html($status_label) . '</span>';
                break;

            case 'linked_events':
                $linked_events = get_post_meta($post_id, 'linked_events', true);
                if (!empty($linked_events) && is_array($linked_events)) {
                    $event_links = [];
                    foreach ($linked_events as $event_id) {
                        $event = get_post($event_id);
                        if ($event && $event->post_type === 'mayo_event') {
                            $event_links[] = '<a href="' . get_edit_post_link($event_id) . '">' . esc_html($event->post_title) . '</a>';
                        }
                    }
                    echo !empty($event_links) ? implode(', ', $event_links) : '—';
                } else {
                    echo '—';
                }
                break;
        }
    }

    public static function set_sortable_columns($columns) {
        $columns['priority'] = 'priority';
        $columns['display_window'] = 'display_start_date';
        $columns['status_indicator'] = 'display_status';
        return $columns;
    }

    public static function handle_custom_orderby($orderby, $query) {
        if (!is_admin() || !$query->is_main_query()) {
            return $orderby;
        }

        $orderby_param = $query->get('orderby');
        $order = $query->get('order', 'ASC');

        global $wpdb;

        switch ($orderby_param) {
            case 'priority':
                $orderby = "FIELD((SELECT meta_value FROM $wpdb->postmeta WHERE post_id = $wpdb->posts.ID AND meta_key = 'priority'), 'urgent', 'high', 'normal', 'low') $order";
                break;
            case 'display_start_date':
                $orderby = "(SELECT meta_value FROM $wpdb->postmeta WHERE post_id = $wpdb->posts.ID AND meta_key = 'display_start_date') $order";
                break;
        }

        return $orderby;
    }

    /**
     * Add custom dropdown filter for announcement status in admin list
     *
     * @param string $post_type The current post type
     */
    public static function add_announcement_status_filter($post_type) {
        if ($post_type !== 'mayo_announcement') {
            return;
        }

        $selected = isset($_GET['announcement_status']) ? sanitize_text_field($_GET['announcement_status']) : '';

        ?>
        <select name="announcement_status">
            <option value=""><?php echo esc_html__('All statuses', 'mayo-events-manager'); ?></option>
            <option value="active" <?php selected($selected, 'active'); ?>><?php echo esc_html__('Active', 'mayo-events-manager'); ?></option>
            <option value="scheduled" <?php selected($selected, 'scheduled'); ?>><?php echo esc_html__('Scheduled', 'mayo-events-manager'); ?></option>
            <option value="expired" <?php selected($selected, 'expired'); ?>><?php echo esc_html__('Expired', 'mayo-events-manager'); ?></option>
        </select>
        <?php
    }

    /**
     * Filter announcements by status based on the dropdown selection
     *
     * @param WP_Query $query The WordPress query object
     */
    public static function filter_announcements_by_status($query) {
        global $pagenow;

        if (!is_admin() || $pagenow !== 'edit.php' || !$query->is_main_query()) {
            return;
        }

        if (!isset($_GET['post_type']) || $_GET['post_type'] !== 'mayo_announcement') {
            return;
        }

        if (!isset($_GET['announcement_status']) || empty($_GET['announcement_status'])) {
            return;
        }

        $filter = sanitize_text_field($_GET['announcement_status']);
        $today = current_time('Y-m-d');

        $meta_query = $query->get('meta_query') ?: [];

        switch ($filter) {
            case 'active':
                // Active: start_date <= today AND (end_date >= today OR end_date is empty)
                $meta_query['relation'] = 'AND';
                $meta_query[] = [
                    'relation' => 'OR',
                    [
                        'key' => 'display_start_date',
                        'value' => $today,
                        'compare' => '<=',
                        'type' => 'DATE'
                    ],
                    [
                        'key' => 'display_start_date',
                        'compare' => 'NOT EXISTS'
                    ],
                    [
                        'key' => 'display_start_date',
                        'value' => '',
                        'compare' => '='
                    ]
                ];
                $meta_query[] = [
                    'relation' => 'OR',
                    [
                        'key' => 'display_end_date',
                        'value' => $today,
                        'compare' => '>=',
                        'type' => 'DATE'
                    ],
                    [
                        'key' => 'display_end_date',
                        'compare' => 'NOT EXISTS'
                    ],
                    [
                        'key' => 'display_end_date',
                        'value' => '',
                        'compare' => '='
                    ]
                ];
                break;

            case 'scheduled':
                // Scheduled: start_date > today
                $meta_query[] = [
                    'key' => 'display_start_date',
                    'value' => $today,
                    'compare' => '>',
                    'type' => 'DATE'
                ];
                break;

            case 'expired':
                // Expired: end_date < today
                $meta_query[] = [
                    'relation' => 'AND',
                    [
                        'key' => 'display_end_date',
                        'value' => $today,
                        'compare' => '<',
                        'type' => 'DATE'
                    ],
                    [
                        'key' => 'display_end_date',
                        'compare' => 'EXISTS'
                    ],
                    [
                        'key' => 'display_end_date',
                        'value' => '',
                        'compare' => '!='
                    ]
                ];
                break;
        }

        $query->set('meta_query', $meta_query);
    }

    /**
     * Get active announcements
     *
     * @param array $args Optional query arguments
     * @return array Array of announcement data
     */
    public static function get_active_announcements($args = []) {
        $today = current_time('Y-m-d');

        $defaults = [
            'post_type' => 'mayo_announcement',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'relation' => 'OR',
                    [
                        'key' => 'display_start_date',
                        'value' => $today,
                        'compare' => '<=',
                        'type' => 'DATE'
                    ],
                    [
                        'key' => 'display_start_date',
                        'compare' => 'NOT EXISTS'
                    ],
                    [
                        'key' => 'display_start_date',
                        'value' => '',
                        'compare' => '='
                    ]
                ],
                [
                    'relation' => 'OR',
                    [
                        'key' => 'display_end_date',
                        'value' => $today,
                        'compare' => '>=',
                        'type' => 'DATE'
                    ],
                    [
                        'key' => 'display_end_date',
                        'compare' => 'NOT EXISTS'
                    ],
                    [
                        'key' => 'display_end_date',
                        'value' => '',
                        'compare' => '='
                    ]
                ]
            ]
        ];

        $query_args = wp_parse_args($args, $defaults);
        $posts = get_posts($query_args);

        $announcements = [];
        foreach ($posts as $post) {
            $linked_events = get_post_meta($post->ID, 'linked_events', true) ?: [];
            $linked_event_data = [];

            foreach ($linked_events as $event_id) {
                $event = get_post($event_id);
                if ($event && $event->post_type === 'mayo_event') {
                    $linked_event_data[] = [
                        'id' => $event_id,
                        'title' => $event->post_title,
                        'permalink' => get_permalink($event_id),
                        'start_date' => get_post_meta($event_id, 'event_start_date', true),
                    ];
                }
            }

            $announcements[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'content' => apply_filters('the_content', $post->post_content),
                'excerpt' => get_the_excerpt($post),
                'permalink' => get_permalink($post->ID),
                'display_start_date' => get_post_meta($post->ID, 'display_start_date', true),
                'display_end_date' => get_post_meta($post->ID, 'display_end_date', true),
                'priority' => get_post_meta($post->ID, 'priority', true) ?: 'normal',
                'linked_events' => $linked_event_data,
                'featured_image' => get_the_post_thumbnail_url($post->ID, 'medium'),
            ];
        }

        // Sort by priority (urgent first, then high, normal, low)
        usort($announcements, function($a, $b) {
            $priority_order = ['urgent' => 0, 'high' => 1, 'normal' => 2, 'low' => 3];
            return ($priority_order[$a['priority']] ?? 2) - ($priority_order[$b['priority']] ?? 2);
        });

        return $announcements;
    }

    /**
     * Get announcements linked to a specific event
     *
     * @param int $event_id The event post ID
     * @return array Array of announcement data
     */
    public static function get_announcements_for_event($event_id) {
        $today = current_time('Y-m-d');

        $args = [
            'post_type' => 'mayo_announcement',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'linked_events',
                    'value' => 'i:' . intval($event_id) . ';',
                    'compare' => 'LIKE'
                ]
            ]
        ];

        $posts = get_posts($args);
        $announcements = [];

        foreach ($posts as $post) {
            // Check if currently active
            $start_date = get_post_meta($post->ID, 'display_start_date', true);
            $end_date = get_post_meta($post->ID, 'display_end_date', true);

            $is_active = true;
            if ($start_date && $start_date > $today) {
                $is_active = false;
            }
            if ($end_date && $end_date < $today) {
                $is_active = false;
            }

            $announcements[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'content' => apply_filters('the_content', $post->post_content),
                'priority' => get_post_meta($post->ID, 'priority', true) ?: 'normal',
                'is_active' => $is_active,
                'display_start_date' => $start_date,
                'display_end_date' => $end_date,
                'edit_link' => get_edit_post_link($post->ID, 'raw'),
            ];
        }

        return $announcements;
    }
}
