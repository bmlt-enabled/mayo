/**
 * Regression tests for translated event-type filter labels.
 */
import { normalizeOptions } from '../EventFilters';

jest.mock('@wordpress/i18n', () => ({
    __: (text, domain) => domain === 'mayo-events-manager' ? `translated:${text}` : text,
    sprintf: (format, ...args) => format.replace('%s', args[0]),
}));

describe('event type facet labels', () => {
    it('translates the canonical event type values while keeping filter values unchanged', () => {
        const options = normalizeOptions('event_type', {
            event_types: ['Service', 'Activity', 'Celebration'],
        });

        expect(options).toEqual([
            { value: 'Service', label: 'translated:Service' },
            { value: 'Activity', label: 'translated:Activity' },
            { value: 'Celebration', label: 'translated:Celebration' },
        ]);
    });

    it('leaves unknown event types readable', () => {
        const options = normalizeOptions('event_type', {
            event_types: ['ExternalType'],
        });

        expect(options).toEqual([
            { value: 'ExternalType', label: 'translated:ExternalType' },
        ]);
    });
});
