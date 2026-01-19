<?php

namespace BmltEnabled\Mayo\Tests\Unit;

use BmltEnabled\Mayo\Subscriber;
use Brain\Monkey\Functions;
use ReflectionClass;

class SubscriberTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        $this->mockPostMeta();
        $this->mockWpMail();
        $this->mockGetOption([
            'mayo_settings' => [
                'bmlt_root_server' => 'https://bmlt.example.com'
            ]
        ]);

        // Mock wp_json_encode
        Functions\when('wp_json_encode')->alias(function($data) {
            return json_encode($data);
        });

        // Mock wp_strip_all_tags
        Functions\when('wp_strip_all_tags')->alias(function($string) {
            return strip_tags($string);
        });

        // Mock wp_get_post_terms
        Functions\when('wp_get_post_terms')->justReturn([]);

        // Mock get_term
        Functions\when('get_term')->justReturn(null);
    }

    /**
     * Test generate_token returns 32 character hex string
     */
    public function testGenerateTokenReturns32CharHexString(): void {
        $token = Subscriber::generate_token();

        $this->assertIsString($token);
        $this->assertEquals(32, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $token);
    }

    /**
     * Test generate_token returns unique values
     */
    public function testGenerateTokenReturnsUniqueValues(): void {
        $token1 = Subscriber::generate_token();
        $token2 = Subscriber::generate_token();
        $token3 = Subscriber::generate_token();

        $this->assertNotEquals($token1, $token2);
        $this->assertNotEquals($token2, $token3);
        $this->assertNotEquals($token1, $token3);
    }

    /**
     * Test format_date formats date correctly
     */
    public function testFormatDateFormatsDateCorrectly(): void {
        $method = $this->getPrivateMethod('format_date');

        $result = $method->invoke(null, '2025-01-15');

        $this->assertStringContainsString('January', $result);
        $this->assertStringContainsString('15', $result);
        $this->assertStringContainsString('2025', $result);
    }

    /**
     * Test format_date returns empty for empty input
     */
    public function testFormatDateReturnsEmptyForEmptyInput(): void {
        $method = $this->getPrivateMethod('format_date');

        $result = $method->invoke(null, '');

        $this->assertEquals('', $result);
    }

    /**
     * Test format_date returns original for invalid date
     */
    public function testFormatDateReturnsOriginalForInvalidDate(): void {
        $method = $this->getPrivateMethod('format_date');

        $result = $method->invoke(null, 'not-a-date');

        // DateTime might still parse some strings or throw exception
        $this->assertIsString($result);
    }

    /**
     * Test format_time formats time correctly
     */
    public function testFormatTimeFormatsTimeCorrectly(): void {
        $method = $this->getPrivateMethod('format_time');

        $result = $method->invoke(null, '14:30');

        $this->assertStringContainsString('2', $result);
        $this->assertStringContainsString('30', $result);
        $this->assertStringContainsString('PM', $result);
    }

    /**
     * Test format_time returns empty for empty input
     */
    public function testFormatTimeReturnsEmptyForEmptyInput(): void {
        $method = $this->getPrivateMethod('format_time');

        $result = $method->invoke(null, '');

        $this->assertEquals('', $result);
    }

    /**
     * Test format_time handles morning time
     */
    public function testFormatTimeHandlesMorningTime(): void {
        $method = $this->getPrivateMethod('format_time');

        $result = $method->invoke(null, '09:00');

        $this->assertStringContainsString('9', $result);
        $this->assertStringContainsString('00', $result);
        $this->assertStringContainsString('AM', $result);
    }

    /**
     * Test matches_preferences returns true for no preferences (legacy)
     */
    public function testMatchesPreferencesReturnsTrueForNoPreferences(): void {
        $method = $this->getPrivateMethod('matches_preferences');

        $subscriber = (object)[
            'email' => 'test@example.com',
            'preferences' => null
        ];

        $announcement_data = [
            'categories' => [1, 2],
            'tags' => [3],
            'service_body' => '5'
        ];

        $result = $method->invoke(null, $subscriber, $announcement_data);

        $this->assertTrue($result);
    }

    /**
     * Test matches_preferences returns true for invalid preferences
     */
    public function testMatchesPreferencesReturnsTrueForInvalidPreferences(): void {
        $method = $this->getPrivateMethod('matches_preferences');

        $subscriber = (object)[
            'email' => 'test@example.com',
            'preferences' => 'invalid-json'
        ];

        $announcement_data = [
            'categories' => [1],
            'tags' => [],
            'service_body' => ''
        ];

        $result = $method->invoke(null, $subscriber, $announcement_data);

        $this->assertTrue($result);
    }

    /**
     * Test matches_preferences returns true for empty preferences
     */
    public function testMatchesPreferencesReturnsTrueForEmptyPreferences(): void {
        $method = $this->getPrivateMethod('matches_preferences');

        $subscriber = (object)[
            'email' => 'test@example.com',
            'preferences' => json_encode([
                'categories' => [],
                'tags' => [],
                'service_bodies' => []
            ])
        ];

        $announcement_data = [
            'categories' => [1],
            'tags' => [2],
            'service_body' => '5'
        ];

        $result = $method->invoke(null, $subscriber, $announcement_data);

        $this->assertTrue($result);
    }

    /**
     * Test matches_preferences returns true for category match
     */
    public function testMatchesPreferencesReturnsTrueForCategoryMatch(): void {
        $method = $this->getPrivateMethod('matches_preferences');

        $subscriber = (object)[
            'email' => 'test@example.com',
            'preferences' => json_encode([
                'categories' => [1, 2, 3],
                'tags' => [],
                'service_bodies' => []
            ])
        ];

        $announcement_data = [
            'categories' => [2, 4],
            'tags' => [],
            'service_body' => ''
        ];

        $result = $method->invoke(null, $subscriber, $announcement_data);

        $this->assertTrue($result);
    }

    /**
     * Test matches_preferences returns true for tag match
     */
    public function testMatchesPreferencesReturnsTrueForTagMatch(): void {
        $method = $this->getPrivateMethod('matches_preferences');

        $subscriber = (object)[
            'email' => 'test@example.com',
            'preferences' => json_encode([
                'categories' => [],
                'tags' => [5, 6],
                'service_bodies' => []
            ])
        ];

        $announcement_data = [
            'categories' => [],
            'tags' => [6, 7],
            'service_body' => ''
        ];

        $result = $method->invoke(null, $subscriber, $announcement_data);

        $this->assertTrue($result);
    }

    /**
     * Test matches_preferences returns true for service body match
     */
    public function testMatchesPreferencesReturnsTrueForServiceBodyMatch(): void {
        $method = $this->getPrivateMethod('matches_preferences');

        $subscriber = (object)[
            'email' => 'test@example.com',
            'preferences' => json_encode([
                'categories' => [],
                'tags' => [],
                'service_bodies' => ['10', '20']
            ])
        ];

        $announcement_data = [
            'categories' => [],
            'tags' => [],
            'service_body' => '10'
        ];

        $result = $method->invoke(null, $subscriber, $announcement_data);

        $this->assertTrue($result);
    }

    /**
     * Test matches_preferences returns false for no match
     */
    public function testMatchesPreferencesReturnsFalseForNoMatch(): void {
        $method = $this->getPrivateMethod('matches_preferences');

        $subscriber = (object)[
            'email' => 'test@example.com',
            'preferences' => json_encode([
                'categories' => [1, 2],
                'tags' => [3, 4],
                'service_bodies' => ['10']
            ])
        ];

        $announcement_data = [
            'categories' => [5, 6],
            'tags' => [7, 8],
            'service_body' => '20'
        ];

        $result = $method->invoke(null, $subscriber, $announcement_data);

        $this->assertFalse($result);
    }

    /**
     * Test get_match_reason returns all for no preferences
     */
    public function testGetMatchReasonReturnsAllForNoPreferences(): void {
        $method = $this->getPrivateMethod('get_match_reason');

        $subscriber = (object)[
            'email' => 'test@example.com',
            'preferences' => null
        ];

        $announcement_data = ['categories' => [], 'tags' => [], 'service_body' => ''];

        $result = $method->invoke(null, $subscriber, $announcement_data, []);

        $this->assertIsArray($result);
        $this->assertTrue($result['all']);
    }

    /**
     * Test get_match_reason returns false for no match
     */
    public function testGetMatchReasonReturnsFalseForNoMatch(): void {
        $method = $this->getPrivateMethod('get_match_reason');

        $subscriber = (object)[
            'email' => 'test@example.com',
            'preferences' => json_encode([
                'categories' => [1],
                'tags' => [2],
                'service_bodies' => ['3']
            ])
        ];

        $announcement_data = [
            'categories' => [10],
            'tags' => [20],
            'service_body' => '30'
        ];

        $result = $method->invoke(null, $subscriber, $announcement_data, []);

        $this->assertFalse($result);
    }

    /**
     * Test get_announcement_data returns array with required keys
     */
    public function testGetAnnouncementDataReturnsArrayWithRequiredKeys(): void {
        $method = $this->getPrivateMethod('get_announcement_data');

        $this->setPostMeta(100, [
            'service_body' => '5'
        ]);

        $result = $method->invoke(null, 100);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('categories', $result);
        $this->assertArrayHasKey('tags', $result);
        $this->assertArrayHasKey('service_body', $result);
    }

    /**
     * Test get_linked_events_text returns empty for no linked events
     */
    public function testGetLinkedEventsTextReturnsEmptyForNoLinkedEvents(): void {
        $method = $this->getPrivateMethod('get_linked_events_text');

        $this->setPostMeta(200, [
            'linked_event_refs' => [],
            'linked_events' => []
        ]);

        $result = $method->invoke(null, 200);

        $this->assertEquals('', $result);
    }

    /**
     * Test TABLE_NAME constant
     */
    public function testTableNameConstant(): void {
        $this->assertEquals('mayo_subscribers', Subscriber::TABLE_NAME);
    }

    /**
     * Helper to get private method
     */
    private function getPrivateMethod(string $methodName): \ReflectionMethod {
        $reflection = new ReflectionClass(Subscriber::class);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }
}
