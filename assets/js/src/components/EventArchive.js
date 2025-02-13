import { useState, useEffect } from '@wordpress/element';
import { formatTimezone } from './EventList'; // Import the helper function

const EventArchive = () => {
    const [events, setEvents] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    console.log('EventArchive component mounted');

    useEffect(() => {
        fetchEvents();
    }, []);

    const fetchEvents = async () => {
        try {
            console.log('Fetching events...');
            const response = await fetch('/wp-json/event-manager/v1/events?archive=true');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            console.log('Events fetched:', data);
            setEvents(data);
            setLoading(false);
        } catch (err) {
            console.error('Error fetching events:', err);
            setError('Failed to load events');
            setLoading(false);
        }
    };

    if (loading) return <div>Loading events...</div>;
    if (error) return <div>{error}</div>;

    return (
        <div className="mayo-archive-container">
            <div className="mayo-archive-content">
                <header className="mayo-archive-header">
                    <h1 className="mayo-archive-title">Events</h1>
                </header>

                <div className="mayo-archive-events">
                    {events.map(event => (
                        <article key={event.id} className="mayo-archive-event">
                            <div className="mayo-archive-event-content">
                                {event.meta.flyer_url && (
                                    <div className="mayo-archive-event-image">
                                        <img src={event.meta.flyer_url} alt={event.title.rendered} />
                                    </div>
                                )}

                                <div className="mayo-archive-event-details">
                                    <h2 className="mayo-archive-event-title">
                                        <a href={event.link} dangerouslySetInnerHTML={{ __html: event.title.rendered }} />
                                    </h2>

                                    <div className="mayo-archive-event-meta">
                                        {event.meta.event_type && (
                                            <div className="mayo-archive-event-type">
                                                <strong>Type:</strong> {event.meta.event_type}
                                            </div>
                                        )}

                                        <div className="mayo-archive-event-datetime">
                                            <strong>When:</strong>{' '}
                                            {event.meta.event_start_date}
                                            {event.meta.event_start_time && ` at ${event.meta.event_start_time}`}
                                            {(event.meta.event_end_date || event.meta.event_end_time) && ' - '}
                                            {event.meta.event_end_date}
                                            {event.meta.event_end_time && ` at ${event.meta.event_end_time}`}
                                            {event.meta.timezone && ` (${formatTimezone(event.meta.timezone)})`}
                                        </div>

                                        {(event.meta.location_name || event.meta.location_address) && (
                                            <div className="mayo-archive-event-location">
                                                <strong>Where:</strong>{' '}
                                                {event.meta.location_name}
                                                {event.meta.location_name && event.meta.location_address && ', '}
                                                {event.meta.location_address}
                                            </div>
                                        )}

                                        {event.categories.length > 0 && (
                                            <div className="mayo-archive-event-categories">
                                                {event.categories.map(cat => (
                                                    <span key={cat.id} className="mayo-event-category">
                                                        {cat.name}
                                                    </span>
                                                ))}
                                            </div>
                                        )}

                                        {event.tags.length > 0 && (
                                            <div className="mayo-archive-event-tags">
                                                {event.tags.map(tag => (
                                                    <span key={tag.id} className="mayo-event-tag">
                                                        {tag.name}
                                                    </span>
                                                ))}
                                            </div>
                                        )}
                                    </div>

                                    <div 
                                        className="mayo-archive-event-excerpt"
                                        dangerouslySetInnerHTML={{ __html: event.content.rendered }}
                                    />

                                    <a href={event.link} className="mayo-archive-event-link">
                                        View Event Details
                                    </a>
                                </div>
                            </div>
                        </article>
                    ))}
                </div>
            </div>
        </div>
    );
};

export default EventArchive; 