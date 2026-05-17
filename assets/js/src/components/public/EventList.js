import { useState, useEffect, useRef, useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import EventCard from './cards/EventCard';
import EventWidgetCard from './cards/EventWidgetCard';
import CalendarView from './CalendarView';
import EventFilters from './EventFilters';
import { useEventProvider } from '../providers/EventProvider';
import { apiFetch } from '../../util';
import { getUserTimezone } from '../../timezones';

const EMPTY_FILTERS = { event_type: [], service_body: [], categories: [], tags: [] };
const EMPTY_FACETS = { event_types: [], service_bodies: [], categories: [], tags: [] };

const EventList = ({ widget = false, settings = {} }) => {
    const containerRef = useRef(null);
    const loaderRef = useRef(null);
    const updateTimeout = useRef(null);
    const isInitialFilterEffect = useRef(true);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [events, setEvents] = useState([]);
    const [currentPage, setCurrentPage] = useState(1);
    const [timeFormat, setTimeFormat] = useState('12hour');
    const [isWidget, setIsWidget] = useState(false);
    const [hasMore, setHasMore] = useState(true);
    const [totalPages, setTotalPages] = useState(1);
    const [allExpanded, setAllExpanded] = useState(false);
    const [expandedEvents, setExpandedEvents] = useState(new Set());
    const [isPrinting, setIsPrinting] = useState(false);
    const [isLoadingMore, setIsLoadingMore] = useState(false);
    const [isInitialLoad, setIsInitialLoad] = useState(true);
    const [autoexpand, setAutoexpand] = useState(false);
    const [showShortcode, setShowShortcode] = useState(false);
    const [viewMode, setViewMode] = useState(settings?.defaultView || 'list'); // 'list' or 'calendar'
    const [calendarDate, setCalendarDate] = useState(new Date()); // Current month for calendar view
    const [calendarEvents, setCalendarEvents] = useState([]); // Events for calendar view
    const [calendarLoading, setCalendarLoading] = useState(false);
    const [activeFilters, setActiveFilters] = useState(EMPTY_FILTERS);
    const [facets, setFacets] = useState(EMPTY_FACETS);
    const { updateExternalServiceBodies } = useEventProvider();

    // Locked facets: those pinned by a shortcode attribute should not be
    // shown as user-selectable dropdowns (they'd just collapse to one value).
    const lockedFilters = useMemo(() => {
        const locked = new Set();
        if (settings?.eventType) locked.add('event_type');
        if (settings?.serviceBody) locked.add('service_body');
        if (settings?.categories) locked.add('categories');
        if (settings?.tags) locked.add('tags');
        return locked;
    }, [settings?.eventType, settings?.serviceBody, settings?.categories, settings?.tags]);

    // Get user's current timezone
    const userTimezone = getUserTimezone();

    // Initialize component
    useEffect(() => {
        setIsWidget(widget);
        setTimeFormat(settings?.timeFormat || '12hour');
        setCurrentPage(1);
        setEvents([]);
        setLoading(true);
        setError(null);
        setHasMore(true);
        setTotalPages(1);
        setActiveFilters(EMPTY_FILTERS);
        isInitialFilterEffect.current = true;

        fetchEvents(1);
        if (!widget) {
            fetchFacets();
        }
    }, [settings, widget]);

    // Check for autoexpand in querystring or settings
    useEffect(() => {
        const querystringAutoexpand = getQueryStringValue('autoexpand');
        const shouldAutoexpand = querystringAutoexpand !== null ?
            querystringAutoexpand === 'true' :
            (settings?.autoexpand || false);
        setAutoexpand(shouldAutoexpand);
    }, [settings?.autoexpand]);

    // Check for view in querystring or settings
    useEffect(() => {
        const querystringView = getQueryStringValue('view');
        const defaultView = querystringView !== null ?
            querystringView :
            (settings?.defaultView || 'list');
        if (defaultView === 'calendar' || defaultView === 'list') {
            setViewMode(defaultView);
        }
    }, [settings?.defaultView]);

    // Autoexpand all events if autoexpand is true
    useEffect(() => {
        if (autoexpand && events.length > 0) {
            setAllExpanded(true);
            setExpandedEvents(new Set(events.map(event => event.id)));
        }
    }, [autoexpand, events]);

    // Process external service bodies from sources array in API response
    const processServiceBodies = useCallback((sources) => {
        if (!sources || !Array.isArray(sources) || !settings?.sourceIds) {
            return;
        }

        // Clear any pending updates
        if (updateTimeout.current) {
            clearTimeout(updateTimeout.current);
        }

        // Debounce the service body update
        updateTimeout.current = setTimeout(() => {
            sources.forEach(source => {
                if (source.id !== 'local' && source.service_bodies) {
                    updateExternalServiceBodies(source.id, source.service_bodies);
                }
            });
        }, 300);
    }, [settings?.sourceIds, updateExternalServiceBodies]);

    // Set up intersection observer for infinite scroll
    useEffect(() => {
        if (!settings?.infiniteScroll || !loaderRef.current || !hasMore) return;
        
        const observer = new IntersectionObserver(entries => {
            if (entries[0].isIntersecting && hasMore && !loading && currentPage < totalPages) {
                fetchEvents(currentPage + 1);
            }
        }, { threshold: 1.0 });
        
        observer.observe(loaderRef.current);
        
        return () => {
            if (loaderRef.current) {
                observer.unobserve(loaderRef.current);
            }
        };
    }, [hasMore, loading, currentPage, totalPages, settings?.infiniteScroll]);

    const getQueryStringValue = (key, defaultValue = null) => {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.has(key) ? urlParams.get(key) : defaultValue;
    };

    const getRssUrl = () => {
        // Simple RSS feed URL - parameters come from shortcode configuration, not URL
        return `${window.location.pathname}?feed=mayo_rss`;
    };

    const getIcsUrl = () => {
        const params = new URLSearchParams();
        params.append('feed', 'mayo_events');

        // Use the same parameter logic as fetchEvents for ICS feed
        let eventType = getQueryStringValue('event_type') !== null ? getQueryStringValue('event_type') : (settings?.eventType || '');
        let serviceBody = getQueryStringValue('service_body') !== null ? getQueryStringValue('service_body') : (settings?.serviceBody || '');
        let relation = getQueryStringValue('relation') !== null ? getQueryStringValue('relation') : (settings?.relation || 'AND');
        let categories = getQueryStringValue('categories') !== null ? getQueryStringValue('categories') : (settings?.categories || '');
        let categoryRelation = getQueryStringValue('category_relation') !== null ? getQueryStringValue('category_relation') : (settings?.categoryRelation || 'OR');
        let tags = getQueryStringValue('tags') !== null ? getQueryStringValue('tags') : (settings?.tags || '');

        // Add parameters only if they have non-empty values
        if (eventType) params.append('event_type', eventType);
        if (serviceBody) params.append('service_body', serviceBody);
        if (relation !== 'AND') params.append('relation', relation);
        if (categories) params.append('categories', categories);
        if (categoryRelation !== 'OR') params.append('category_relation', categoryRelation);
        if (tags) params.append('tags', tags);
        
        const queryString = params.toString();
        return `${window.location.origin}${window.location.pathname}${queryString ? '?' + queryString : ''}`;
    };

    const generateShortcode = () => {
        const params = [];
        
        // Get effective values (querystring takes priority over settings)
        const effectiveValues = {
            timeFormat: settings?.timeFormat || '12hour',
            perPage: settings?.perPage || 10,
            infiniteScroll: settings?.infiniteScroll ?? true,
            autoexpand: settings?.autoexpand || false,
            categories: settings?.categories || '',
            categoryRelation: settings?.categoryRelation || 'OR',
            tags: settings?.tags || '',
            eventType: settings?.eventType || '',
            status: settings?.status || 'publish',
            serviceBody: settings?.serviceBody || '',
            sourceIds: settings?.sourceIds || ''
        };

        // Only include non-default values
        if (effectiveValues.timeFormat !== '12hour') {
            params.push(`time_format="${effectiveValues.timeFormat}"`);
        }
        if (effectiveValues.perPage !== 10) {
            params.push(`per_page="${effectiveValues.perPage}"`);
        }
        if (effectiveValues.infiniteScroll !== true) {
            params.push(`infinite_scroll="${effectiveValues.infiniteScroll ? 'true' : 'false'}"`);
        }
        if (effectiveValues.autoexpand !== false) {
            params.push(`autoexpand="${effectiveValues.autoexpand ? 'true' : 'false'}"`);
        }
        if (effectiveValues.categories) {
            params.push(`categories="${effectiveValues.categories}"`);
        }
        if (effectiveValues.categoryRelation !== 'OR') {
            params.push(`category_relation="${effectiveValues.categoryRelation}"`);
        }
        if (effectiveValues.tags) {
            params.push(`tags="${effectiveValues.tags}"`);
        }
        if (effectiveValues.eventType) {
            params.push(`event_type="${effectiveValues.eventType}"`);
        }
        if (effectiveValues.status !== 'publish') {
            params.push(`status="${effectiveValues.status}"`);
        }
        if (effectiveValues.serviceBody) {
            params.push(`service_body="${effectiveValues.serviceBody}"`);
        }
        if (effectiveValues.sourceIds) {
            params.push(`source_ids="${effectiveValues.sourceIds}"`);
        }
        
        return params.length > 0 ? `[mayo_event_list ${params.join(' ')}]` : '[mayo_event_list]';
    };

    const handleCopyShortcode = async () => {
        const shortcode = generateShortcode();
        try {
            await navigator.clipboard.writeText(shortcode);
            // You could add a toast notification here if desired
            console.log('Shortcode copied to clipboard:', shortcode);
        } catch (err) {
            console.error('Failed to copy shortcode:', err);
            // Fallback method
            const textArea = document.createElement('textarea');
            textArea.value = shortcode;
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                console.log('Shortcode copied to clipboard (fallback):', shortcode);
            } catch (fallbackErr) {
                console.error('Fallback copy failed:', fallbackErr);
            }
            document.body.removeChild(textArea);
        }
    };

    // Process events to add validation flags and move invalid dates to the end
    // Trust the order from the REST API - don't re-sort valid dates
    const processEvents = (eventList) => {
        const validEvents = [];
        const invalidEvents = [];

        eventList.forEach(event => {
            // Add validation flag
            const hasValidDate = event.meta.event_start_date &&
                event.meta.event_start_date !== '' &&
                !isNaN(new Date(event.meta.event_start_date).getTime());

            const processedEvent = {
                ...event,
                hasValidDate,
                isInvalid: !hasValidDate
            };

            if (hasValidDate) {
                validEvents.push(processedEvent);
            } else {
                invalidEvents.push(processedEvent);
            }
        });

        // Return valid events in the order received from API, followed by invalid events
        return [...validEvents, ...invalidEvents];
    };

    // Resolve a filter value with precedence: querystring > runtime selection > shortcode setting.
    // Locked filters always use the settings value (the shortcode pin) so a user can't widen
    // scope past what the site admin allowed. Runtime selection is an array (multi-select pills).
    const resolveFilterValue = (key, qsKey, settingsKey) => {
        const qs = getQueryStringValue(qsKey);
        if (qs !== null) return qs;
        if (lockedFilters.has(key)) {
            return settings?.[settingsKey] || '';
        }
        const runtime = activeFilters[key];
        if (Array.isArray(runtime) && runtime.length > 0) {
            return runtime.join(',');
        }
        return settings?.[settingsKey] || '';
    };

    const buildEventsQueryParams = (extra = {}) => {
        const params = new URLSearchParams();
        const status = getQueryStringValue('status') !== null ? getQueryStringValue('status') : (settings?.status || 'publish');
        const relation = getQueryStringValue('relation') !== null ? getQueryStringValue('relation') : (settings?.relation || 'AND');
        const categoryRelation = getQueryStringValue('category_relation') !== null ? getQueryStringValue('category_relation') : (settings?.categoryRelation || 'OR');
        const sourceIds = getQueryStringValue('source_ids') !== null ? getQueryStringValue('source_ids') : (settings?.sourceIds || '');
        const archive = getQueryStringValue('archive') !== null ? getQueryStringValue('archive') : (settings?.showArchived ? 'true' : 'false');
        const order = getQueryStringValue('order') !== null ? getQueryStringValue('order') : (settings?.order || 'ASC');

        params.append('status', status);
        params.append('event_type', resolveFilterValue('event_type', 'event_type', 'eventType'));
        params.append('service_body', resolveFilterValue('service_body', 'service_body', 'serviceBody'));
        params.append('relation', relation);
        params.append('categories', resolveFilterValue('categories', 'categories', 'categories'));
        params.append('category_relation', categoryRelation);
        params.append('tags', resolveFilterValue('tags', 'tags', 'tags'));
        params.append('source_ids', sourceIds);
        params.append('timezone', userTimezone);
        params.append('current_time', new Date().toISOString());
        params.append('archive', archive);
        params.append('order', order);

        Object.entries(extra).forEach(([k, v]) => params.append(k, String(v)));
        return params;
    };

    // Update fetchEvents to use processEvents
    const fetchEvents = async (page = 1) => {
        setLoading(true);
        try {
            const infiniteScroll = getQueryStringValue('infinite_scroll') !== null
                ? getQueryStringValue('infinite_scroll') === 'true'
                : (settings?.infiniteScroll ?? true);
            const perPage = getQueryStringValue('per_page') !== null
                ? parseInt(getQueryStringValue('per_page'))
                : (settings?.perPage || 10);

            const params = buildEventsQueryParams({ page, per_page: perPage });
            const data = await apiFetch(`/events?${params.toString()}`);

            // Handle both old and new response formats
            const events = Array.isArray(data) ? data : (data.events || []);
            const sources = data.sources || [];
            const pagination = data.pagination || {
                current_page: 1,
                total_pages: Math.ceil(events.length / (settings?.perPage || 10))
            };

            // Process service bodies from sources array
            if (sources.length > 0) {
                processServiceBodies(sources);
            }

            // Process events to handle invalid dates
            const processedEvents = processEvents(events);
            
            // Update pagination info
            setCurrentPage(pagination.current_page);
            setTotalPages(pagination.total_pages);
            setHasMore(pagination.current_page < pagination.total_pages);

            // For infinite scroll, append new events if it's not the first page
            if (page > 1 && infiniteScroll) {
                setEvents(prevEvents => [...prevEvents, ...processedEvents]);
            } else {
                setEvents(processedEvents);
            }
            
            setLoading(false);
        } catch (err) {
            console.error('Error in fetchEvents:', err);
            setError(`Failed to load events: ${err.message}`);
            setLoading(false);
            setHasMore(false);
        }
    };

    const handlePageChange = (newPage) => {
        if (settings?.infiniteScroll) return;

        setCurrentPage(newPage);
        fetchEvents(newPage);
        // Scroll to top of event list
        containerRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    // Fetch events for calendar view by date range
    const fetchCalendarEvents = async (year, month) => {
        setCalendarLoading(true);
        try {
            const startDate = `${year}-${String(month + 1).padStart(2, '0')}-01`;
            const lastDay = new Date(year, month + 1, 0).getDate();
            const endDate = `${year}-${String(month + 1).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;

            const params = buildEventsQueryParams({
                start_date: startDate,
                end_date: endDate,
                per_page: 100,
            });
            const data = await apiFetch(`/events?${params.toString()}`);

            // Handle both old and new response formats
            const fetchedEvents = Array.isArray(data) ? data : (data.events || []);
            const sources = data.sources || [];

            // Process service bodies from sources array
            if (sources.length > 0) {
                processServiceBodies(sources);
            }

            // Process events to handle invalid dates
            const processedEvents = processEvents(fetchedEvents);

            setCalendarEvents(processedEvents);
            setCalendarLoading(false);
        } catch (err) {
            console.error('Error in fetchCalendarEvents:', err);
            setCalendarLoading(false);
        }
    };

    // Handle calendar month change
    const handleCalendarMonthChange = (newDate) => {
        setCalendarDate(newDate);
        fetchCalendarEvents(newDate.getFullYear(), newDate.getMonth());
    };

    // Fetch calendar events when switching to calendar view or on initial load
    useEffect(() => {
        if (viewMode === 'calendar' && !isWidget) {
            fetchCalendarEvents(calendarDate.getFullYear(), calendarDate.getMonth());
        }
    }, [viewMode]);

    // Build the facets request URL using the shortcode-locked scope only — we
    // intentionally exclude runtime user selections so the dropdowns keep their
    // full option set as the user narrows results.
    const fetchFacets = async () => {
        try {
            const params = new URLSearchParams();
            const status = getQueryStringValue('status') !== null ? getQueryStringValue('status') : (settings?.status || 'publish');
            const sourceIds = getQueryStringValue('source_ids') !== null ? getQueryStringValue('source_ids') : (settings?.sourceIds || '');
            params.append('status', status);
            if (settings?.eventType) params.append('event_type', settings.eventType);
            if (settings?.serviceBody) params.append('service_body', settings.serviceBody);
            if (settings?.categories) params.append('categories', settings.categories);
            if (settings?.tags) params.append('tags', settings.tags);
            if (sourceIds) params.append('source_ids', sourceIds);
            params.append('timezone', userTimezone);

            const data = await apiFetch(`/events/facets?${params.toString()}`);
            setFacets({
                event_types: Array.isArray(data?.event_types) ? data.event_types : [],
                service_bodies: Array.isArray(data?.service_bodies) ? data.service_bodies : [],
                categories: Array.isArray(data?.categories) ? data.categories : [],
                tags: Array.isArray(data?.tags) ? data.tags : [],
            });
        } catch (err) {
            console.error('Error in fetchFacets:', err);
            setFacets(EMPTY_FACETS);
        }
    };

    const handleToggleFilter = (key, value) => {
        if (lockedFilters.has(key)) {
            return;
        }
        const current = Array.isArray(activeFilters[key]) ? activeFilters[key] : [];
        const next = current.includes(value)
            ? current.filter(v => v !== value)
            : [...current, value];
        setActiveFilters({ ...activeFilters, [key]: next });
    };

    const handleClearFilters = () => {
        setActiveFilters(EMPTY_FILTERS);
    };

    // Refetch whenever the user changes a filter. The init useEffect handles the
    // first load; this one fires only on subsequent activeFilters changes.
    useEffect(() => {
        if (isInitialFilterEffect.current) {
            isInitialFilterEffect.current = false;
            return;
        }
        setCurrentPage(1);
        setEvents([]);
        setHasMore(true);
        fetchEvents(1);
        if (viewMode === 'calendar' && !isWidget) {
            fetchCalendarEvents(calendarDate.getFullYear(), calendarDate.getMonth());
        }
    }, [activeFilters]);

    const handlePrint = () => {
        // Create a new window for printing
        const printWindow = window.open('', '_blank');
        
        // Get the current page title
        const pageTitle = document.title;
        
        // Create the print content
        const printContent = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>${pageTitle} - Print View</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        line-height: 1.6;
                        color: #333;
                        max-width: 800px;
                        margin: 0 auto;
                        padding: 20px;
                    }
                    .mayo-print-header {
                        text-align: center;
                        margin-bottom: 30px;
                        padding-bottom: 20px;
                        border-bottom: 2px solid #eee;
                    }
                    .mayo-print-event {
                        margin-bottom: 30px;
                        padding-bottom: 20px;
                        border-bottom: 1px solid #eee;
                    }
                    .mayo-print-event:last-child {
                        border-bottom: none;
                    }
                    .mayo-print-event-title {
                        font-size: 1.4em;
                        margin: 0 0 10px 0;
                        color: #0073aa;
                    }
                    .mayo-print-event-meta {
                        margin-bottom: 15px;
                        color: #666;
                    }
                    .mayo-print-event-description {
                        margin-top: 15px;
                    }
                    .mayo-print-event-taxonomies {
                        margin-top: 15px;
                    }
                    .mayo-print-event-taxonomy {
                        display: inline-block;
                        padding: 3px 8px;
                        margin: 0 5px 5px 0;
                        border-radius: 3px;
                        font-size: 0.9em;
                    }
                    .mayo-print-event-category {
                        background: #e9ecef;
                        color: #495057;
                    }
                    .mayo-print-event-tag {
                        background: #e5f5e8;
                        color: #1fa23d;
                    }
                    @media print {
                        body {
                            padding: 0;
                        }
                        .mayo-print-header {
                            margin-bottom: 20px;
                        }
                        .mayo-print-event {
                            page-break-inside: avoid;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="mayo-print-header">
                    <h1>${pageTitle}</h1>
                    <p>${__('Printed on', 'mayo-events-manager')} ${new Date().toLocaleString()}</p>
                </div>
                ${events.map(event => `
                    <div class="mayo-print-event">
                        <h2 class="mayo-print-event-title">${event.title.rendered}</h2>
                        <div class="mayo-print-event-meta">
                            <p><strong>${__('Date:', 'mayo-events-manager')}</strong> ${event.meta.event_start_date}${event.meta.event_start_time ? ` ${__('at', 'mayo-events-manager')} ${event.meta.event_start_time}` : ''}</p>
                            ${event.meta.event_type ? `<p><strong>${__('Type:', 'mayo-events-manager')}</strong> ${event.meta.event_type}</p>` : ''}
                            ${event.meta.location_name ? `<p><strong>${__('Location:', 'mayo-events-manager')}</strong> ${event.meta.location_name}</p>` : ''}
                        </div>
                        <div class="mayo-print-event-description">
                            ${event.content.rendered}
                        </div>
                        ${(event.categories.length > 0 || event.tags.length > 0) ? `
                            <div class="mayo-print-event-taxonomies">
                                ${event.categories.map(cat => `
                                    <span class="mayo-print-event-taxonomy mayo-print-event-category">${cat.name}</span>
                                `).join('')}
                                ${event.tags.map(tag => `
                                    <span class="mayo-print-event-taxonomy mayo-print-event-tag">${tag.name}</span>
                                `).join('')}
                            </div>
                        ` : ''}
                    </div>
                `).join('')}
            </body>
            </html>
        `;
        
        // Write the content to the new window
        printWindow.document.write(printContent);
        printWindow.document.close();
        
        // Wait for images to load before printing
        printWindow.onload = () => {
            printWindow.print();
            // Close the window after printing (optional)
            // printWindow.close();
        };
    };

    const hasActiveFilters = Object.values(activeFilters).some(arr => Array.isArray(arr) && arr.length > 0);

    const renderFilters = () => (!isWidget ? (
        <EventFilters
            facets={facets}
            selected={activeFilters}
            onToggle={handleToggleFilter}
            onClear={handleClearFilters}
            lockedFilters={lockedFilters}
        />
    ) : null);

    if (loading && events.length === 0) {
        return (
            <div className="mayo-event-list" ref={containerRef}>
                {renderFilters()}
                <div>{__('Loading events...', 'mayo-events-manager')}</div>
            </div>
        );
    }
    if (error && events.length === 0) {
        return (
            <div className="mayo-event-list" ref={containerRef}>
                {renderFilters()}
                <div className="mayo-error">{error}</div>
            </div>
        );
    }
    if (!events.length) {
        const emptyMessage = hasActiveFilters
            ? <div className="mayo-no-events">{__('No events match the selected filters.', 'mayo-events-manager')}</div>
            : (settings?.showArchived
                ? <div className="mayo-no-events">{__('No events found in the archive.', 'mayo-events-manager')}</div>
                : <div className="mayo-no-events">
                    {__('No upcoming events found.', 'mayo-events-manager')}{' '}
                    <a href={`${window.location.pathname}?archive=true`} className="mayo-archive-link">
                        {__('View past events', 'mayo-events-manager')}
                    </a>
                </div>);
        return (
            <div className="mayo-event-list" ref={containerRef}>
                {renderFilters()}
                {emptyMessage}
            </div>
        );
    }

    return (
        <div className={`mayo-event-list${viewMode === 'calendar' ? ' mayo-calendar-view' : ''}`} ref={containerRef}>
            {isWidget ? (
                <div className="mayo-widget-events">
                    {events.map(event => (
                        <EventWidgetCard 
                            key={`${event.id}-${event.meta.event_start_date}`}
                            event={event}
                            timeFormat={timeFormat}
                        />
                    ))}
                </div>
            ) : (
                <>
                    {renderFilters()}
                    <div className="mayo-event-list-header">
                        <div className="mayo-view-toggle">
                            <button
                                className={`mayo-view-toggle-button ${viewMode === 'list' ? 'active' : ''}`}
                                onClick={() => setViewMode('list')}
                                title={__('List View', 'mayo-events-manager')}
                            >
                                <span className="dashicons dashicons-list-view"></span>
                            </button>
                            <button
                                className={`mayo-view-toggle-button ${viewMode === 'calendar' ? 'active' : ''}`}
                                onClick={() => setViewMode('calendar')}
                                title={__('Calendar View', 'mayo-events-manager')}
                            >
                                <span className="dashicons dashicons-calendar-alt"></span>
                            </button>
                        </div>
                        <div className="mayo-event-list-actions">
                            {viewMode === 'list' && (
                                <button
                                    className="mayo-expand-all-button"
                                    onClick={() => setAllExpanded(!allExpanded)}
                                    title={allExpanded ? __('Collapse All', 'mayo-events-manager') : __('Expand All', 'mayo-events-manager')}
                                >
                                    <span className={`dashicons ${allExpanded ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2'}`}></span>
                                </button>
                            )}
                            <button
                                className="mayo-print-button"
                                onClick={handlePrint}
                                title={__('Print Events', 'mayo-events-manager')}
                            >
                                <span className="dashicons dashicons-printer"></span>
                            </button>
                            <a
                                href={getIcsUrl()}
                                className="mayo-rss-link"
                                target="_blank"
                                rel="noopener noreferrer"
                                title={__('Calendar Feed (ICS)', 'mayo-events-manager')}
                            >
                                <span className="dashicons dashicons-calendar"></span>
                            </a>
                            <a
                                href={getRssUrl()}
                                className="mayo-rss-link"
                                target="_blank"
                                rel="noopener noreferrer"
                                title={__('RSS Feed', 'mayo-events-manager')}
                            >
                                <span className="dashicons dashicons-rss"></span>
                            </a>
                            <button
                                className="mayo-shortcode-button"
                                onClick={() => setShowShortcode(!showShortcode)}
                                title={showShortcode ? __('Hide Shortcode', 'mayo-events-manager') : __('Show Shortcode', 'mayo-events-manager')}
                            >
                                <span className="dashicons dashicons-editor-code"></span>
                            </button>
                        </div>
                    </div>
                    {showShortcode && (
                        <div className="mayo-shortcode-display">
                            <div className="mayo-shortcode-header">
                                <strong>{__('Shortcode for this event list:', 'mayo-events-manager')}</strong>
                                <button
                                    className="mayo-copy-shortcode"
                                    onClick={handleCopyShortcode}
                                    title={__('Copy to Clipboard', 'mayo-events-manager')}
                                >
                                    <span className="dashicons dashicons-clipboard"></span>
                                    {__('Copy', 'mayo-events-manager')}
                                </button>
                            </div>
                            <div className="mayo-shortcode-text">
                                <code>{generateShortcode()}</code>
                            </div>
                        </div>
                    )}
                    {viewMode === 'calendar' ? (
                        <CalendarView
                            events={calendarEvents}
                            timeFormat={timeFormat}
                            onMonthChange={handleCalendarMonthChange}
                            loading={calendarLoading}
                        />
                    ) : (
                        <div className="mayo-event-cards">
                            {events.map(event => (
                                <EventCard
                                    key={`${event.id}-${event.meta.event_start_date}`}
                                    event={event}
                                    timeFormat={timeFormat}
                                    forceExpanded={allExpanded}
                                />
                            ))}

                            {/* Infinite scroll loading indicator */}
                            {getQueryStringValue('infinite_scroll') !== null ?
                                getQueryStringValue('infinite_scroll') === 'true' && hasMore && (
                                    <div ref={loaderRef} className="mayo-infinite-loader">
                                        {loading && <div className="mayo-loader">{__('Loading more events...', 'mayo-events-manager')}</div>}
                                    </div>
                                )
                                : settings?.infiniteScroll && hasMore && (
                                    <div ref={loaderRef} className="mayo-infinite-loader">
                                        {loading && <div className="mayo-loader">{__('Loading more events...', 'mayo-events-manager')}</div>}
                                    </div>
                                )
                            }
                        </div>
                    )}
                </>
            )}
        </div>
    );
};

export default EventList; 