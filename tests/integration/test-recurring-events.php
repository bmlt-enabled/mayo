<?php

namespace BmltEnabled\Mayo\Tests\Integration;

use WP_UnitTestCase;
use BmltEnabled\Mayo\RecurringEventGenerator;

class RecurringEventsIntegrationTest extends WP_UnitTestCase {
    private $generator;

    public function setUp(): void {
        parent::setUp();
        $this->generator = new RecurringEventGenerator();
    }

    public function testMonthlyRecurringEvents() {
        $startDate = '2025-07-15';
        $endDate = '2025-12-15';
        $pattern = [
            'type' => 'monthly',
            'interval' => 1,
            'day' => 15
        ];

        $events = $this->generator->generateEvents($startDate, $pattern, $endDate);
        
        $this->assertCount(6, $events);
        $this->assertEquals('2025-07-15', $events[0]);
        $this->assertEquals('2025-08-15', $events[1]);
        $this->assertEquals('2025-09-15', $events[2]);
        $this->assertEquals('2025-10-15', $events[3]);
        $this->assertEquals('2025-11-15', $events[4]);
        $this->assertEquals('2025-12-15', $events[5]);
    }

    public function testWeeklyRecurringEvents() {
        $startDate = '2025-07-15';
        $endDate = '2025-07-29';
        $pattern = [
            'type' => 'weekly',
            'interval' => 1,
            'days' => [2, 4] // Tuesday and Thursday
        ];

        $events = $this->generator->generateEvents($startDate, $pattern, $endDate);
        
        $this->assertCount(4, $events);
        $this->assertEquals('2025-07-15', $events[0]); // Tuesday
        $this->assertEquals('2025-07-17', $events[1]); // Thursday
        $this->assertEquals('2025-07-22', $events[2]); // Tuesday
        $this->assertEquals('2025-07-24', $events[3]); // Thursday
    }

    public function testDailyRecurringEvents() {
        $startDate = '2025-07-15';
        $endDate = '2025-07-19';
        $pattern = [
            'type' => 'daily',
            'interval' => 1
        ];

        $events = $this->generator->generateEvents($startDate, $pattern, $endDate);
        
        $this->assertCount(5, $events);
        $this->assertEquals('2025-07-15', $events[0]);
        $this->assertEquals('2025-07-16', $events[1]);
        $this->assertEquals('2025-07-17', $events[2]);
        $this->assertEquals('2025-07-18', $events[3]);
        $this->assertEquals('2025-07-19', $events[4]);
    }
} 