import { useState, useEffect, useRef } from '@wordpress/element';
import { Button } from '@wordpress/components';
import EventCalendar from './EventCalendar';

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

const EventCard = ({ event, timeFormat }) => {
    const [isExpanded, setIsExpanded] = useState(false);
    const eventDate = new Date(event.meta.event_start_date);
    const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    return (
        <div className="mayo-event-card">
            <div 
                className="mayo-event-header"
                onClick={() => setIsExpanded(!isExpanded)}
            >
                <div className="mayo-event-date-badge">
                    <span className="mayo-event-day-name">{dayNames[eventDate.getDay()]}</span>
                    <span className="mayo-event-day-number">{eventDate.getDate()}</span>
                    <span className="mayo-event-month">{monthNames[eventDate.getMonth()]}</span>
                </div>
                <div className="mayo-event-summary">
                    <h3>{event.title.rendered}</h3>
                    <div className="mayo-event-brief">
                        <span className="mayo-event-type">{event.meta.event_type}</span>
                        <span className="mayo-event-time">
                            {formatTime(event.meta.event_start_time, timeFormat)} - {formatTime(event.meta.event_end_time, timeFormat)}
                        </span>
                    </div>
                </div>
                <span className={`mayo-caret dashicons ${isExpanded ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2'}`} />
            </div>
            {isExpanded && (
                <div className="mayo-event-details">
                    {event.meta.flyer_url && (
                        <div className="mayo-event-image">
                            <img src={event.meta.flyer_url} alt={event.title.rendered} />
                        </div>
                    )}
                    <div className="mayo-event-content">
                        <div 
                            className="mayo-event-description"
                            dangerouslySetInnerHTML={{ __html: event.content.rendered }}
                        />
                        {event.meta.recurring_schedule && (
                            <p className="mayo-event-recurring">
                                Recurring: {event.meta.recurring_schedule}
                            </p>
                        )}
                        {(event.meta.location_name || event.meta.location_address) && (
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
    const [view, setView] = useState('list');
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

    const fetchEvents = async () => {
        try {
            const response = await fetch('/wp-json/event-manager/v1/events');
            const data = await response.json();
            
            const now = new Date();
            const upcomingEvents = data
                .filter(event => {
                    const eventDate = new Date(`${event.meta.event_start_date} ${event.meta.event_start_time}`);
                    return eventDate > now;
                })
                .sort((a, b) => {
                    const dateA = new Date(`${a.meta.event_start_date} ${a.meta.event_start_time}`);
                    const dateB = new Date(`${b.meta.event_start_date} ${b.meta.event_start_time}`);
                    return dateA - dateB;
                });

            setEvents(upcomingEvents);
            setLoading(false);
        } catch (err) {
            setError('Failed to load events');
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
            {/* <div className="mayo-view-switcher">
                <button 
                    className={`mayo-view-button ${view === 'list' ? 'active' : ''}`}
                    onClick={() => setView('list')}
                >
                    List View
                </button>
                <button 
                    className={`mayo-view-button ${view === 'calendar' ? 'active' : ''}`}
                    onClick={() => setView('calendar')}
                >
                    Calendar View
                </button>
            </div> */}

            {view === 'list' ? (
                <>
                    {getPaginatedEvents().map(event => (
                        <EventCard 
                            key={event.id} 
                            event={event}
                            timeFormat={timeFormat}
                        />
                    ))}
                    
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
            ) : (
                <EventCalendar events={events} />
            )}
        </div>
    );
};

export default EventList; 