import { useState, useEffect, useRef } from '@wordpress/element';
import { Button } from '@wordpress/components';

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

    return (
        <div className="mayo-event-card">
            <div 
                className="mayo-event-header"
                onClick={() => setIsExpanded(!isExpanded)}
            >
                <div className="mayo-event-summary">
                    <h3>{event.title.rendered}</h3>
                    <div className="mayo-event-brief">
                        <span className="mayo-event-type">{event.meta.event_type}</span>
                        <span className="mayo-event-datetime">
                            {new Date(event.meta.event_date).toLocaleDateString()} | {' '}
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
    const containerRef = useRef(null);
    const [timeFormat, setTimeFormat] = useState('12hour');

    useEffect(() => {
        // Get the container element and read the time format
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
                    const eventDate = new Date(`${event.meta.event_date} ${event.meta.event_start_time}`);
                    return eventDate > now;
                })
                .sort((a, b) => {
                    const dateA = new Date(`${a.meta.event_date} ${a.meta.event_start_time}`);
                    const dateB = new Date(`${b.meta.event_date} ${b.meta.event_start_time}`);
                    return dateA - dateB;
                });

            setEvents(upcomingEvents);
            setLoading(false);
        } catch (err) {
            setError('Failed to load events');
            setLoading(false);
        }
    };

    if (loading) return <div>Loading events...</div>;
    if (error) return <div className="mayo-error">{error}</div>;
    if (!events.length) return <div>No upcoming events</div>;

    return (
        <div className="mayo-event-list" ref={containerRef}>
            {events.map(event => (
                <EventCard 
                    key={event.id} 
                    event={event}
                    timeFormat={timeFormat}
                />
            ))}
        </div>
    );
};

export default EventList; 