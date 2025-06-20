import { useState, useEffect } from '@wordpress/element';
import { formatTimezone, apiFetch } from '../../util'; // Import the helper function
import { useEventProvider } from '../providers/EventProvider';

const EventArchive = () => {
    const [events, setEvents] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const { getServiceBodyName } = useEventProvider();

    useEffect(() => {
        const fetchEvents = async () => {
            try {
                const response = await apiFetch('/events?archive=true');
                
                // Ensure we have a valid response and it's an array
                if (response && Array.isArray(response)) {
                    setEvents(response);
                } else if (response && response.events && Array.isArray(response.events)) {
                    setEvents(response.events);
                } else {
                    console.warn('Unexpected API response format:', response);
                    setEvents([]);
                }
            } catch (err) {
                console.error('Error fetching events:', err);
                setError('Failed to load events');
                setEvents([]);
            } finally {
                setLoading(false);
            }
        };

        fetchEvents();
    }, []);

    if (loading) return <div>Loading events...</div>;
    if (error) return <div className="mayo-error">{error}</div>;
    if (!events.length) return <div className="mayo-no-events">No archived events found.</div>;

    return (
        <div className="mayo-archive-container">
            <div className="mayo-archive-content">
                <header className="mayo-archive-header">
                    <h1 className="mayo-archive-title">Events</h1>
                </header>

                <div className="mayo-archive-events">
                    {events.map(event => {
                        return (
                            <article key={event.id} className="mayo-archive-event">
                                <div className="mayo-archive-event-content">
                                    {event.featured_image && (
                                        <div className="mayo-archive-event-image">
                                            <a href={event.featured_image} target="_blank" rel="noopener noreferrer">
                                                <img src={event.featured_image} alt={event.title.rendered} />
                                            </a>
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

                                            {event.categories?.length > 0 && (
                                                <div className="mayo-archive-event-categories">
                                                    {event.categories.map(cat => (
                                                        <span key={cat.id} className="mayo-event-category">
                                                            {cat.name}
                                                        </span>
                                                    ))}
                                                </div>
                                            )}

                                            {event.tags?.length > 0 && (
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

                                        {event.meta.service_body && (
                                            <p><strong>Service Body:</strong> {getServiceBodyName(event.meta.service_body)}</p>
                                        )}

                                        <a href={event.link} className="mayo-archive-event-link">
                                            View Event Details
                                        </a>
                                    </div>
                                </div>
                            </article>
                        );
                    })}
                </div>
            </div>
        </div>
    );
};

export default EventArchive; 