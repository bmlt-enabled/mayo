<?php

namespace BmltEnabled\Mayo\Tests\Unit\Rest\Helpers;

use BmltEnabled\Mayo\Tests\Unit\TestCase;
use BmltEnabled\Mayo\Rest\Helpers\ServiceBodyLookup;
use Brain\Monkey\Functions;

class ServiceBodyLookupTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        // Clear cache before each test
        ServiceBodyLookup::clear_cache();
    }

    /**
     * Test get_name returns "Unaffiliated" for ID 0
     */
    public function testGetNameReturnsUnaffiliatedForZero(): void {
        $result = ServiceBodyLookup::get_name('0');
        $this->assertEquals('Unaffiliated', $result);
    }

    /**
     * Test get_name returns "Unknown" for empty ID
     */
    public function testGetNameReturnsUnknownForEmpty(): void {
        $result = ServiceBodyLookup::get_name('');
        $this->assertEquals('Unknown', $result);
    }

    /**
     * Test get_name returns "Unknown" for null
     */
    public function testGetNameReturnsUnknownForNull(): void {
        $result = ServiceBodyLookup::get_name(null);
        $this->assertEquals('Unknown', $result);
    }

    /**
     * Test get_all returns empty when no BMLT server configured
     */
    public function testGetAllReturnsEmptyWithNoServer(): void {
        $this->mockGetOption([
            'mayo_settings' => [
                'bmlt_root_server' => ''
            ]
        ]);

        $result = ServiceBodyLookup::get_all();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test get_all fetches from BMLT server
     */
    public function testGetAllFetchesFromBmltServer(): void {
        $this->mockGetOption([
            'mayo_settings' => [
                'bmlt_root_server' => 'https://bmlt.example.com'
            ]
        ]);

        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [
                    ['id' => '1', 'name' => 'Region 1'],
                    ['id' => '2', 'name' => 'Area 2']
                ]
            ]
        ]);

        $result = ServiceBodyLookup::get_all();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('1', $result[0]['id']);
        $this->assertEquals('Region 1', $result[0]['name']);
    }

    /**
     * Test get_all caches results
     */
    public function testGetAllCachesResults(): void {
        $this->mockGetOption([
            'mayo_settings' => [
                'bmlt_root_server' => 'https://bmlt.example.com'
            ]
        ]);

        $callCount = 0;
        Functions\when('wp_remote_get')->alias(function($url) use (&$callCount) {
            $callCount++;
            return [
                'response' => ['code' => 200],
                'body' => json_encode([['id' => '1', 'name' => 'Test']])
            ];
        });

        Functions\when('wp_remote_retrieve_body')->alias(function($response) {
            return $response['body'];
        });

        // Call twice
        ServiceBodyLookup::get_all();
        ServiceBodyLookup::get_all();

        // Should only have called the API once due to caching
        $this->assertEquals(1, $callCount);
    }

    /**
     * Test get_all returns empty on API error
     */
    public function testGetAllReturnsEmptyOnApiError(): void {
        $this->mockGetOption([
            'mayo_settings' => [
                'bmlt_root_server' => 'https://bmlt.example.com'
            ]
        ]);

        Functions\when('wp_remote_get')->justReturn(new \WP_Error('http_request_failed', 'Connection failed'));

        $result = ServiceBodyLookup::get_all();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test get_all returns empty on invalid JSON
     */
    public function testGetAllReturnsEmptyOnInvalidJson(): void {
        $this->mockGetOption([
            'mayo_settings' => [
                'bmlt_root_server' => 'https://bmlt.example.com'
            ]
        ]);

        Functions\when('wp_remote_get')->justReturn([
            'response' => ['code' => 200],
            'body' => 'not valid json'
        ]);

        Functions\when('wp_remote_retrieve_body')->alias(function($response) {
            return $response['body'];
        });

        $result = ServiceBodyLookup::get_all();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test get_name with valid service body
     */
    public function testGetNameReturnsCorrectName(): void {
        $this->mockGetOption([
            'mayo_settings' => [
                'bmlt_root_server' => 'https://bmlt.example.com'
            ]
        ]);

        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [
                    ['id' => '1', 'name' => 'Region 1'],
                    ['id' => '2', 'name' => 'Area 2'],
                    ['id' => '3', 'name' => 'District 3']
                ]
            ]
        ]);

        $result = ServiceBodyLookup::get_name('2');
        $this->assertEquals('Area 2', $result);
    }

    /**
     * Test get_name returns Unknown for non-existent ID
     */
    public function testGetNameReturnsUnknownForNonExistentId(): void {
        $this->mockGetOption([
            'mayo_settings' => [
                'bmlt_root_server' => 'https://bmlt.example.com'
            ]
        ]);

        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [
                    ['id' => '1', 'name' => 'Region 1']
                ]
            ]
        ]);

        $result = ServiceBodyLookup::get_name('999');
        $this->assertEquals('Unknown', $result);
    }

    /**
     * Test get_by_ids returns matching service bodies
     */
    public function testGetByIdsReturnsMatchingBodies(): void {
        $this->mockGetOption([
            'mayo_settings' => [
                'bmlt_root_server' => 'https://bmlt.example.com'
            ]
        ]);

        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [
                    ['id' => '1', 'name' => 'Region 1'],
                    ['id' => '2', 'name' => 'Area 2'],
                    ['id' => '3', 'name' => 'District 3']
                ]
            ]
        ]);

        $result = ServiceBodyLookup::get_by_ids(['1', '3']);

        $this->assertCount(2, $result);
        $this->assertEquals('1', $result[0]['id']);
        $this->assertEquals('Region 1', $result[0]['name']);
        $this->assertEquals('3', $result[1]['id']);
        $this->assertEquals('District 3', $result[1]['name']);
    }

    /**
     * Test get_by_ids returns empty for empty input
     */
    public function testGetByIdsReturnsEmptyForEmptyInput(): void {
        $result = ServiceBodyLookup::get_by_ids([]);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test get_all_as_map returns correct map
     */
    public function testGetAllAsMapReturnsCorrectMap(): void {
        $this->mockGetOption([
            'mayo_settings' => [
                'bmlt_root_server' => 'https://bmlt.example.com'
            ]
        ]);

        $this->mockWpRemoteGet([
            'GetServiceBodies' => [
                'code' => 200,
                'body' => [
                    ['id' => '1', 'name' => 'Region 1'],
                    ['id' => '2', 'name' => 'Area 2']
                ]
            ]
        ]);

        $result = ServiceBodyLookup::get_all_as_map();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('1', $result);
        $this->assertArrayHasKey('2', $result);
        $this->assertEquals('Region 1', $result['1']);
        $this->assertEquals('Area 2', $result['2']);
    }

    /**
     * Test clear_cache resets cache
     */
    public function testClearCacheResetsCache(): void {
        $this->mockGetOption([
            'mayo_settings' => [
                'bmlt_root_server' => 'https://bmlt.example.com'
            ]
        ]);

        $callCount = 0;
        Functions\when('wp_remote_get')->alias(function($url) use (&$callCount) {
            $callCount++;
            return [
                'response' => ['code' => 200],
                'body' => json_encode([['id' => '1', 'name' => 'Test ' . $callCount]])
            ];
        });

        Functions\when('wp_remote_retrieve_body')->alias(function($response) {
            return $response['body'];
        });

        // First call
        ServiceBodyLookup::get_all();
        $this->assertEquals(1, $callCount);

        // Clear cache
        ServiceBodyLookup::clear_cache();

        // Second call should trigger API again
        ServiceBodyLookup::get_all();
        $this->assertEquals(2, $callCount);
    }
}
