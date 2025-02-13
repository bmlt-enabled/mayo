import { useState, useEffect } from '@wordpress/element';

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
            
            // Filter and sort events
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
                <div key={event.id} className="mayo-event-card">
                    {event.meta.flyer_url && (
                        <div className="mayo-event-image">
                            <img src={event.meta.flyer_url} alt={event.title.rendered} />
                        </div>
                    )}
                    <div className="mayo-event-content">
                        <h3>{event.title.rendered}</h3>
                        <div className="mayo-event-details">
                            <p className="mayo-event-type">{event.meta.event_type}</p>
                            <p className="mayo-event-datetime">
                                {new Date(event.meta.event_date).toLocaleDateString()} | {' '}
                                {event.meta.event_start_time} - {event.meta.event_end_time}
                            </p>
                        </div>
                        <div 
                            className="mayo-event-description"
                            dangerouslySetInnerHTML={{ __html: event.content.rendered }}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
};

export default EventList; 