/**
 * Regression tests for infinite scroll in EventList when the list starts in
 * calendar view. [#335]
 */
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import EventList from '../EventList';
import { apiFetch } from '../../../util';

jest.mock('../../../util', () => ({
    ...jest.requireActual('../../../util'),
    apiFetch: jest.fn(),
}));

jest.mock('../../providers/EventProvider', () => ({
    useEventProvider: () => ({ updateExternalServiceBodies: jest.fn() }),
}));

// Keep the render tree small: the observer wiring is what's under test.
jest.mock('../CalendarView', () => () => <div className="mock-calendar" />);
jest.mock('../EventFilters', () => () => null);
jest.mock('../cards/EventCard', () => ({ event }) => (
    <div className="mock-event-card">{event.title}</div>
));

const PER_PAGE = 10;
const TOTAL_PAGES = 3;

const makeEvents = (page) => Array.from({ length: PER_PAGE }, (_, i) => {
    const n = (page - 1) * PER_PAGE + i + 1;
    return {
        id: n,
        title: `Event ${n}`,
        meta: { event_start_date: '2099-01-01' },
    };
});

// Minimal IntersectionObserver stand-in: tracks live observers so a test can
// "scroll" the loader into view and check nothing is left attached.
let observers = [];
class MockIntersectionObserver {
    constructor(callback, options) {
        this.callback = callback;
        this.options = options;
        this.targets = new Set();
        observers.push(this);
    }
    observe(el) { this.targets.add(el); }
    unobserve(el) { this.targets.delete(el); }
    disconnect() { this.targets.clear(); }
}

const liveObservers = () => observers.filter(o => o.targets.size > 0);

const scrollLoaderIntoView = async () => {
    const loader = document.querySelector('.mayo-infinite-loader');
    expect(loader).not.toBeNull();
    const watching = liveObservers().filter(o => o.targets.has(loader));
    expect(watching).toHaveLength(1);
    await act(async () => {
        watching[0].callback([{ isIntersecting: true, target: loader }]);
    });
};

const eventsPagesRequested = () => apiFetch.mock.calls
    .map(([url]) => url)
    .filter(url => url.startsWith('/events?'))
    .map(url => new URLSearchParams(url.split('?')[1]))
    .filter(params => !params.has('start_date')) // skip calendar month fetches
    .map(params => Number(params.get('page')));

const cardCount = () => document.querySelectorAll('.mock-event-card').length;

const clickButton = async (label) => {
    const button = document.querySelector(`button.mayo-view-toggle-button[title="${label}"]`);
    expect(button).not.toBeNull();
    await act(async () => {
        button.click();
    });
};

describe('EventList infinite scroll', () => {
    let container;
    let root;

    beforeAll(() => {
        global.IS_REACT_ACT_ENVIRONMENT = true;
    });

    beforeEach(() => {
        observers = [];
        window.IntersectionObserver = MockIntersectionObserver;
        window.history.replaceState({}, '', '/');
        jest.spyOn(console, 'error').mockImplementation(() => {});

        apiFetch.mockImplementation(async (url) => {
            if (url.startsWith('/events/facets')) {
                return { event_types: [], service_bodies: [], categories: [], tags: [] };
            }
            if (url.startsWith('/events?')) {
                const params = new URLSearchParams(url.split('?')[1]);
                if (params.has('start_date') || params.has('end_date')) {
                    return { events: [], pagination: { current_page: 1, total_pages: 1 } };
                }
                const page = Number(params.get('page')) || 1;
                return {
                    events: makeEvents(page),
                    pagination: { current_page: page, total_pages: TOTAL_PAGES },
                };
            }
            return {};
        });

        container = document.createElement('div');
        document.body.appendChild(container);
        root = createRoot(container);
    });

    afterEach(async () => {
        await act(async () => {
            root.unmount();
        });
        container.remove();
        jest.restoreAllMocks();
        apiFetch.mockReset();
    });

    const mount = async (settings) => {
        await act(async () => {
            root.render(<EventList settings={{ infiniteScroll: true, perPage: PER_PAGE, ...settings }} />);
        });
    };

    it('loads the next page after switching from calendar to List View', async () => {
        await mount({ defaultView: 'calendar' });

        expect(document.querySelector('.mock-calendar')).not.toBeNull();
        expect(document.querySelector('.mayo-infinite-loader')).toBeNull();
        expect(liveObservers()).toHaveLength(0);

        await clickButton('List View');
        expect(cardCount()).toBe(PER_PAGE);

        await scrollLoaderIntoView();
        expect(eventsPagesRequested()).toContain(2);
        expect(cardCount()).toBe(PER_PAGE * 2);

        await scrollLoaderIntoView();
        expect(eventsPagesRequested()).toContain(3);
        expect(cardCount()).toBe(PER_PAGE * 3);

        // Last page reached: the loader goes away and nothing is observed.
        expect(document.querySelector('.mayo-infinite-loader')).toBeNull();
        expect(liveObservers()).toHaveLength(0);
    });

    it('keeps paginating after List → Calendar → List without leaking observers', async () => {
        await mount({ defaultView: 'calendar' });

        await clickButton('List View');
        expect(liveObservers()).toHaveLength(1);

        await clickButton('Calendar View');
        expect(document.querySelector('.mayo-infinite-loader')).toBeNull();
        expect(liveObservers()).toHaveLength(0);

        await clickButton('List View');
        expect(liveObservers()).toHaveLength(1);

        await scrollLoaderIntoView();
        expect(eventsPagesRequested()).toContain(2);
        expect(cardCount()).toBe(PER_PAGE * 2);
        expect(liveObservers()).toHaveLength(1);
    });

    it('still paginates when the list starts in list view', async () => {
        await mount({ defaultView: 'list' });
        expect(cardCount()).toBe(PER_PAGE);

        await scrollLoaderIntoView();
        expect(eventsPagesRequested()).toContain(2);
        expect(cardCount()).toBe(PER_PAGE * 2);
    });

    it('does not fetch another page while one is already loading', async () => {
        await mount({ defaultView: 'list' });

        let resolvePage2;
        apiFetch.mockImplementationOnce(() => new Promise(resolve => {
            resolvePage2 = resolve;
        }));

        await scrollLoaderIntoView();
        // The loader stays observed while page 2 is in flight; another
        // intersection must not trigger a duplicate request.
        await scrollLoaderIntoView();
        expect(eventsPagesRequested().filter(p => p === 2)).toHaveLength(1);
        expect(eventsPagesRequested()).not.toContain(3);

        await act(async () => {
            resolvePage2({ events: makeEvents(2), pagination: { current_page: 2, total_pages: TOTAL_PAGES } });
        });
        expect(cardCount()).toBe(PER_PAGE * 2);
    });

    it('uses a forgiving observer threshold so a zero-height loader still triggers', async () => {
        await mount({ defaultView: 'list' });
        const [observer] = liveObservers();
        expect(observer.options.threshold).toBe(0);
        expect(observer.options.rootMargin).toMatch(/200px/);
    });
});
