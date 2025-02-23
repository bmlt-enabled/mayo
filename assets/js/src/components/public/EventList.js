import { useState, useEffect, useRef } from '@wordpress/element';
import EventCard from './cards/EventCard';
import EventWidgetCard from './cards/EventWidgetCard';

const EventList = ({ widget = false, settings = {} }) => {
    const containerRef = useRef(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [events, setEvents] = useState([]);
    const [currentPage, setCurrentPage] = useState(1);
    const [timeFormat, setTimeFormat] = useState('12hour');
    const [isWidget, setIsWidget] = useState(false);
    
    // Get settings from props instead of global
    const {
        perPage = 10,
        showPagination = true,
        timeFormat: settingsTimeFormat = '12hour',
        // ... other settings
    } = settings;

    useEffect(() => {
        setIsWidget(widget);
        setTimeFormat(settingsTimeFormat);
        fetchEvents();
    }, [settings]);

    const getQueryStringValue = (key, defaultValue = null) => {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.has(key) ? urlParams.get(key) : defaultValue;
    };

    const fetchEvents = async () => {
        try {
            let status = getQueryStringValue('status') !== null ? getQueryStringValue('status') : (settings?.status || 'publish');
            let eventType = getQueryStringValue('event_type') !== null ? getQueryStringValue('event_type') : (settings?.eventType || '');
            let serviceBody = getQueryStringValue('service_body') !== null ? getQueryStringValue('service_body') : (settings?.serviceBody || '');
            let relation = getQueryStringValue('relation') !== null ? getQueryStringValue('relation') : (settings?.relation || 'AND');
            let categories = getQueryStringValue('categories') !== null ? getQueryStringValue('categories') : (settings?.categories || '');
            let tags = getQueryStringValue('tags') !== null ? getQueryStringValue('tags') : (settings?.tags || '');
            // Build the endpoint URL with query parameters
            const endpoint = `/wp-json/event-manager/v1/events?status=${status}`
                + `&event_type=${eventType}`
                + `&service_body=${serviceBody}`
                + `&relation=${relation}`
                + `&categories=${categories}`
                + `&tags=${tags}`;
            
            const response = await fetch(endpoint);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            
            const now = new Date();
            const upcomingEvents = data
                .filter(event => {
                    // Date filter with timezone
                    const eventDate = new Date(`${event.meta.event_start_date}T${event.meta.event_start_time || '00:00:00'}${event.meta.timezone ? 
                        new Date().toLocaleString('en-US', { timeZone: event.meta.timezone, timeZoneName: 'short' }).split(' ')[2] : ''}`);
                    if (eventDate <= now) return false;

                    return true;
                })
                .sort((a, b) => {
                    // Create date objects with timezone consideration
                    const dateA = new Date(`${a.meta.event_start_date}T${a.meta.event_start_time || '00:00:00'}${a.meta.timezone ? 
                        new Date().toLocaleString('en-US', { timeZone: a.meta.timezone, timeZoneName: 'short' }).split(' ')[2] : ''}`);
                    const dateB = new Date(`${b.meta.event_start_date}T${b.meta.event_start_time || '00:00:00'}${b.meta.timezone ? 
                        new Date().toLocaleString('en-US', { timeZone: b.meta.timezone, timeZoneName: 'short' }).split(' ')[2] : ''}`);
                    
                    if (isNaN(dateA.getTime()) || isNaN(dateB.getTime())) {
                        // If either date is invalid, fall back to comparing just the date strings
                        return a.meta.event_start_date.localeCompare(b.meta.event_start_date);
                    }
                    
                    return dateA - dateB;
                });

            setEvents(upcomingEvents);
            setLoading(false);
        } catch (err) {
            console.error('Error in fetchEvents:', err);
            setError(`Failed to load events: ${err.message}`);
            setLoading(false);
        }
    };

    // Get paginated events
    const getPaginatedEvents = () => {
        const startIndex = (currentPage - 1) * perPage;
        const endIndex = startIndex + perPage;
        return events.slice(startIndex, endIndex);
    };

    // Total number of pages
    const totalPages = Math.ceil(events.length / perPage);

    const handlePageChange = (newPage) => {
        setCurrentPage(newPage);
        // Scroll to top of event list
        containerRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    if (loading) return <div>Loading events...</div>;
    if (error) return <div className="mayo-error">{error}</div>;
    if (!events.length) return <div>No upcoming events</div>;

    return (
        <div className="mayo-event-list" ref={containerRef}>
            {isWidget ? (
                <div className="mayo-widget-events">
                    {getPaginatedEvents().map(event => (
                        <EventWidgetCard 
                            key={`${event.id}-${event.meta.event_start_date}`}
                            event={event}
                            timeFormat={timeFormat}
                        />
                    ))}
                </div>
            ) : (
                <div className="mayo-event-cards">
                    {getPaginatedEvents().map(event => (
                        <EventCard 
                            key={`${event.id}-${event.meta.event_start_date}`}
                            event={event}
                            timeFormat={timeFormat}
                        />
                    ))}
                </div>
            )}
            
            {showPagination && totalPages > 1 && (
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