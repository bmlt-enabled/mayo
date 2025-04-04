import { useState, useEffect } from '@wordpress/element';
import { formatTimezone } from '../../util'; // Import the helper function
import { useEventProvider } from '../providers/EventProvider';
import apiFetch from '@wordpress/api-fetch';

const EventArchive = () => {
    const [events, setEvents] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const { getServiceBodyName } = useEventProvider();
    const [pdfEmbedStates, setPdfEmbedStates] = useState({});

    useEffect(() => {
        const fetchEvents = async () => {
            try {
                const response = await apiFetch({ path: '/wp-json/event-manager/v1/events?archive=true' });
                if (response) {
                    setEvents(response);
                } else {
                    throw new Error('No events found');
                }
            } catch (err) {
                console.error('Error fetching events:', err);
                setError('Failed to load events');
            } finally {
                setLoading(false);
            }
        };

        fetchEvents();
    }, []);

    const togglePdfEmbed = (eventId) => {
        setPdfEmbedStates(prev => ({
            ...prev,
            [eventId]: !prev[eventId]
        }));
    };

    if (loading) return <div>Loading events...</div>;
    if (error) return <div className="mayo-error">{error}</div>;

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
                                    {event.meta.event_pdf_url && (
                                        <div className="mayo-archive-event-attachments">
                                            <h3>Event Flyer</h3>
                                            <div className="mayo-event-pdf">
                                                <div className="mayo-pdf-actions">
                                                    <button 
                                                        className="mayo-pdf-toggle"
                                                        onClick={() => togglePdfEmbed(event.id)}
                                                    >
                                                        {pdfEmbedStates[event.id] ? 'Hide Flyer' : 'View Flyer'}
                                                    </button>
                                                    <a 
                                                        href={event.meta.event_pdf_url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="mayo-pdf-link"
                                                    >
                                                        Download Flyer
                                                    </a>
                                                </div>
                                                
                                                {pdfEmbedStates[event.id] && (
                                                    <div className="mayo-pdf-embed">
                                                        <object
                                                            data={`${event.meta.event_pdf_url}#view=FitH&toolbar=0&navpanes=0&scrollbar=0`}
                                                            type="application/pdf"
                                                            width="100%"
                                                            height="400px"
                                                        >
                                                            <p>
                                                                Your browser doesn't support PDF embedding. You can{' '}
                                                                <a 
                                                                    href={event.meta.event_pdf_url}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                >
                                                                    download the flyer here
                                                                </a>.
                                                            </p>
                                                        </object>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    )}

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