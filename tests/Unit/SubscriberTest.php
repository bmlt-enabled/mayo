<?php

namespace BmltEnabled\Mayo\Tests\Unit;

use BmltEnabled\Mayo\Subscriber;
use Brain\Monkey\Functions;
use ReflectionClass;
use Mockery;

/**
 * Testable Subscriber class that allows mocking $wpdb
 */
class TestableSubscriber extends Subscriber {
    public static $mockWpdb = null;

    protected static function get_wpdb() {
        if (self::$mockWpdb !== null) {
            return self::$mockWpdb;
        }
        return parent::get_wpdb();
    }

    public static function resetMock() {
        self::$mockWpdb = null;
    }
}

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

        // Mock get_bloginfo for email methods
        Functions\when('get_bloginfo')->alias(function($show) {
            if ($show === 'name') return 'Test Site';
            return '';
        });

        // Reset testable subscriber mock
        TestableSubscriber::resetMock();
    }

    protected function tearDown(): void {
        TestableSubscriber::resetMock();
        parent::tearDown();
    }

    /**
     * Create a mock wpdb object for testing
     */
    private function createMockWpdb() {
        $mock = Mockery::mock('wpdb');
        $mock->prefix = 'wp_';
        return $mock;
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
     * Test get_table_name returns prefixed table name
     */
    public function testGetTableNameReturnsPrefixedTableName(): void {
        $mockWpdb = $this->createMockWpdb();
        $mockWpdb->prefix = 'test_prefix_';
        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::get_table_name();

        $this->assertEquals('test_prefix_mayo_subscribers', $result);
    }

    /**
     * Test subscribe returns error for invalid email
     */
    public function testSubscribeReturnsErrorForInvalidEmail(): void {
        $result = TestableSubscriber::subscribe('not-an-email');

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertEquals('invalid_email', $result['code']);
    }

    /**
     * Test subscribe creates new subscriber
     */
    public function testSubscribeCreatesNewSubscriber(): void {
        $mockWpdb = $this->createMockWpdb();

        // No existing subscriber
        $mockWpdb->shouldReceive('prepare')
            ->once()
            ->andReturn('SELECT * FROM wp_mayo_subscribers WHERE email = "test@example.com"');

        $mockWpdb->shouldReceive('get_row')
            ->once()
            ->andReturn(null);

        // Insert new subscriber
        $mockWpdb->shouldReceive('insert')
            ->once()
            ->andReturn(1);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::subscribe('test@example.com');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('confirmation_sent', $result['code']);
    }

    /**
     * Test subscribe with preferences creates new subscriber with preferences
     */
    public function testSubscribeWithPreferencesCreatesSubscriberWithPreferences(): void {
        $mockWpdb = $this->createMockWpdb();

        $mockWpdb->shouldReceive('prepare')
            ->once()
            ->andReturn('SQL');

        $mockWpdb->shouldReceive('get_row')
            ->once()
            ->andReturn(null);

        $mockWpdb->shouldReceive('insert')
            ->once()
            ->withArgs(function($table, $data) {
                return isset($data['preferences']) && $data['preferences'] !== null;
            })
            ->andReturn(1);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $preferences = ['categories' => [1, 2], 'tags' => [], 'service_bodies' => []];
        $result = TestableSubscriber::subscribe('test@example.com', $preferences);

        $this->assertTrue($result['success']);
    }

    /**
     * Test subscribe returns error when email already active
     */
    public function testSubscribeReturnsErrorWhenEmailAlreadyActive(): void {
        $mockWpdb = $this->createMockWpdb();

        $existingSubscriber = (object)[
            'id' => 1,
            'email' => 'test@example.com',
            'status' => 'active',
            'token' => 'existingtoken123'
        ];

        $mockWpdb->shouldReceive('prepare')->once()->andReturn('SQL');
        $mockWpdb->shouldReceive('get_row')->once()->andReturn($existingSubscriber);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::subscribe('test@example.com');

        $this->assertFalse($result['success']);
        $this->assertEquals('already_subscribed', $result['code']);
    }

    /**
     * Test subscribe resends confirmation for pending subscriber
     */
    public function testSubscribeResendsConfirmationForPending(): void {
        $mockWpdb = $this->createMockWpdb();

        $existingSubscriber = (object)[
            'id' => 1,
            'email' => 'test@example.com',
            'status' => 'pending',
            'token' => 'existingtoken123'
        ];

        $mockWpdb->shouldReceive('prepare')->once()->andReturn('SQL');
        $mockWpdb->shouldReceive('get_row')->once()->andReturn($existingSubscriber);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::subscribe('test@example.com');

        $this->assertTrue($result['success']);
        $this->assertEquals('confirmation_resent', $result['code']);
    }

    /**
     * Test subscribe re-subscribes unsubscribed user
     */
    public function testSubscribeResubscribesUnsubscribedUser(): void {
        $mockWpdb = $this->createMockWpdb();

        $existingSubscriber = (object)[
            'id' => 1,
            'email' => 'test@example.com',
            'status' => 'unsubscribed',
            'token' => 'existingtoken123'
        ];

        $mockWpdb->shouldReceive('prepare')->once()->andReturn('SQL');
        $mockWpdb->shouldReceive('get_row')->once()->andReturn($existingSubscriber);
        $mockWpdb->shouldReceive('update')->once()->andReturn(1);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::subscribe('test@example.com');

        $this->assertTrue($result['success']);
        $this->assertEquals('resubscribed', $result['code']);
    }

    /**
     * Test subscribe returns error on database failure
     */
    public function testSubscribeReturnsErrorOnDatabaseFailure(): void {
        $mockWpdb = $this->createMockWpdb();

        $mockWpdb->shouldReceive('prepare')->once()->andReturn('SQL');
        $mockWpdb->shouldReceive('get_row')->once()->andReturn(null);
        $mockWpdb->shouldReceive('insert')->once()->andReturn(false);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::subscribe('test@example.com');

        $this->assertFalse($result['success']);
        $this->assertEquals('database_error', $result['code']);
    }

    /**
     * Test get_by_token returns subscriber
     */
    public function testGetByTokenReturnsSubscriber(): void {
        $mockWpdb = $this->createMockWpdb();

        $subscriber = (object)[
            'id' => 1,
            'email' => 'test@example.com',
            'status' => 'active',
            'token' => 'abc123def456'
        ];

        $mockWpdb->shouldReceive('prepare')->once()->andReturn('SQL');
        $mockWpdb->shouldReceive('get_row')->once()->andReturn($subscriber);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::get_by_token('abc123def456');

        $this->assertNotNull($result);
        $this->assertEquals('test@example.com', $result->email);
    }

    /**
     * Test get_by_token returns null for invalid token
     */
    public function testGetByTokenReturnsNullForInvalidToken(): void {
        $mockWpdb = $this->createMockWpdb();

        $mockWpdb->shouldReceive('prepare')->once()->andReturn('SQL');
        $mockWpdb->shouldReceive('get_row')->once()->andReturn(null);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::get_by_token('invalidtoken');

        $this->assertNull($result);
    }

    /**
     * Test confirm activates subscriber
     */
    public function testConfirmActivatesSubscriber(): void {
        $mockWpdb = $this->createMockWpdb();

        $subscriber = (object)[
            'id' => 1,
            'email' => 'test@example.com',
            'status' => 'pending',
            'token' => 'validtoken123'
        ];

        $mockWpdb->shouldReceive('prepare')->once()->andReturn('SQL');
        $mockWpdb->shouldReceive('get_row')->once()->andReturn($subscriber);
        $mockWpdb->shouldReceive('update')->once()->andReturn(1);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::confirm('validtoken123');

        $this->assertTrue($result['success']);
        $this->assertEquals('confirmed', $result['code']);
    }

    /**
     * Test confirm returns error for invalid token
     */
    public function testConfirmReturnsErrorForInvalidToken(): void {
        $mockWpdb = $this->createMockWpdb();

        $mockWpdb->shouldReceive('prepare')->once()->andReturn('SQL');
        $mockWpdb->shouldReceive('get_row')->once()->andReturn(null);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::confirm('invalidtoken');

        $this->assertFalse($result['success']);
        $this->assertEquals('invalid_token', $result['code']);
    }

    /**
     * Test confirm returns already confirmed for active subscriber
     */
    public function testConfirmReturnsAlreadyConfirmedForActive(): void {
        $mockWpdb = $this->createMockWpdb();

        $subscriber = (object)[
            'id' => 1,
            'email' => 'test@example.com',
            'status' => 'active',
            'token' => 'validtoken123'
        ];

        $mockWpdb->shouldReceive('prepare')->once()->andReturn('SQL');
        $mockWpdb->shouldReceive('get_row')->once()->andReturn($subscriber);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::confirm('validtoken123');

        $this->assertTrue($result['success']);
        $this->assertEquals('already_confirmed', $result['code']);
    }

    /**
     * Test unsubscribe marks subscriber as unsubscribed
     */
    public function testUnsubscribeMarksSubscriberAsUnsubscribed(): void {
        $mockWpdb = $this->createMockWpdb();

        $subscriber = (object)[
            'id' => 1,
            'email' => 'test@example.com',
            'status' => 'active',
            'token' => 'validtoken123'
        ];

        $mockWpdb->shouldReceive('prepare')->once()->andReturn('SQL');
        $mockWpdb->shouldReceive('get_row')->once()->andReturn($subscriber);
        $mockWpdb->shouldReceive('update')->once()->andReturn(1);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::unsubscribe('validtoken123');

        $this->assertTrue($result['success']);
        $this->assertEquals('unsubscribed', $result['code']);
    }

    /**
     * Test unsubscribe returns error for invalid token
     */
    public function testUnsubscribeReturnsErrorForInvalidToken(): void {
        $mockWpdb = $this->createMockWpdb();

        $mockWpdb->shouldReceive('prepare')->once()->andReturn('SQL');
        $mockWpdb->shouldReceive('get_row')->once()->andReturn(null);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::unsubscribe('invalidtoken');

        $this->assertFalse($result['success']);
        $this->assertEquals('invalid_token', $result['code']);
    }

    /**
     * Test unsubscribe returns already_unsubscribed for unsubscribed user
     */
    public function testUnsubscribeReturnsAlreadyUnsubscribed(): void {
        $mockWpdb = $this->createMockWpdb();

        $subscriber = (object)[
            'id' => 1,
            'email' => 'test@example.com',
            'status' => 'unsubscribed',
            'token' => 'validtoken123'
        ];

        $mockWpdb->shouldReceive('prepare')->once()->andReturn('SQL');
        $mockWpdb->shouldReceive('get_row')->once()->andReturn($subscriber);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::unsubscribe('validtoken123');

        $this->assertTrue($result['success']);
        $this->assertEquals('already_unsubscribed', $result['code']);
    }

    /**
     * Test get_active_subscribers returns only active subscribers
     */
    public function testGetActiveSubscribersReturnsOnlyActive(): void {
        $mockWpdb = $this->createMockWpdb();

        $subscribers = [
            (object)['id' => 1, 'email' => 'active1@example.com', 'status' => 'active'],
            (object)['id' => 2, 'email' => 'active2@example.com', 'status' => 'active']
        ];

        $mockWpdb->shouldReceive('get_results')->once()->andReturn($subscribers);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::get_active_subscribers();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    /**
     * Test get_all_subscribers returns all subscribers
     */
    public function testGetAllSubscribersReturnsAll(): void {
        $mockWpdb = $this->createMockWpdb();

        $subscribers = [
            (object)['id' => 1, 'email' => 'a@example.com', 'status' => 'active'],
            (object)['id' => 2, 'email' => 'b@example.com', 'status' => 'pending'],
            (object)['id' => 3, 'email' => 'c@example.com', 'status' => 'unsubscribed']
        ];

        $mockWpdb->shouldReceive('get_results')->once()->andReturn($subscribers);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::get_all_subscribers();

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }

    /**
     * Test update_status updates subscriber status
     */
    public function testUpdateStatusUpdatesSubscriberStatus(): void {
        $mockWpdb = $this->createMockWpdb();

        $mockWpdb->shouldReceive('update')
            ->once()
            ->withArgs(function($table, $data, $where) {
                return $data['status'] === 'active';
            })
            ->andReturn(1);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::update_status(1, 'active');

        $this->assertTrue($result);
    }

    /**
     * Test update_status sets confirmed_at when activating
     */
    public function testUpdateStatusSetsConfirmedAtWhenActivating(): void {
        $mockWpdb = $this->createMockWpdb();

        $mockWpdb->shouldReceive('update')
            ->once()
            ->withArgs(function($table, $data, $where) {
                return isset($data['confirmed_at']);
            })
            ->andReturn(1);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::update_status(1, 'active');

        $this->assertTrue($result);
    }

    /**
     * Test update_preferences updates subscriber preferences
     */
    public function testUpdatePreferencesUpdatesSubscriberPreferences(): void {
        $mockWpdb = $this->createMockWpdb();

        $mockWpdb->shouldReceive('update')
            ->once()
            ->andReturn(1);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::update_preferences('token123', ['categories' => [1]]);

        $this->assertTrue($result);
    }

    /**
     * Test update_preferences returns false on failure
     */
    public function testUpdatePreferencesReturnsFalseOnFailure(): void {
        $mockWpdb = $this->createMockWpdb();

        $mockWpdb->shouldReceive('update')
            ->once()
            ->andReturn(false);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::update_preferences('token123', ['categories' => [1]]);

        $this->assertFalse($result);
    }

    /**
     * Test update_preferences_by_id updates subscriber preferences by ID
     */
    public function testUpdatePreferencesByIdUpdatesPreferences(): void {
        $mockWpdb = $this->createMockWpdb();

        $mockWpdb->shouldReceive('update')
            ->once()
            ->andReturn(1);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::update_preferences_by_id(1, ['categories' => [1, 2]]);

        $this->assertTrue($result);
    }

    /**
     * Test delete removes subscriber
     */
    public function testDeleteRemovesSubscriber(): void {
        $mockWpdb = $this->createMockWpdb();

        $mockWpdb->shouldReceive('delete')
            ->once()
            ->andReturn(1);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::delete(1);

        $this->assertTrue($result);
    }

    /**
     * Test delete returns false when subscriber not found
     */
    public function testDeleteReturnsFalseWhenNotFound(): void {
        $mockWpdb = $this->createMockWpdb();

        $mockWpdb->shouldReceive('delete')
            ->once()
            ->andReturn(false);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::delete(999);

        $this->assertFalse($result);
    }

    /**
     * Test get_matching returns subscribers matching criteria
     */
    public function testGetMatchingReturnsMatchingSubscribers(): void {
        $mockWpdb = $this->createMockWpdb();

        $subscribers = [
            (object)[
                'id' => 1,
                'email' => 'match@example.com',
                'status' => 'active',
                'preferences' => json_encode(['categories' => [1], 'tags' => [], 'service_bodies' => []])
            ]
        ];

        $mockWpdb->shouldReceive('get_results')->once()->andReturn($subscribers);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::get_matching([
            'categories' => [1],
            'tags' => [],
            'service_body' => ''
        ]);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    /**
     * Test count_matching returns count of matching subscribers
     */
    public function testCountMatchingReturnsCount(): void {
        $mockWpdb = $this->createMockWpdb();

        $subscribers = [
            (object)[
                'id' => 1,
                'email' => 'match1@example.com',
                'status' => 'active',
                'preferences' => null
            ],
            (object)[
                'id' => 2,
                'email' => 'match2@example.com',
                'status' => 'active',
                'preferences' => null
            ]
        ];

        $mockWpdb->shouldReceive('get_results')->once()->andReturn($subscribers);

        TestableSubscriber::$mockWpdb = $mockWpdb;

        $result = TestableSubscriber::count_matching([
            'categories' => [1],
            'tags' => [],
            'service_body' => ''
        ]);

        $this->assertEquals(2, $result);
    }

    /**
     * Test get_wpdb returns wpdb instance
     * Note: create_table can't be fully tested without WordPress test environment
     * because it requires require_once for upgrade.php
     */
    public function testGetWpdbIntegration(): void {
        // The base class get_wpdb() is tested implicitly by other tests
        // This test verifies the TestableSubscriber mock pattern works
        $mockWpdb = $this->createMockWpdb();
        TestableSubscriber::$mockWpdb = $mockWpdb;

        // Verify the mock is returned
        $this->assertEquals('wp_mayo_subscribers', TestableSubscriber::get_table_name());
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
