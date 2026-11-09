<?php

namespace BmltEnabled\Mayo\Tests\Unit;

use BmltEnabled\Mayo\MovedOccurrences;

class MovedOccurrencesTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        $this->mockPostMeta();
    }

    public function testSanitizeKeepsValidMoves(): void {
        $this->assertSame(
            [['original_date' => '2025-09-07', 'new_date' => '2025-09-14']],
            MovedOccurrences::sanitize([
                ['original_date' => '2025-09-07', 'new_date' => '2025-09-14'],
            ])
        );
    }

    public function testSanitizeDropsMalformedAndNoOpEntries(): void {
        $result = MovedOccurrences::sanitize([
            ['original_date' => '2025-02-30', 'new_date' => '2025-03-01'], // not a real date
            ['original_date' => '09/07/2025', 'new_date' => '2025-09-14'], // wrong format
            ['original_date' => '2025-10-05', 'new_date' => '2025-10-05'], // same date
            ['original_date' => '2025-11-02'],                            // missing new_date
            'garbage',
            ['original_date' => ' 2025-12-07 ', 'new_date' => '2025-12-14'], // trimmed
        ]);

        $this->assertSame(
            [['original_date' => '2025-12-07', 'new_date' => '2025-12-14']],
            $result
        );
    }

    public function testSanitizeKeepsFirstMoveForADuplicateOriginal(): void {
        $result = MovedOccurrences::sanitize([
            ['original_date' => '2025-09-07', 'new_date' => '2025-09-14'],
            ['original_date' => '2025-09-07', 'new_date' => '2025-09-21'],
        ]);

        $this->assertSame(
            [['original_date' => '2025-09-07', 'new_date' => '2025-09-14']],
            $result
        );
    }

    public function testSanitizeAcceptsJsonStringAndObjects(): void {
        $this->assertSame(
            [['original_date' => '2025-09-07', 'new_date' => '2025-09-14']],
            MovedOccurrences::sanitize('[{"original_date":"2025-09-07","new_date":"2025-09-14"}]')
        );
        $this->assertSame(
            [['original_date' => '2025-09-07', 'new_date' => '2025-09-14']],
            MovedOccurrences::sanitize([(object) ['original_date' => '2025-09-07', 'new_date' => '2025-09-14']])
        );
        $this->assertSame([], MovedOccurrences::sanitize('not json'));
        $this->assertSame([], MovedOccurrences::sanitize(null));
    }

    public function testGetMapReturnsOriginalToNewDate(): void {
        $this->setPostMeta(900, [
            'moved_occurrences' => [
                ['original_date' => '2025-09-07', 'new_date' => '2025-09-14'],
                ['original_date' => '2025-12-07', 'new_date' => '2025-12-14'],
            ],
        ]);

        $this->assertSame(
            ['2025-09-07' => '2025-09-14', '2025-12-07' => '2025-12-14'],
            MovedOccurrences::get_map(900)
        );
    }

    public function testGetMapIsEmptyWithoutMeta(): void {
        $this->setPostMeta(901, []);
        $this->assertSame([], MovedOccurrences::get_map(901));
    }
}
