import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import EventCard from './cards/EventCard';
import EventWidgetCard from './cards/EventWidgetCard';
import { useEventProvider } from '../providers/EventProvider';

const EventList = ({ widget = false, settings = {} }) => {
    const containerRef = useRef(null);
    const loaderRef = useRef(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [events, setEvents] = useState([]);
    const [currentPage, setCurrentPage] = useState(1);
    const [timeFormat, setTimeFormat] = useState('12hour');
    const [isWidget, setIsWidget] = useState(false);
    const [hasMore, setHasMore] = useState(true);
    const [totalPages, setTotalPages] = useState(1);
    const { updateExternalServiceBodies } = useEventProvider();
    
    // Get settings from props instead of global
    const {
        perPage = 10,
        showPagination = true,
        infiniteScroll = true,
        timeFormat: settingsTimeFormat = '12hour',
        showArchived = false,
        // ... other settings
    } = settings;

    // Get user's current timezone
    const userTimezone = Intl.DateTimeFormat().resolvedOptions().timezone || 'America/New_York';

    useEffect(() => {
        setIsWidget(widget);
        setTimeFormat(settingsTimeFormat);
        fetchEvents(1); // Reset to page 1 when settings change
    }, [settings]);

    // Process external service bodies when events are loaded
    useEffect(() => {
        if (events.length > 0) {
            // Group events by external source
            const externalSources = {};
            
            events.forEach(event => {
                if (event.external_source && event.external_source.service_bodies) {
                    externalSources[event.external_source.id] = event.external_source.service_bodies;
                }
            });
            
            // Update external service bodies for each source
            Object.entries(externalSources).forEach(([sourceId, serviceBodies]) => {
                updateExternalServiceBodies(sourceId, serviceBodies);
            });
        }
    }, [events, updateExternalServiceBodies]);

    // Set up intersection observer for infinite scroll
    useEffect(() => {
        if (!infiniteScroll || !loaderRef.current || !hasMore || widget) return;
        
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
    }, [hasMore, loading, currentPage, totalPages, infiniteScroll]);

    const getQueryStringValue = (key, defaultValue = null) => {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.has(key) ? urlParams.get(key) : defaultValue;
    };

    const getRssUrl = () => {
        const baseUrl = '/';
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
            let archive = getQueryStringValue('archive') !== null ? getQueryStringValue('archive') : (showArchived ? 'true' : 'false');

            // Build the endpoint URL with query parameters
            const endpoint = `/wp-json/event-manager/v1/events?status=${status}`
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
            
            const response = await fetch(endpoint);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            
            // Process events to handle invalid dates
            const processedEvents = processEvents(data.events);
            console.log('Events after processing:', processedEvents);
            
            // Update pagination info
            setCurrentPage(data.pagination.current_page);
            setTotalPages(data.pagination.total_pages);
            setHasMore(data.pagination.current_page < data.pagination.total_pages);

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
        if (infiniteScroll) return;
        
        setCurrentPage(newPage);
        fetchEvents(newPage);
        // Scroll to top of event list
        containerRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    if (loading && events.length === 0) return <div>Loading events...</div>;
    if (error && events.length === 0) return <div className="mayo-error">{error}</div>;
    if (!events.length) {
        if (showArchived) {
            return <div className="mayo-no-events">No events found in the archive. <span className="mayo-debug">(archive=true)</span></div>;
        } else {
            return <div className="mayo-no-events">
                No upcoming events found. 
                <a href={`${window.location.pathname}?archive=true`} className="mayo-archive-link">
                    View past events
                </a>
                <span className="mayo-debug">(timezone={userTimezone})</span>
            </div>;
        }
    }

    return (
        <div className="mayo-event-list" ref={containerRef}>
            <div className="mayo-event-list-header">
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
                <div className="mayo-event-cards">
                    {events.map(event => (
                        <EventCard 
                            key={`${event.id}-${event.meta.event_start_date}`}
                            event={event}
                            timeFormat={timeFormat}
                        />
                    ))}
                    
                    {/* Infinite scroll loading indicator */}
                    {infiniteScroll && hasMore && (
                        <div ref={loaderRef} className="mayo-infinite-loader">
                            {loading && <div className="mayo-loader">Loading more events...</div>}
                        </div>
                    )}
                </div>
            )}
            
            {/* Standard pagination */}
            {showPagination && totalPages > 1 && !infiniteScroll && (
                <div className="mayo-pagination">
                    <button
                        onClick={() => handlePageChange(currentPage - 1)}
                        disabled={currentPage === 1}
                        className="mayo-pagination-button"
                    >
                        Previous
                    </button>
                    
                    <span className="mayo-pagination-info">
                        Page {currentPage} of {totalPages}
                    </span>
                    
                    <button
                        onClick={() => handlePageChange(currentPage + 1)}
                        disabled={currentPage === totalPages}
                        className="mayo-pagination-button"
                    >
                        Next
                    </button>
                </div>
            )}
        </div>
    );
};

export default EventList; 