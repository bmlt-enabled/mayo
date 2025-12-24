import { useState, useEffect } from '@wordpress/element';
import { useEventProvider } from '../providers/EventProvider';
import { formatTime, formatTimezone, formatRecurringPattern, apiFetch } from '../../util';
import LocationAddress from './LocationAddress';

const EventDetails = () => {
    const [event, setEvent] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [linkedAnnouncements, setLinkedAnnouncements] = useState([]);
    const { getServiceBodyName } = useEventProvider();

    useEffect(() => {
        const fetchEvent = async () => {
            try {
                const pathParts = window.location.pathname.split('/');
                const eventSlug = pathParts[pathParts.length - 2];

                const response = await apiFetch(`/event/${eventSlug}`);
                if (response) {
                    setEvent(response);
                    // Linked announcements are now included in the event response
                    if (response.linked_announcements) {
                        setLinkedAnnouncements(response.linked_announcements);
                    }
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

    return (
        <div className="mayo-single-container">
            <article className="mayo-single-event">
                <div className="mayo-single-event-content">
                    <header className="mayo-single-event-header">
                        <h1 className="mayo-single-event-title" dangerouslySetInnerHTML={{ __html: title.rendered }} />
                    </header>

                    {linkedAnnouncements.length > 0 && (
                        <div className="mayo-event-announcements">
                            {linkedAnnouncements.map(announcement => {
                                const priorityColors = {
                                    urgent: '#dc3545',
                                    high: '#ff9800',
                                    normal: '#0073aa',
                                    low: '#6c757d'
                                };
                                const borderColor = priorityColors[announcement.priority] || priorityColors.normal;

                                return (
                                    <div
                                        key={announcement.id}
                                        className="mayo-event-announcement-notice"
                                        style={{
                                            padding: '12px 16px',
                                            marginBottom: '16px',
                                            backgroundColor: '#fff8e1',
                                            borderLeft: `4px solid ${borderColor}`,
                                            borderRadius: '4px',
                                        }}
                                    >
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '4px' }}>
                                            <span className="dashicons dashicons-megaphone" style={{ color: borderColor, fontSize: '18px' }}></span>
                                            <strong style={{ fontSize: '15px' }}>{announcement.title}</strong>
                                            {announcement.priority && announcement.priority !== 'normal' && (
                                                <span
                                                    style={{
                                                        backgroundColor: borderColor,
                                                        color: '#fff',
                                                        padding: '2px 6px',
                                                        borderRadius: '3px',
                                                        fontSize: '10px',
                                                        textTransform: 'uppercase',
                                                    }}
                                                >
                                                    {announcement.priority}
                                                </span>
                                            )}
                                        </div>
                                        {announcement.excerpt && (
                                            <p
                                                style={{ margin: '8px 0 0', fontSize: '14px', color: '#555' }}
                                                dangerouslySetInnerHTML={{
                                                    __html: announcement.excerpt.replace(/<[^>]+>/g, '').substring(0, 200) + (announcement.excerpt.length > 200 ? '...' : '')
                                                }}
                                            />
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    {event.featured_image && (
                        <div className="mayo-single-event-image">
                            <div className="mayo-image-actions">
                            <a 
                                href={event.featured_image}
                                download
                                className="mayo-image-link"
                            >
                                Download Flyer
                            </a>
                        </div>
                            <a href={event.featured_image} target="_blank" rel="noopener noreferrer">
                                <img src={event.featured_image} alt={title.rendered} />
                            </a>
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
                                        <LocationAddress address={location_address} />
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
                                <p>{getServiceBodyName(service_body)}</p>
                            </div>
                        )}

                        <div className="mayo-single-event-datetime">
                            <h3>Date & Time</h3>
                            <p>
                                <strong>Start:</strong> {event.meta.event_start_date} at {formatTime(event.meta.event_start_time, '12hour')}
                                {event.meta.timezone && ` (${formatTimezone(event.meta.timezone)})`}
                            </p>
                            {(event.meta.event_end_date || event.meta.event_end_time) && (
                                <p>
                                    <strong>End:</strong> {event.meta.event_end_date || event.meta.event_start_date} at {formatTime(event.meta.event_end_time, '12hour')}
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
