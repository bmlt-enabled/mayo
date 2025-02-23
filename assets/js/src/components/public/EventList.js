import { useState, useEffect, useRef } from '@wordpress/element';
import { useEventProvider } from '../providers/EventProvider';

const formatTime = (time, format) => {
    if (!time) return '';
    
    if (format === '24hour') {
        return time;
    }
    
    // Convert to 12-hour format
    const [hours, minutes] = time.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
};

export const formatTimezone = (timezone) => {
    try {
        const date = new Date();
        const timeString = date.toLocaleTimeString('en-US', { timeZone: timezone, timeZoneName: 'short' });
        return timeString.split(' ')[2]; // Extract timezone abbreviation (e.g., EST, CST)
    } catch (e) {
        return timezone.split('/').pop().replace('_', ' '); // Fallback to city name
    }
};

const EventCard = ({ event, timeFormat }) => {
    const [isExpanded, setIsExpanded] = useState(false);
    // Create date object for display (using only the date part)
    const eventDate = new Date(event.meta.event_start_date + 'T00:00:00');
    const { getServiceBodyName } = useEventProvider();

    const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    const formatRecurringPattern = (pattern) => {
        if (!pattern || pattern.type === 'none') return '';
        
        const { type, interval, weekdays = [], endDate, monthlyType, monthlyWeekday, monthlyDate } = pattern;
        let text = "Repeats ";
        
        switch (type) {
            case 'daily':
                text += interval > 1 ? `every ${interval} days` : "daily";
                break;
            case 'weekly':
                text += interval > 1 ? `every ${interval} weeks` : "weekly";
                if (weekdays && weekdays.length) {
                    const days = weekdays.map(day => {
                        return dayNames[parseInt(day)];
                    });
                    text += ` on ${days.join(', ')}`;
                }
                break;
            case 'monthly':
                text += interval > 1 ? `every ${interval} months` : "monthly";
                if (monthlyType === 'date' && monthlyDate) {
                    text += ` on day ${monthlyDate}`;
                } else if (monthlyType === 'weekday' && monthlyWeekday) {
                    const [week, weekday] = monthlyWeekday.split(',').map(Number);
                    const weekText = week > 0 
                        ? ['first', 'second', 'third', 'fourth', 'fifth'][week - 1] 
                        : 'last';
                    text += ` on the ${weekText} ${dayNames[weekday]}`;
                }
                break;
            default:
                return '';
        }
        
        if (endDate) {
            text += ` until ${endDate}`;
        }
        
        return text;
    };

    return (
        <div className="mayo-event-card">
            <div 
                className="mayo-event-header"
                onClick={() => setIsExpanded(!isExpanded)}
            >
                <div className="mayo-event-date-badge">
                    {eventDate && !isNaN(eventDate.getTime()) ? (
                        <>
                            <span className="mayo-event-day-name">{dayNames[eventDate.getDay()]}</span>
                            <span className="mayo-event-day-number">{eventDate.getDate()}</span>
                            <span className="mayo-event-month">{monthNames[eventDate.getMonth()]}</span>
                        </>
                    ) : (
                        <span className="mayo-event-date-error">Invalid Date</span>
                    )}
                </div>
                <div className="mayo-event-summary">
                    <h3>{event.title.rendered}</h3>
                    <div className="mayo-event-brief">
                        <span className="mayo-event-type">{event.meta.event_type}</span>
                        <span className="mayo-event-time">
                            {formatTime(event.meta.event_start_time, timeFormat)} - {formatTime(event.meta.event_end_time, timeFormat)}
                            {event.meta.timezone && (
                                <span className="mayo-event-timezone">
                                    {' '}({formatTimezone(event.meta.timezone)})
                                </span>
                            )}
                        </span>
                        {(event.categories.length > 0 || event.tags.length > 0) && (
                            <div className="mayo-event-brief-taxonomies">
                                {event.categories.map(cat => (
                                    <span key={cat.id} className="mayo-event-category mayo-event-category-small">
                                        {cat.name}
                                    </span>
                                ))}
                                {event.tags.map(tag => (
                                    <span key={tag.id} className="mayo-event-tag mayo-event-tag-small">
                                        {tag.name}
                                    </span>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
                <span className={`mayo-caret dashicons ${isExpanded ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2'}`} />
            </div>
            {isExpanded && (
                <div className="mayo-event-details">
                    <div className="mayo-event-content">
                        <div className="mayo-event-metadata">
                            <div className="mayo-event-datetime-details">
                                <h4>Date & Time</h4>
                                <p>
                                    <strong>Start:</strong> {event.meta.event_start_date} at {formatTime(event.meta.event_start_time, timeFormat)}
                                    {event.meta.timezone && ` (${formatTimezone(event.meta.timezone)})`}
                                </p>
                                {(event.meta.event_end_date || event.meta.event_end_time) && (
                                    <p>
                                        <strong>End:</strong> {event.meta.event_end_date || event.meta.event_start_date} at {formatTime(event.meta.event_end_time, timeFormat)}
                                    </p>
                                )}
                            </div>

                            {event.meta.event_type && (
                                <div className="mayo-event-type-details">
                                    <h4>Event Type</h4>
                                    <p>{event.meta.event_type}</p>
                                </div>
                            )}
                        </div>

                        <div className="mayo-event-description">
                            <h4>Description</h4>
                            <div dangerouslySetInnerHTML={{ __html: event.content.rendered }} />
                        </div>

                        {event.featured_image && (
                            <div className="mayo-event-image">
                                <h4>Event Flyer</h4>
                                <a href={event.featured_image} target="_blank" rel="noopener noreferrer">
                                    <img src={event.featured_image} alt={event.title.rendered} />
                                </a>
                            </div>
                        )}

                        {(event.meta.location_name || event.meta.location_address || event.meta.location_details) && (
                            <div className="mayo-event-location">
                                <h4>Location</h4>
                                {event.meta.location_name && (
                                    <p className="mayo-location-name">{event.meta.location_name}</p>
                                )}
                                {event.meta.location_address && (
                                    <p className="mayo-location-address">
                                        <a 
                                            href={`https://maps.google.com?q=${encodeURIComponent(event.meta.location_address)}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            {event.meta.location_address}
                                        </a>
                                    </p>
                                )}
                                {event.meta.location_details && (
                                    <p className="mayo-location-details">{event.meta.location_details}</p>
                                )}
                            </div>
                        )}

                        {event.meta.service_body && (
                            <div className="mayo-event-service-body">
                                <h4>Service Body</h4>
                                <p>{getServiceBodyName(event.meta.service_body)}</p>
                            </div>
                        )}

                        {(event.categories.length > 0 || event.tags.length > 0) && (
                            <div className="mayo-event-taxonomies">
                                {event.categories.length > 0 && (
                                    <div className="mayo-event-categories">
                                        <h4>Categories</h4>
                                        <div className="mayo-taxonomy-list">
                                            {event.categories.map(cat => (
                                                <span key={cat.id} className="mayo-event-category">
                                                    {cat.name}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}
                                {event.tags.length > 0 && (
                                    <div className="mayo-event-tags">
                                        <h4>Tags</h4>
                                        <div className="mayo-taxonomy-list">
                                            {event.tags.map(tag => (
                                                <span key={tag.id} className="mayo-event-tag">
                                                    {tag.name}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        {event.meta.recurring_pattern && event.meta.recurring_pattern.type !== 'none' && (
                            <div className="mayo-event-recurring">
                                {formatRecurringPattern(event.meta.recurring_pattern)}
                            </div>
                        )}

                        <div className="mayo-event-actions">
                            <a 
                                href={event.link} 
                                className="mayo-read-more"
                                onClick={(e) => e.stopPropagation()}
                            >
                                Read More
                            </a>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

const EventList = () => {
    const [events, setEvents] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [currentPage, setCurrentPage] = useState(1);
    const containerRef = useRef(null);
    const [timeFormat, setTimeFormat] = useState('12hour');
    
    // Get settings from PHP
    const perPage = window.mayoEventSettings?.perPage || 10;
    const showPagination = window.mayoEventSettings?.showPagination !== false;
    
    useEffect(() => {
        const container = document.getElementById('mayo-event-list');
        if (container) {
            const format = container.dataset.timeFormat || '12hour';
            setTimeFormat(format);
        }
        
        fetchEvents();
    }, []);


    const getQueryStringValue = (key, defaultValue = null) => {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.has(key) ? urlParams.get(key) : defaultValue;
    };

    const fetchEvents = async () => {
        try {
            let status = getQueryStringValue('status') !== null ? getQueryStringValue('status') : (window.mayoEventSettings?.status || 'publish');
            let eventType = getQueryStringValue('event_type') !== null ? getQueryStringValue('event_type') : (window.mayoEventSettings?.eventType || '');
            let serviceBody = getQueryStringValue('service_body') !== null ? getQueryStringValue('service_body') : (window.mayoEventSettings?.serviceBody || '');
            let relation = getQueryStringValue('relation') !== null ? getQueryStringValue('relation') : (window.mayoEventSettings?.relation || 'AND');
            let categories = getQueryStringValue('categories') !== null ? getQueryStringValue('categories') : (window.mayoEventSettings?.categories || '');
            let tags = getQueryStringValue('tags') !== null ? getQueryStringValue('tags') : (window.mayoEventSettings?.tags || '');
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
            <>
                <div className="mayo-event-cards">
                    {getPaginatedEvents().map(event => (
                        <EventCard 
                            key={`${event.id}-${event.meta.event_start_date}`}
                            event={event}
                            timeFormat={timeFormat}
                        />
                    ))}
                </div>
                
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
            </>
        </div>
    );
};

export default EventList; 