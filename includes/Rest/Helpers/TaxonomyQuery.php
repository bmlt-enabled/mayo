<?php

namespace BmltEnabled\Mayo\Rest\Helpers;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Helper class for building taxonomy queries
 *
 * Handles category and tag filtering with support for include/exclude patterns.
 */
class TaxonomyQuery {

    /**
     * Parse categories/tags string and separate includes from excludes
     * Items prefixed with '-' are excluded, others are included
     *
     * @param string $filter_string Comma-separated taxonomy slugs (prefix with '-' to exclude)
     * @return array Array with 'include' and 'exclude' keys
     */
    public static function parse_taxonomy_filter($filter_string) {
        if (empty($filter_string)) {
            return ['include' => '', 'exclude' => ''];
        }

        $items = array_map('trim', explode(',', $filter_string));
        $include = [];
        $exclude = [];

        foreach ($items as $item) {
            if (empty($item)) {
                continue;
            }
            if (strpos($item, '-') === 0) {
                // Remove the leading '-' and add to exclude list
                $exclude[] = substr($item, 1);
            } else {
                $include[] = $item;
            }
        }

        return [
            'include' => implode(',', $include),
            'exclude' => implode(',', $exclude)
        ];
    }

    /**
     * Build taxonomy query args for categories and tags
     * Handles both inclusion and exclusion (items prefixed with '-')
     *
     * @param string $categories Comma-separated category slugs (prefix with '-' to exclude)
     * @param string $categoryRelation 'AND' or 'OR' - how to match multiple categories
     * @param string $tags Comma-separated tag slugs (prefix with '-' to exclude)
     * @return array WordPress query args with tax_query if applicable
     */
    public static function build_taxonomy_args($categories, $categoryRelation = 'OR', $tags = '') {
        $cat_filter = self::parse_taxonomy_filter($categories);
        $tag_filter = self::parse_taxonomy_filter($tags);

        $args = [];
        $tax_query = [];

        // Handle category inclusion via tax_query (supports AND/OR relation)
        if (!empty($cat_filter['include'])) {
            $include_cat_slugs = array_map('trim', explode(',', $cat_filter['include']));
            $include_cat_ids = [];
            foreach ($include_cat_slugs as $slug) {
                $term = get_term_by('slug', $slug, 'category');
                if ($term) {
                    $include_cat_ids[] = $term->term_id;
                }
            }
            if (!empty($include_cat_ids)) {
                // 'IN' = posts with ANY of these categories (OR)
                // 'AND' = posts with ALL of these categories (AND)
                $operator = strtoupper($categoryRelation) === 'AND' ? 'AND' : 'IN';
                $tax_query[] = [
                    'taxonomy' => 'category',
                    'field' => 'term_id',
                    'terms' => $include_cat_ids,
                    'operator' => $operator
                ];
            }
        }

        // Handle category exclusion via tax_query
        if (!empty($cat_filter['exclude'])) {
            $exclude_cat_slugs = array_map('trim', explode(',', $cat_filter['exclude']));
            $exclude_cat_ids = [];
            foreach ($exclude_cat_slugs as $slug) {
                $term = get_term_by('slug', $slug, 'category');
                if ($term) {
                    $exclude_cat_ids[] = $term->term_id;
                }
            }
            if (!empty($exclude_cat_ids)) {
                $tax_query[] = [
                    'taxonomy' => 'category',
                    'field' => 'term_id',
                    'terms' => $exclude_cat_ids,
                    'operator' => 'NOT IN'
                ];
            }
        }

        // Handle tag inclusion
        if (!empty($tag_filter['include'])) {
            $args['tag'] = $tag_filter['include'];
        }

        // Handle tag exclusion via tax_query
        if (!empty($tag_filter['exclude'])) {
            $exclude_tag_slugs = array_map('trim', explode(',', $tag_filter['exclude']));
            $exclude_tag_ids = [];
            foreach ($exclude_tag_slugs as $slug) {
                $term = get_term_by('slug', $slug, 'post_tag');
                if ($term) {
                    $exclude_tag_ids[] = $term->term_id;
                }
            }
            if (!empty($exclude_tag_ids)) {
                $tax_query[] = [
                    'taxonomy' => 'post_tag',
                    'field' => 'term_id',
                    'terms' => $exclude_tag_ids,
                    'operator' => 'NOT IN'
                ];
            }
        }

        // Add tax_query if we have any taxonomy conditions
        if (!empty($tax_query)) {
            $tax_query['relation'] = 'AND';
            $args['tax_query'] = $tax_query;
        }

        return $args;
    }

    /**
     * Get terms for a post
     *
     * @param \WP_Post|int $post Post object or post ID
     * @param string $taxonomy Taxonomy name
     * @return array Array of formatted term objects
     */
    public static function get_terms($post, $taxonomy) {
        $post_id = is_object($post) ? $post->ID : $post;

        $terms = wp_get_post_terms($post_id, $taxonomy, array(
            'fields' => 'all'
        ));

        if (is_wp_error($terms)) {
            return array();
        }

        return array_map(function($term) {
            return array(
                'id' => $term->term_id,
                'name' => html_entity_decode($term->name),
                'slug' => $term->slug,
                'link' => get_term_link($term)
            );
        }, $terms);
    }
}
