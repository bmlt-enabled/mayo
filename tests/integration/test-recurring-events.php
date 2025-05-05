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

    public function testMonthlyRecurringEventsWithWeekday() {
        $startDate = '2025-02-13';
        $endDate = '2025-05-13';
        $pattern = [
            'type' => 'monthly',
            'interval' => 1,
            'monthlyWeekday' => '2,4' // 2nd Thursday (4 = Thursday)
        ];

        $events = $this->generator->generateEvents($startDate, $pattern, $endDate);
        
        $this->assertCount(4, $events);
        $this->assertEquals('2025-02-13', $events[0]); // 2nd Thursday of February
        $this->assertEquals('2025-03-13', $events[1]); // 2nd Thursday of March
        $this->assertEquals('2025-04-10', $events[2]); // 2nd Thursday of April
        $this->assertEquals('2025-05-08', $events[3]); // 2nd Thursday of May
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