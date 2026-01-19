<?php

namespace BmltEnabled\Mayo\Tests\Unit\Rest\Helpers;

use BmltEnabled\Mayo\Tests\Unit\TestCase;
use BmltEnabled\Mayo\Rest\Helpers\EmailNotification;
use Brain\Monkey\Functions;

class EmailNotificationTest extends TestCase {

    /**
     * Test parsing empty email string
     */
    public function testParseRecipientsWithEmptyString(): void {
        $result = EmailNotification::parse_recipients('');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test parsing single valid email
     */
    public function testParseRecipientsWithSingleEmail(): void {
        $result = EmailNotification::parse_recipients('test@example.com');
        $this->assertCount(1, $result);
        $this->assertEquals('test@example.com', $result[0]);
    }

    /**
     * Test parsing comma-separated emails
     */
    public function testParseRecipientsWithCommas(): void {
        $result = EmailNotification::parse_recipients('one@example.com, two@example.com, three@example.com');
        $this->assertCount(3, $result);
        $this->assertContains('one@example.com', $result);
        $this->assertContains('two@example.com', $result);
        $this->assertContains('three@example.com', $result);
    }

    /**
     * Test parsing semicolon-separated emails
     */
    public function testParseRecipientsWithSemicolons(): void {
        $result = EmailNotification::parse_recipients('one@example.com; two@example.com; three@example.com');
        $this->assertCount(3, $result);
        $this->assertContains('one@example.com', $result);
        $this->assertContains('two@example.com', $result);
        $this->assertContains('three@example.com', $result);
    }

    /**
     * Test parsing mixed separators
     */
    public function testParseRecipientsWithMixedSeparators(): void {
        $result = EmailNotification::parse_recipients('one@example.com, two@example.com; three@example.com');
        $this->assertCount(3, $result);
    }

    /**
     * Test invalid emails are filtered out
     */
    public function testParseRecipientsFiltersInvalidEmails(): void {
        $result = EmailNotification::parse_recipients('valid@example.com, invalid-email, another@test.com');
        $this->assertCount(2, $result);
        $this->assertContains('valid@example.com', $result);
        $this->assertContains('another@test.com', $result);
        $this->assertNotContains('invalid-email', $result);
    }

    /**
     * Test whitespace is trimmed
     */
    public function testParseRecipientsTrimWhitespace(): void {
        $result = EmailNotification::parse_recipients('  test@example.com  ');
        $this->assertCount(1, $result);
        $this->assertEquals('test@example.com', $result[0]);
    }

    /**
     * Test get_notification_recipients returns configured email
     */
    public function testGetNotificationRecipientsReturnsConfiguredEmail(): void {
        $this->mockGetOption([
            'mayo_settings' => [
                'notification_email' => 'admin@example.com'
            ],
            'admin_email' => 'fallback@example.com'
        ]);

        $result = EmailNotification::get_notification_recipients();
        $this->assertEquals('admin@example.com', $result);
    }

    /**
     * Test get_notification_recipients falls back to admin email
     */
    public function testGetNotificationRecipientsFallsBackToAdminEmail(): void {
        $this->mockGetOption([
            'mayo_settings' => [],
            'admin_email' => 'fallback@example.com'
        ]);

        $result = EmailNotification::get_notification_recipients();
        $this->assertEquals('fallback@example.com', $result);
    }

    /**
     * Test get_notification_recipients with empty notification email
     */
    public function testGetNotificationRecipientsWithEmptyConfiguredEmail(): void {
        $this->mockGetOption([
            'mayo_settings' => [
                'notification_email' => ''
            ],
            'admin_email' => 'admin@site.com'
        ]);

        $result = EmailNotification::get_notification_recipients();
        $this->assertEquals('admin@site.com', $result);
    }

    /**
     * Test get_notification_recipients returns array for multiple emails
     */
    public function testGetNotificationRecipientsReturnsArrayForMultiple(): void {
        $this->mockGetOption([
            'mayo_settings' => [
                'notification_email' => 'one@example.com, two@example.com'
            ],
            'admin_email' => 'fallback@example.com'
        ]);

        $result = EmailNotification::get_notification_recipients();
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    /**
     * Test get_notification_recipients_array always returns array
     */
    public function testGetNotificationRecipientsArrayAlwaysReturnsArray(): void {
        $this->mockGetOption([
            'mayo_settings' => [
                'notification_email' => 'single@example.com'
            ],
            'admin_email' => 'fallback@example.com'
        ]);

        $result = EmailNotification::get_notification_recipients_array();
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('single@example.com', $result[0]);
    }

    /**
     * Test get_notification_recipients_array with multiple recipients
     */
    public function testGetNotificationRecipientsArrayWithMultiple(): void {
        $this->mockGetOption([
            'mayo_settings' => [
                'notification_email' => 'one@example.com, two@example.com, three@example.com'
            ],
            'admin_email' => 'fallback@example.com'
        ]);

        $result = EmailNotification::get_notification_recipients_array();
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }
}
