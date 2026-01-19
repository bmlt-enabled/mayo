<?php

namespace BmltEnabled\Mayo\Tests\Unit\Rest\Helpers;

use BmltEnabled\Mayo\Tests\Unit\TestCase;
use BmltEnabled\Mayo\Rest\Helpers\PreferencesSanitizer;
use Brain\Monkey\Functions;

class PreferencesSanitizerTest extends TestCase {

    /**
     * Test sanitize with null input returns WP_Error
     */
    public function testSanitizeWithNullReturnsError(): void {
        $result = PreferencesSanitizer::sanitize(null);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('invalid_preferences', $result->get_error_code());
    }

    /**
     * Test sanitize with non-array input returns WP_Error
     */
    public function testSanitizeWithStringReturnsError(): void {
        $result = PreferencesSanitizer::sanitize('not an array');
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('invalid_preferences', $result->get_error_code());
    }

    /**
     * Test sanitize with empty array returns clean preferences
     */
    public function testSanitizeWithEmptyArrayReturnsCleanPreferences(): void {
        $result = PreferencesSanitizer::sanitize([]);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('categories', $result);
        $this->assertArrayHasKey('tags', $result);
        $this->assertArrayHasKey('service_bodies', $result);
        $this->assertEmpty($result['categories']);
        $this->assertEmpty($result['tags']);
        $this->assertEmpty($result['service_bodies']);
    }

    /**
     * Test sanitize with valid categories
     */
    public function testSanitizeWithValidCategories(): void {
        $result = PreferencesSanitizer::sanitize([
            'categories' => [1, 2, '3', '4']
        ]);

        $this->assertCount(4, $result['categories']);
        $this->assertContains(1, $result['categories']);
        $this->assertContains(2, $result['categories']);
        $this->assertContains(3, $result['categories']);
        $this->assertContains(4, $result['categories']);
    }

    /**
     * Test sanitize with valid tags
     */
    public function testSanitizeWithValidTags(): void {
        $result = PreferencesSanitizer::sanitize([
            'tags' => [10, 20, '30']
        ]);

        $this->assertCount(3, $result['tags']);
        $this->assertContains(10, $result['tags']);
        $this->assertContains(20, $result['tags']);
        $this->assertContains(30, $result['tags']);
    }

    /**
     * Test sanitize with valid service bodies
     */
    public function testSanitizeWithValidServiceBodies(): void {
        $result = PreferencesSanitizer::sanitize([
            'service_bodies' => ['sb1', 'sb2', 'sb3']
        ]);

        $this->assertCount(3, $result['service_bodies']);
        $this->assertContains('sb1', $result['service_bodies']);
        $this->assertContains('sb2', $result['service_bodies']);
        $this->assertContains('sb3', $result['service_bodies']);
    }

    /**
     * Test sanitize with all fields
     */
    public function testSanitizeWithAllFields(): void {
        $result = PreferencesSanitizer::sanitize([
            'categories' => [1, 2],
            'tags' => [3, 4],
            'service_bodies' => ['a', 'b']
        ]);

        $this->assertCount(2, $result['categories']);
        $this->assertCount(2, $result['tags']);
        $this->assertCount(2, $result['service_bodies']);
    }

    /**
     * Test sanitize ignores non-array categories
     */
    public function testSanitizeIgnoresNonArrayCategories(): void {
        $result = PreferencesSanitizer::sanitize([
            'categories' => 'not an array'
        ]);

        $this->assertEmpty($result['categories']);
    }

    /**
     * Test has_selections returns false for empty preferences
     */
    public function testHasSelectionsReturnsFalseForEmpty(): void {
        $result = PreferencesSanitizer::has_selections([
            'categories' => [],
            'tags' => [],
            'service_bodies' => []
        ]);

        $this->assertFalse($result);
    }

    /**
     * Test has_selections returns true with categories
     */
    public function testHasSelectionsReturnsTrueWithCategories(): void {
        $result = PreferencesSanitizer::has_selections([
            'categories' => [1],
            'tags' => [],
            'service_bodies' => []
        ]);

        $this->assertTrue($result);
    }

    /**
     * Test has_selections returns true with tags
     */
    public function testHasSelectionsReturnsTrueWithTags(): void {
        $result = PreferencesSanitizer::has_selections([
            'categories' => [],
            'tags' => [1],
            'service_bodies' => []
        ]);

        $this->assertTrue($result);
    }

    /**
     * Test has_selections returns true with service bodies
     */
    public function testHasSelectionsReturnsTrueWithServiceBodies(): void {
        $result = PreferencesSanitizer::has_selections([
            'categories' => [],
            'tags' => [],
            'service_bodies' => ['sb1']
        ]);

        $this->assertTrue($result);
    }

    /**
     * Test has_selections returns false for non-array
     */
    public function testHasSelectionsReturnsFalseForNonArray(): void {
        $result = PreferencesSanitizer::has_selections('not an array');
        $this->assertFalse($result);
    }

    /**
     * Test sanitize_and_validate with valid preferences
     */
    public function testSanitizeAndValidateWithValidPreferences(): void {
        $result = PreferencesSanitizer::sanitize_and_validate([
            'categories' => [1, 2],
            'tags' => [],
            'service_bodies' => []
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('categories', $result);
        $this->assertCount(2, $result['categories']);
    }

    /**
     * Test sanitize_and_validate with null returns error response
     */
    public function testSanitizeAndValidateWithNullReturnsErrorResponse(): void {
        $result = PreferencesSanitizer::sanitize_and_validate(null);

        $this->assertInstanceOf(\WP_REST_Response::class, $result);
        $this->assertEquals(400, $result->get_status());
        $data = $result->get_data();
        $this->assertFalse($data['success']);
        $this->assertEquals('invalid_preferences', $data['code']);
    }

    /**
     * Test sanitize_and_validate with no selections returns error
     */
    public function testSanitizeAndValidateWithNoSelectionsReturnsError(): void {
        $result = PreferencesSanitizer::sanitize_and_validate([
            'categories' => [],
            'tags' => [],
            'service_bodies' => []
        ]);

        $this->assertInstanceOf(\WP_REST_Response::class, $result);
        $this->assertEquals(400, $result->get_status());
        $data = $result->get_data();
        $this->assertFalse($data['success']);
        $this->assertEquals('no_preferences', $data['code']);
    }
}
