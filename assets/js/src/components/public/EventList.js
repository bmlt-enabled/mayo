import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import EventCard from './cards/EventCard';
import EventWidgetCard from './cards/EventWidgetCard';
import { useEventProvider } from '../providers/EventProvider';
import { apiFetch } from '../../util';

const EventList = ({ widget = false, settings = {} }) => {
    const containerRef = useRef(null);
    const loaderRef = useRef(null);
    const updateTimeout = useRef(null);
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
    const { updateExternalServiceBodies } = useEventProvider();
    
    // Get user's current timezone
    const userTimezone = Intl.DateTimeFormat().resolvedOptions().timezone || 'America/New_York';

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
        
        fetchEvents(1);
    }, [settings, widget]);

    // Check for autoexpand in querystring or settings
    useEffect(() => {
        const querystringAutoexpand = getQueryStringValue('autoexpand');
        const shouldAutoexpand = querystringAutoexpand !== null ? 
            querystringAutoexpand === 'true' : 
            (settings?.autoexpand || false);
        setAutoexpand(shouldAutoexpand);
    }, [settings?.autoexpand]);

    // Autoexpand all events if autoexpand is true
    useEffect(() => {
        if (autoexpand && events.length > 0) {
            setAllExpanded(true);
            setExpandedEvents(new Set(events.map(event => event.id)));
        }
    }, [autoexpand, events]);

    // Process external service bodies when events are loaded
    const processServiceBodies = useCallback((events) => {
        if (!events.length || !settings?.sourceIds) {
            return;
        }

        // Clear any pending updates
        if (updateTimeout.current) {
            clearTimeout(updateTimeout.current);
        }

        // Debounce the service body update
        updateTimeout.current = setTimeout(() => {
            const externalSources = new Map();
            
            events.forEach(event => {
                if (event.external_source && event.external_source.service_bodies) {
                    const sourceId = event.external_source.id;
                    const serviceBodies = event.external_source.service_bodies;
                    
                    if (settings.sourceIds.includes(sourceId)) {
                        externalSources.set(sourceId, serviceBodies);
                    }
                }
            });
            
            externalSources.forEach((serviceBodies, sourceId) => {
                updateExternalServiceBodies(sourceId, serviceBodies);
            });
        }, 300);
    }, [settings?.sourceIds, updateExternalServiceBodies]);

    // Call processServiceBodies when events change
    useEffect(() => {
        if (events.length > 0) {
            processServiceBodies(events);
        }
    }, [events, processServiceBodies]);

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
        const baseUrl = '';
        const params = new URLSearchParams();

        params.append('feed', 'mayo_events');
        
        // Use the same parameter logic as fetchEvents
        let eventType = getQueryStringValue('event_type') !== null ? getQueryStringValue('event_type') : (settings?.eventType || '');
        let serviceBody = getQueryStringValue('service_body') !== null ? getQueryStringValue('service_body') : (settings?.serviceBody || '');
        let relation = getQueryStringValue('relation') !== null ? getQueryStringValue('relation') : (settings?.relation || 'AND');
        let categories = getQueryStringValue('categories') !== null ? getQueryStringValue('categories') : (settings?.categories || '');
        let tags = getQueryStringValue('tags') !== null ? getQueryStringValue('tags') : (settings?.tags || '');
        
        // Add parameters only if they have non-empty values
        if (eventType) params.append('event_type', eventType);
        if (serviceBody) params.append('service_body', serviceBody);
        if (relation !== 'AND') params.append('relation', relation);
        if (categories) params.append('categories', categories);
        if (tags) params.append('tags', tags);
        
        const queryString = params.toString();
        return `${baseUrl}${queryString ? '?' + queryString : ''}`;
    };

    // Sort events properly on the client side as well to handle invalid dates
    const processEvents = (eventList) => {
        return eventList.map(event => {
            // Add validation flag
            const hasValidDate = event.meta.event_start_date && 
                event.meta.event_start_date !== '' && 
                !isNaN(new Date(event.meta.event_start_date).getTime());
            
            return {
                ...event,
                hasValidDate,
                isInvalid: !hasValidDate
            };
        }).sort((a, b) => {
            // Move invalid dates to the end
            if (!a.hasValidDate && !b.hasValidDate) return 0;
            if (!a.hasValidDate) return 1;
            if (!b.hasValidDate) return -1;
            
            // Sort by date for valid dates
            const dateA = new Date(`${a.meta.event_start_date}T${a.meta.event_start_time || '00:00:00'}`);
            const dateB = new Date(`${b.meta.event_start_date}T${b.meta.event_start_time || '00:00:00'}`);
            return dateA - dateB;
        });
    };

    // Update fetchEvents to use processEvents
    const fetchEvents = async (page = 1) => {
        setLoading(true);
        try {
            let status = getQueryStringValue('status') !== null ? getQueryStringValue('status') : (settings?.status || 'publish');
            let eventType = getQueryStringValue('event_type') !== null ? getQueryStringValue('event_type') : (settings?.eventType || '');
            let serviceBody = getQueryStringValue('service_body') !== null ? getQueryStringValue('service_body') : (settings?.serviceBody || '');
            let relation = getQueryStringValue('relation') !== null ? getQueryStringValue('relation') : (settings?.relation || 'AND');
            let categories = getQueryStringValue('categories') !== null ? getQueryStringValue('categories') : (settings?.categories || '');
            let tags = getQueryStringValue('tags') !== null ? getQueryStringValue('tags') : (settings?.tags || '');
            let sourceIds = getQueryStringValue('source_ids') !== null ? getQueryStringValue('source_ids') : (settings?.sourceIds || '');
            let archive = getQueryStringValue('archive') !== null ? getQueryStringValue('archive') : (settings?.showArchived ? 'true' : 'false');
            let infiniteScroll = getQueryStringValue('infinite_scroll') !== null ? getQueryStringValue('infinite_scroll') === 'true' : (settings?.infiniteScroll ?? true);
            let perPage = getQueryStringValue('per_page') !== null ? parseInt(getQueryStringValue('per_page')) : (settings?.perPage || 10);

            // Build the endpoint URL with query parameters
            const endpoint = `/events?status=${status}`
                + `&event_type=${eventType}`
                + `&service_body=${serviceBody}`
                + `&relation=${relation}`
                + `&categories=${categories}`
                + `&tags=${tags}`
                + `&source_ids=${sourceIds}`
                + `&page=${page}`
                + `&per_page=${perPage}`
                + `&timezone=${encodeURIComponent(userTimezone)}`
                + `&archive=${archive}`;
            
            const data = await apiFetch(endpoint);
            
            // Handle both old and new response formats
            const events = Array.isArray(data) ? data : (data.events || []);
            const pagination = data.pagination || {
                current_page: 1,
                total_pages: Math.ceil(events.length / (settings?.perPage || 10))
            };
            
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
                    <p>Printed on ${new Date().toLocaleString()}</p>
                </div>
                ${events.map(event => `
                    <div class="mayo-print-event">
                        <h2 class="mayo-print-event-title">${event.title.rendered}</h2>
                        <div class="mayo-print-event-meta">
                            <p><strong>Date:</strong> ${event.meta.event_start_date}${event.meta.event_start_time ? ` at ${event.meta.event_start_time}` : ''}</p>
                            ${event.meta.event_type ? `<p><strong>Type:</strong> ${event.meta.event_type}</p>` : ''}
                            ${event.meta.location_name ? `<p><strong>Location:</strong> ${event.meta.location_name}</p>` : ''}
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

    if (loading && events.length === 0) return <div>Loading events...</div>;
    if (error && events.length === 0) return <div className="mayo-error">{error}</div>;
    if (!events.length) {
        if (settings?.showArchived) {
            return <div className="mayo-no-events">No events found in the archive.</div>;
        } else {
            return <div className="mayo-no-events">
                No upcoming events found. 
                <a href={`${window.location.pathname}?archive=true`} className="mayo-archive-link">
                    View past events
                </a>
            </div>;
        }
    }

    return (
        <div className="mayo-event-list" ref={containerRef}>
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
                    <div className="mayo-event-list-header">
                        <div className="mayo-event-list-actions">
                            <button 
                                className="mayo-expand-all-button"
                                onClick={() => setAllExpanded(!allExpanded)}
                                title={allExpanded ? "Collapse All" : "Expand All"}
                            >
                                <span className={`dashicons ${allExpanded ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2'}`}></span>
                            </button>
                            <button 
                                className="mayo-print-button"
                                onClick={handlePrint}
                                title="Print Events"
                            >
                                <span className="dashicons dashicons-printer"></span>
                            </button>
                            <a 
                                href={getRssUrl()} 
                                className="mayo-rss-link" 
                                target="_blank" 
                                rel="noopener noreferrer"
                                title="Calendar Feed"
                            >
                                <span className="dashicons dashicons-calendar"></span>
                            </a>
                        </div>
                    </div>
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
                                    {loading && <div className="mayo-loader">Loading more events...</div>}
                                </div>
                            )
                            : settings?.infiniteScroll && hasMore && (
                                <div ref={loaderRef} className="mayo-infinite-loader">
                                    {loading && <div className="mayo-loader">Loading more events...</div>}
                                </div>
                            )
                        }
                    </div>
                </>
            )}
        </div>
    );
};

export default EventList; 