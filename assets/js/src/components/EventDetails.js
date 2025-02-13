import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const EventDetails = () => {
    const [event, setEvent] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        const fetchEvent = async () => {
            try {
                const pathParts = window.location.pathname.split('/');
                const eventSlug = pathParts[pathParts.length - 2];

                const response = await apiFetch({
                    path: `/wp-json/event-manager/v1/event/${eventSlug}`
                });
                if (response) {
                    setEvent(response);
                } else {
                    throw new Error('Event not found');
                }
            } catch (err) {
                console.error('Error fetching event:', err);
                setError('Failed to load event details');
            } finally {
                setLoading(false);
            }
        };

        fetchEvent();
    }, []);

    if (loading) return <div>Loading event details...</div>;
    if (error) return <div className="mayo-error">{error}</div>;
    if (!event) return <div>Event not found</div>;

    const {
        title,
        content,
        meta: {
            event_type,
            event_start_date,
            event_end_date,
            event_start_time,
            event_end_time,
            timezone,
            location_name,
            location_address,
            location_details,
            recurring_pattern,
            service_body
        }
    } = event;

    const formatRecurringPattern = (pattern) => {
        if (!pattern || pattern.type === 'none') return null;
        
        const { type, interval, weekdays = [], endDate } = pattern;
        let text = 'This event repeats ';
        
        switch (type) {
            case 'daily':
                text += interval > 1 ? `every ${interval} days` : 'daily';
                break;
            case 'weekly':
                text += interval > 1 ? `every ${interval} weeks` : 'weekly';
                if (weekdays.length) {
                    const days = weekdays.map(day => {
                        const weekdayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                        return weekdayNames[day];
                    });
                    text += ` on ${days.join(', ')}`;
                }
                break;
            case 'monthly':
                text += interval > 1 ? `every ${interval} months` : 'monthly';
                break;
        }
        
        if (endDate) {
            text += ` until ${endDate}`;
        }
        
        return text;
    };

    return (
        <div className="mayo-single-container">
            <article className="mayo-single-event">
                <div className="mayo-single-event-content">
                    <header className="mayo-single-event-header">
                        <h1 className="mayo-single-event-title">{title.rendered}</h1>
                    </header>

                    {event.featured_image && (
                        <div className="mayo-single-event-image">
                            <img src={event.featured_image} alt={title.rendered} />
                        </div>
                    )}

                    <div className="mayo-single-event-description">
                        <h3>Description</h3>
                        <div dangerouslySetInnerHTML={{ __html: content.rendered }} />
                    </div>

                    {(location_name || location_address || location_details) && (
                            <div className="mayo-single-event-location">
                                <h3>Location</h3>
                                {location_name && (
                                    <p className="mayo-location-name">{location_name}</p>
                                )}
                                {location_address && (
                                    <p className="mayo-location-address">
                                        <a 
                                            href={`https://maps.google.com?q=${encodeURIComponent(location_address)}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            {location_address}
                                        </a>
                                    </p>
                                )}
                                {location_details && (
                                    <p className="mayo-location-details">{location_details}</p>
                                )}
                            </div>
                        )}

                    <div className="mayo-single-event-meta">
                        {event_type && (
                            <div className="mayo-single-event-type">
                                <h3>Event Type</h3>
                                <p>{event_type}</p>
                            </div>
                        )}

                        {service_body && (
                            <div className="mayo-single-event-service-body">
                                <h3>Service Body</h3>
                                <p>{service_body}</p>
                            </div>
                        )}

                        <div className="mayo-single-event-datetime">
                            <h3>Date & Time</h3>
                            <p>
                                <strong>Start:</strong> {event_start_date}
                                {event_start_time && ` at ${event_start_time}`}
                                {timezone && ` (${timezone})`}
                            </p>
                            {(event_end_date || event_end_time) && (
                                <p>
                                    <strong>End:</strong> {event_end_date || event_start_date}
                                    {event_end_time && ` at ${event_end_time}`}
                                </p>
                            )}
                        </div>

                        {recurring_pattern && recurring_pattern.type !== 'none' && (
                            <div className="mayo-single-event-recurrence">
                                <h3>Recurring Event</h3>
                                <p>{formatRecurringPattern(recurring_pattern)}</p>
                            </div>
                        )}

                        <div className="mayo-single-event-taxonomies">
                            {event.categories?.length > 0 && (
                                <div className="mayo-single-event-categories">
                                    <h3>Categories</h3>
                                    {event.categories.map(cat => (
                                        <a key={cat.id} href={cat.link}>{cat.name}</a>
                                    ))}
                                </div>
                            )}

                            {event.tags?.length > 0 && (
                                <div className="mayo-single-event-tags">
                                    <h3>Tags</h3>
                                    {event.tags.map(tag => (
                                        <a key={tag.id} href={tag.link}>{tag.name}</a>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </article>
        </div>
    );
};

export default EventDetails;
