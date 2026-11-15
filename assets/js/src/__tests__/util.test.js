/**
 * Tests for formatRecurringPattern's annual recurrence text. [#340]
 */
import { formatRecurringPattern } from '../util';

describe('formatRecurringPattern yearly', () => {
    it('describes a yearly pattern as annual', () => {
        expect(formatRecurringPattern({ type: 'yearly', interval: 1 }))
            .toBe('This event repeats annually');
    });

    it('describes a multi-year interval', () => {
        expect(formatRecurringPattern({ type: 'yearly', interval: 2 }))
            .toBe('This event repeats every 2 years');
    });

    it('appends the end date', () => {
        expect(formatRecurringPattern({ type: 'yearly', interval: 1, endDate: '2030-07-04' }))
            .toBe('This event repeats annually until 2030-07-04');
    });
});
