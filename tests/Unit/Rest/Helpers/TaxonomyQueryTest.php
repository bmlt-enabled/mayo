<?php

namespace BmltEnabled\Mayo\Tests\Unit\Rest\Helpers;

use BmltEnabled\Mayo\Tests\Unit\TestCase;
use BmltEnabled\Mayo\Rest\Helpers\TaxonomyQuery;
use Brain\Monkey\Functions;

class TaxonomyQueryTest extends TestCase {

    /**
     * Test parse_taxonomy_filter with empty string
     */
    public function testParseTaxonomyFilterWithEmpty(): void {
        $result = TaxonomyQuery::parse_taxonomy_filter('');

        $this->assertArrayHasKey('include', $result);
        $this->assertArrayHasKey('exclude', $result);
        $this->assertEquals('', $result['include']);
        $this->assertEquals('', $result['exclude']);
    }

    /**
     * Test parse_taxonomy_filter with single include
     */
    public function testParseTaxonomyFilterWithSingleInclude(): void {
        $result = TaxonomyQuery::parse_taxonomy_filter('news');

        $this->assertEquals('news', $result['include']);
        $this->assertEquals('', $result['exclude']);
    }

    /**
     * Test parse_taxonomy_filter with multiple includes
     */
    public function testParseTaxonomyFilterWithMultipleIncludes(): void {
        $result = TaxonomyQuery::parse_taxonomy_filter('news, events, updates');

        $this->assertEquals('news,events,updates', $result['include']);
        $this->assertEquals('', $result['exclude']);
    }

    /**
     * Test parse_taxonomy_filter with single exclude
     */
    public function testParseTaxonomyFilterWithSingleExclude(): void {
        $result = TaxonomyQuery::parse_taxonomy_filter('-news');

        $this->assertEquals('', $result['include']);
        $this->assertEquals('news', $result['exclude']);
    }

    /**
     * Test parse_taxonomy_filter with multiple excludes
     */
    public function testParseTaxonomyFilterWithMultipleExcludes(): void {
        $result = TaxonomyQuery::parse_taxonomy_filter('-news, -events, -updates');

        $this->assertEquals('', $result['include']);
        $this->assertEquals('news,events,updates', $result['exclude']);
    }

    /**
     * Test parse_taxonomy_filter with mixed include and exclude
     */
    public function testParseTaxonomyFilterWithMixed(): void {
        $result = TaxonomyQuery::parse_taxonomy_filter('news, -events, updates, -archive');

        $this->assertEquals('news,updates', $result['include']);
        $this->assertEquals('events,archive', $result['exclude']);
    }

    /**
     * Test parse_taxonomy_filter handles empty items
     */
    public function testParseTaxonomyFilterHandlesEmptyItems(): void {
        $result = TaxonomyQuery::parse_taxonomy_filter('news,, events,');

        $this->assertEquals('news,events', $result['include']);
    }

    /**
     * Test build_taxonomy_args with empty parameters
     */
    public function testBuildTaxonomyArgsWithEmpty(): void {
        $result = TaxonomyQuery::build_taxonomy_args('', 'OR', '');

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('tax_query', $result);
    }

    /**
     * Test build_taxonomy_args with categories
     */
    public function testBuildTaxonomyArgsWithCategories(): void {
        // Mock get_term_by
        Functions\when('get_term_by')->alias(function($field, $slug, $taxonomy) {
            if ($taxonomy === 'category') {
                return (object)['term_id' => crc32($slug) % 100];
            }
            return null;
        });

        $result = TaxonomyQuery::build_taxonomy_args('news, events', 'OR', '');

        $this->assertArrayHasKey('tax_query', $result);
        $this->assertNotEmpty($result['tax_query']);
    }

    /**
     * Test build_taxonomy_args with AND relation
     */
    public function testBuildTaxonomyArgsWithAndRelation(): void {
        Functions\when('get_term_by')->alias(function($field, $slug, $taxonomy) {
            if ($taxonomy === 'category') {
                return (object)['term_id' => crc32($slug) % 100];
            }
            return null;
        });

        $result = TaxonomyQuery::build_taxonomy_args('news, events', 'AND', '');

        $this->assertArrayHasKey('tax_query', $result);
        $found = false;
        foreach ($result['tax_query'] as $query) {
            if (is_array($query) && isset($query['operator']) && $query['operator'] === 'AND') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    /**
     * Test build_taxonomy_args with OR relation (default)
     */
    public function testBuildTaxonomyArgsWithOrRelation(): void {
        Functions\when('get_term_by')->alias(function($field, $slug, $taxonomy) {
            if ($taxonomy === 'category') {
                return (object)['term_id' => crc32($slug) % 100];
            }
            return null;
        });

        $result = TaxonomyQuery::build_taxonomy_args('news, events', 'OR', '');

        $this->assertArrayHasKey('tax_query', $result);
        $found = false;
        foreach ($result['tax_query'] as $query) {
            if (is_array($query) && isset($query['operator']) && $query['operator'] === 'IN') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    /**
     * Test build_taxonomy_args with excluded categories
     */
    public function testBuildTaxonomyArgsWithExcludedCategories(): void {
        Functions\when('get_term_by')->alias(function($field, $slug, $taxonomy) {
            return (object)['term_id' => crc32($slug) % 100];
        });

        $result = TaxonomyQuery::build_taxonomy_args('-archive', 'OR', '');

        $this->assertArrayHasKey('tax_query', $result);
        $foundNotIn = false;
        foreach ($result['tax_query'] as $query) {
            if (is_array($query) && isset($query['operator']) && $query['operator'] === 'NOT IN') {
                $foundNotIn = true;
                break;
            }
        }
        $this->assertTrue($foundNotIn);
    }

    /**
     * Test build_taxonomy_args with tags
     */
    public function testBuildTaxonomyArgsWithTags(): void {
        $result = TaxonomyQuery::build_taxonomy_args('', 'OR', 'featured');

        $this->assertArrayHasKey('tag', $result);
        $this->assertEquals('featured', $result['tag']);
    }

    /**
     * Test build_taxonomy_args with excluded tags
     */
    public function testBuildTaxonomyArgsWithExcludedTags(): void {
        Functions\when('get_term_by')->alias(function($field, $slug, $taxonomy) {
            if ($taxonomy === 'post_tag') {
                return (object)['term_id' => crc32($slug) % 100];
            }
            return null;
        });

        $result = TaxonomyQuery::build_taxonomy_args('', 'OR', '-archived');

        $this->assertArrayHasKey('tax_query', $result);
        $foundTagExclude = false;
        foreach ($result['tax_query'] as $query) {
            if (is_array($query) && isset($query['taxonomy']) && $query['taxonomy'] === 'post_tag') {
                if ($query['operator'] === 'NOT IN') {
                    $foundTagExclude = true;
                    break;
                }
            }
        }
        $this->assertTrue($foundTagExclude);
    }

    /**
     * Test build_taxonomy_args handles non-existent terms
     */
    public function testBuildTaxonomyArgsHandlesNonExistentTerms(): void {
        Functions\when('get_term_by')->justReturn(null);

        $result = TaxonomyQuery::build_taxonomy_args('nonexistent', 'OR', '');

        // Should not add tax_query if term doesn't exist
        $this->assertArrayNotHasKey('tax_query', $result);
    }

    /**
     * Test get_terms returns formatted terms
     */
    public function testGetTermsReturnsFormattedTerms(): void {
        Functions\when('wp_get_post_terms')->justReturn([
            (object)['term_id' => 1, 'name' => 'News', 'slug' => 'news'],
            (object)['term_id' => 2, 'name' => 'Events', 'slug' => 'events']
        ]);

        Functions\when('get_term_link')->alias(function($term) {
            return 'https://example.com/category/' . $term->slug;
        });

        $result = TaxonomyQuery::get_terms(123, 'category');

        $this->assertCount(2, $result);
        $this->assertEquals(1, $result[0]['id']);
        $this->assertEquals('News', $result[0]['name']);
        $this->assertEquals('news', $result[0]['slug']);
        $this->assertStringContainsString('news', $result[0]['link']);
    }

    /**
     * Test get_terms returns empty on error
     */
    public function testGetTermsReturnsEmptyOnError(): void {
        Functions\when('wp_get_post_terms')->justReturn(new \WP_Error('error', 'Failed'));

        $result = TaxonomyQuery::get_terms(123, 'category');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test get_terms handles post object
     */
    public function testGetTermsHandlesPostObject(): void {
        $post = $this->createMockPost(['ID' => 456]);

        Functions\when('wp_get_post_terms')->alias(function($post_id, $taxonomy, $args) {
            // Verify correct post ID is used
            $this->assertEquals(456, $post_id);
            return [];
        });

        TaxonomyQuery::get_terms($post, 'category');
    }

    /**
     * Test get_terms decodes HTML entities in names
     */
    public function testGetTermsDecodesHtmlEntities(): void {
        Functions\when('wp_get_post_terms')->justReturn([
            (object)['term_id' => 1, 'name' => 'News &amp; Events', 'slug' => 'news-events']
        ]);

        Functions\when('get_term_link')->justReturn('https://example.com/category/news-events');

        $result = TaxonomyQuery::get_terms(123, 'category');

        $this->assertEquals('News & Events', $result[0]['name']);
    }
}
