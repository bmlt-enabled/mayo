import { useState, useEffect } from '@wordpress/element';
import { Button } from '@wordpress/components';

const EventCard = ({ event }) => {
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
                            {event.meta.event_start_time} - {event.meta.event_end_time}
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

    useEffect(() => {
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
        <div className="mayo-event-list">
            {events.map(event => (
                <EventCard key={event.id} event={event} />
            ))}
        </div>
    );
};

export default EventList; 