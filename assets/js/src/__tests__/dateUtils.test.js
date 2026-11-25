/**
 * Tests for monthly recurrence defaults derived from the event start date.
 */
import { getInitialMonthlyDate, getInitialWeekdayPattern, parseLocalDate } from '../util';

describe('regression environment', () => {
    it('runs west of UTC so the old UTC parsing moved the date back one day', () => {
        expect(new Date('2026-11-24').getDate()).toBe(23);
        expect(new Date('2026-11-24').getDay()).toBe(1);
    });
});

describe('parseLocalDate', () => {
    it('parses a YYYY-MM-DD value in local time', () => {
        const date = parseLocalDate('2026-11-24');

        expect(date).not.toBeNull();
        expect(date.getFullYear()).toBe(2026);
        expect(date.getMonth()).toBe(10);
        expect(date.getDate()).toBe(24);
    });

    it('rejects missing or invalid dates', () => {
        expect(parseLocalDate('')).toBeNull();
        expect(parseLocalDate('2026-02-30')).toBeNull();
    });
});

describe('monthly recurrence defaults', () => {
    it('uses the event start day of month', () => {
        expect(getInitialMonthlyDate('2026-11-24')).toBe('24');
    });

    it('uses the weekday of the event start date in local time', () => {
        // 2026-11-24 is the fourth Tuesday of the month.
        expect(getInitialWeekdayPattern('2026-11-24')).toBe('4,2');
    });

    it('returns empty strings when no start date is available', () => {
        expect(getInitialMonthlyDate('')).toBe('');
        expect(getInitialWeekdayPattern('')).toBe('');
    });
});
