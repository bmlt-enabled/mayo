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

        // Send email to subscribers when announcement is published via REST API (Gutenberg)
        // Using rest_after_insert ensures taxonomies are saved before we check preferences
        add_action('rest_after_insert_mayo_announcement', [__CLASS__, 'handle_rest_insert'], 10, 3);

        // Fallback for classic editor - uses transition_post_status
        add_action('transition_post_status', [__CLASS__, 'handle_post_status_transition'], 10, 3);
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

        register_post_meta('mayo_announcement', 'display_start_time', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ]);

        register_post_meta('mayo_announcement', 'display_end_time', [
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

        // New unified linked event refs that supports both local and external events
        register_post_meta('mayo_announcement', 'linked_event_refs', [
            'show_in_rest' => [
                'schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => ['local', 'external']],
                            'id' => ['type' => 'integer'],
                            'source_id' => ['type' => 'string'],
                        ],
                        'required' => ['type', 'id']
                    ]
                ]
            ],
            'single' => true,
            'type' => 'array',
            'default' => [],
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ]);

        // Service body association
        register_post_meta('mayo_announcement', 'service_body', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
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
            'service_body' => __('Service Body', 'mayo-events-manager'),
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

            case 'service_body':
                $service_body_id = get_post_meta($post_id, 'service_body', true);
                if ($service_body_id === '0') {
                    echo esc_html('Unaffiliated (0)');
                } elseif (empty($service_body_id)) {
                    echo '—';
                } else {
                    // Get the service body name from the BMLT root server
                    $settings = get_option('mayo_settings', []);
                    $bmlt_root_server = $settings['bmlt_root_server'] ?? '';
                    $found = false;

                    if (!empty($bmlt_root_server)) {
                        $response = wp_remote_get($bmlt_root_server . '/client_interface/json/?switcher=GetServiceBodies');

                        if (!is_wp_error($response)) {
                            $service_bodies = json_decode(wp_remote_retrieve_body($response), true);

                            if (is_array($service_bodies)) {
                                foreach ($service_bodies as $body) {
                                    if ($body['id'] == $service_body_id) {
                                        echo esc_html($body['name'] . ' (' . $body['id'] . ')');
                                        $found = true;
                                        break;
                                    }
                                }
                            }
                        }
                    }

                    // Fallback if we couldn't get the name
                    if (!$found) {
                        echo esc_html('Service Body (' . $service_body_id . ')');
                    }
                }
                break;

            case 'display_window':
                $start_date = get_post_meta($post_id, 'display_start_date', true);
                $start_time = get_post_meta($post_id, 'display_start_time', true);
                $end_date = get_post_meta($post_id, 'display_end_date', true);
                $end_time = get_post_meta($post_id, 'display_end_time', true);

                if ($start_date || $end_date) {
                    $start_formatted = $start_date ? date_i18n('M j, Y', strtotime($start_date)) : 'Now';
                    if ($start_date && $start_time) {
                        $start_formatted .= ' ' . date_i18n('g:i A', strtotime($start_time));
                    }
                    $end_formatted = $end_date ? date_i18n('M j, Y', strtotime($end_date)) : 'Indefinite';
                    if ($end_date && $end_time) {
                        $end_formatted .= ' ' . date_i18n('g:i A', strtotime($end_time));
                    }
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
                $linked_refs = self::get_linked_event_refs($post_id);
                if (!empty($linked_refs)) {
                    $event_links = [];
                    foreach ($linked_refs as $ref) {
                        if ($ref['type'] === 'local') {
                            $event = get_post($ref['id']);
                            if ($event && $event->post_type === 'mayo_event') {
                                $event_links[] = '<a href="' . get_edit_post_link($ref['id']) . '">' . esc_html($event->post_title) . '</a>';
                            }
                        } else if ($ref['type'] === 'external' && !empty($ref['source_id'])) {
                            // For external events, show source name badge
                            $source = self::get_external_source($ref['source_id']);
                            $source_name = $source ? ($source['name'] ?? parse_url($source['url'], PHP_URL_HOST)) : 'External';
                            $event_links[] = '<span class="mayo-external-event-badge" style="display: inline-block; background: #fff3e0; color: #e65100; padding: 2px 6px; border-radius: 3px; font-size: 11px;">Event #' . intval($ref['id']) . ' <small>(' . esc_html($source_name) . ')</small></span>';
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
            $linked_refs = self::get_linked_event_refs($post->ID);
            $linked_event_data = [];

            foreach ($linked_refs as $ref) {
                $resolved = self::resolve_event_ref($ref);
                if ($resolved) {
                    // Handle both 'link' (from external API) and 'permalink' (from local)
                    $permalink = $resolved['permalink'] ?? $resolved['link'] ?? '#';
                    // Handle title - may be string or {rendered: "..."} object from WP REST API
                    $title = $resolved['title'] ?? 'Unknown Event';
                    if (is_array($title) && isset($title['rendered'])) {
                        $title = $title['rendered'];
                    }
                    $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
                    // Handle start_date from meta object or direct field
                    $start_date = $resolved['start_date'] ?? ($resolved['meta']['event_start_date'] ?? '');
                    $linked_event_data[] = [
                        'id' => $resolved['id'],
                        'title' => $title,
                        'permalink' => $permalink,
                        'start_date' => $start_date,
                        'source' => $resolved['source'] ?? ['type' => 'local', 'id' => 'local', 'name' => 'Local'],
                    ];
                } elseif ($ref['type'] === 'external' && !empty($ref['source_id'])) {
                    // External event unavailable - include placeholder with source info
                    $source = self::get_external_source($ref['source_id']);
                    $source_name = $source ? ($source['name'] ?? parse_url($source['url'], PHP_URL_HOST)) : 'External';
                    $linked_event_data[] = [
                        'id' => $ref['id'],
                        'title' => 'Event details unavailable',
                        'permalink' => '#',
                        'start_date' => '',
                        'unavailable' => true,
                        'source' => [
                            'type' => 'external',
                            'id' => $ref['source_id'],
                            'name' => $source_name,
                        ],
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
                'display_start_time' => get_post_meta($post->ID, 'display_start_time', true),
                'display_end_date' => get_post_meta($post->ID, 'display_end_date', true),
                'display_end_time' => get_post_meta($post->ID, 'display_end_time', true),
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
                'display_start_time' => get_post_meta($post->ID, 'display_start_time', true),
                'display_end_date' => $end_date,
                'display_end_time' => get_post_meta($post->ID, 'display_end_time', true),
                'edit_link' => get_edit_post_link($post->ID, 'raw'),
            ];
        }

        return $announcements;
    }

    /**
     * Handle REST API insert for announcements (Gutenberg editor)
     * This fires AFTER taxonomies are saved, ensuring we have the latest data
     *
     * @param WP_Post         $post     Inserted or updated post object
     * @param WP_REST_Request $request  Request object
     * @param bool            $creating True when creating, false when updating
     */
    public static function handle_rest_insert($post, $request, $creating) {
        // Check if status is being changed to publish
        $new_status = $post->post_status;

        // Get the previous status from post meta we set, or assume it wasn't published
        $previous_status = get_post_meta($post->ID, '_mayo_previous_status', true);

        // Only send email when transitioning TO publish from a non-publish status
        if ($new_status === 'publish' && $previous_status !== 'publish') {
            // Mark that we've sent the email via REST to prevent duplicate from transition_post_status
            update_post_meta($post->ID, '_mayo_email_sent_via_rest', '1');
            Subscriber::send_announcement_email($post->ID);
        }

        // Store current status for next comparison
        update_post_meta($post->ID, '_mayo_previous_status', $new_status);
    }

    /**
     * Handle post status transitions for announcements (Classic editor fallback)
     * Sends email to subscribers when an announcement is first published
     *
     * @param string  $new_status New post status
     * @param string  $old_status Old post status
     * @param WP_Post $post       Post object
     */
    public static function handle_post_status_transition($new_status, $old_status, $post) {
        // Only handle mayo_announcement post type
        if ($post->post_type !== 'mayo_announcement') {
            return;
        }

        // Skip if this is a REST request - handle_rest_insert will take care of it
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }

        // Only send email when transitioning TO publish from a non-publish status
        if ($new_status === 'publish' && $old_status !== 'publish') {
            Subscriber::send_announcement_email($post->ID);
        }
    }

    /**
     * Get linked event refs for an announcement
     * Supports new linked_event_refs format with fallback to old linked_events
     *
     * @param int $post_id The announcement post ID
     * @return array Array of event reference objects
     */
    public static function get_linked_event_refs($post_id) {
        // Try new format first
        $refs = get_post_meta($post_id, 'linked_event_refs', true);
        if (!empty($refs) && is_array($refs)) {
            return $refs;
        }

        // Fall back to old format for backward compatibility
        $old_ids = get_post_meta($post_id, 'linked_events', true);
        if (!empty($old_ids) && is_array($old_ids)) {
            return array_map(function($id) {
                return ['type' => 'local', 'id' => intval($id)];
            }, $old_ids);
        }

        return [];
    }

    /**
     * Get external source configuration by ID
     *
     * @param string $source_id The external source ID
     * @return array|null Source configuration or null if not found
     */
    public static function get_external_source($source_id) {
        $sources = get_option('mayo_external_sources', []);
        foreach ($sources as $source) {
            if (isset($source['id']) && $source['id'] === $source_id) {
                return $source;
            }
        }
        return null;
    }

    /**
     * Fetch external event details from remote source
     *
     * @param string $source_id The external source ID
     * @param int    $event_id  The remote event ID
     * @return array|null Event details or null if unavailable
     */
    public static function fetch_external_event($source_id, $event_id) {
        $source = self::get_external_source($source_id);
        if (!$source || empty($source['url'])) {
            return null;
        }

        $url = trailingslashit($source['url']) . 'wp-json/event-manager/v1/events/' . intval($event_id);
        $response = wp_remote_get($url, ['timeout' => 10, 'sslverify' => true]);

        if (is_wp_error($response)) {
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return null;
        }

        $event = json_decode(wp_remote_retrieve_body($response), true);
        if (!$event || !is_array($event)) {
            return null;
        }

        // Add source metadata
        $event['source'] = [
            'type' => 'external',
            'id' => $source_id,
            'name' => $source['name'] ?? parse_url($source['url'], PHP_URL_HOST),
            'url' => parse_url($source['url'], PHP_URL_HOST),
        ];

        return $event;
    }

    /**
     * Resolve an event reference to full event details
     *
     * @param array $ref Event reference object {type, id, source_id?}
     * @return array|null Resolved event details or null if unavailable
     */
    public static function resolve_event_ref($ref) {
        if (!is_array($ref) || !isset($ref['type']) || !isset($ref['id'])) {
            return null;
        }

        if ($ref['type'] === 'local') {
            $event = get_post($ref['id']);
            if (!$event || $event->post_type !== 'mayo_event') {
                return null;
            }

            return [
                'id' => $event->ID,
                'title' => $event->post_title,
                'permalink' => get_permalink($event->ID),
                'slug' => $event->post_name,
                'start_date' => get_post_meta($event->ID, 'event_start_date', true),
                'end_date' => get_post_meta($event->ID, 'event_end_date', true),
                'start_time' => get_post_meta($event->ID, 'event_start_time', true),
                'end_time' => get_post_meta($event->ID, 'event_end_time', true),
                'location_name' => get_post_meta($event->ID, 'location_name', true),
                'location_address' => get_post_meta($event->ID, 'location_address', true),
                'source' => [
                    'type' => 'local',
                    'id' => 'local',
                    'name' => 'Local',
                ],
            ];
        }

        if ($ref['type'] === 'external' && !empty($ref['source_id'])) {
            return self::fetch_external_event($ref['source_id'], $ref['id']);
        }

        return null;
    }

    /**
     * Build permalink for an external event
     *
     * @param array $source External source configuration
     * @param string $slug Event slug
     * @return string External event URL
     */
    public static function build_external_event_permalink($source, $slug) {
        if (empty($source['url']) || empty($slug)) {
            return '#';
        }
        return trailingslashit($source['url']) . 'event/' . $slug;
    }
}
