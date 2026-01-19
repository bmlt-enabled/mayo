import { useEffect } from '@wordpress/element';
import { useEventProvider } from '../providers/EventProvider';
import { formatTime, formatTimezone, formatRecurringPattern, formatDateTimeDisplay, dayNames, monthNames } from '../../util';
import LocationAddress from './LocationAddress';

const EventModal = ({ event, timeFormat, onClose }) => {
    const { getServiceBodyName } = useEventProvider();

    // Close on escape key
    useEffect(() => {
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                onClose();
            }
        };
        document.addEventListener('keydown', handleEscape);
        // Prevent body scroll when modal is open
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', handleEscape);
            document.body.style.overflow = '';
        };
    }, [onClose]);

    // Close when clicking backdrop
    const handleBackdropClick = (e) => {
        if (e.target === e.currentTarget) {
            onClose();
        }
    };

    // Check for valid date
    const hasValidDate = event.meta.event_start_date &&
        event.meta.event_start_date !== '' &&
        !isNaN(new Date(event.meta.event_start_date + 'T00:00:00').getTime());

    const eventDate = hasValidDate ? new Date(event.meta.event_start_date + 'T00:00:00') : null;

    // Check if this is a multi-day event
    const isMultiDay = event.meta.event_end_date && event.meta.event_start_date !== event.meta.event_end_date;
    const endDate = isMultiDay ? new Date(event.meta.event_end_date + 'T00:00:00') : null;

    // Determine the source ID for service body lookup
    const sourceId = event.source_id || 'local';

    return (
        <div className="mayo-event-modal-backdrop" onClick={handleBackdropClick}>
            <div className="mayo-event-modal">
                <button className="mayo-event-modal-close" onClick={onClose} title="Close">
                    <span className="dashicons dashicons-no-alt"></span>
                </button>

                <div className="mayo-event-modal-header">
                    <div className="mayo-event-modal-date">
                        {hasValidDate ? (
                            isMultiDay ? (
                                <span>
                                    {dayNames[eventDate.getDay()]}, {monthNames[eventDate.getMonth()]} {eventDate.getDate()}, {eventDate.getFullYear()}
                                    {' – '}
                                    {dayNames[endDate.getDay()]}, {monthNames[endDate.getMonth()]} {endDate.getDate()}, {endDate.getFullYear()}
                                </span>
                            ) : (
                                <span>
                                    {dayNames[eventDate.getDay()]}, {monthNames[eventDate.getMonth()]} {eventDate.getDate()}, {eventDate.getFullYear()}
                                </span>
                            )
                        ) : (
                            <span className="mayo-event-date-error">No Date Set</span>
                        )}
                    </div>
                    <h2 dangerouslySetInnerHTML={{ __html: event.title.rendered }} />
                    {event.meta.event_type && (
                        <span className="mayo-event-modal-type">{event.meta.event_type}</span>
                    )}
                </div>

                <div className="mayo-event-modal-body">
                    <div className="mayo-event-modal-meta">
                        <div className="mayo-event-modal-time">
                            <span className="dashicons dashicons-clock"></span>
                            <span>
                                {formatDateTimeDisplay(event, timeFormat)}
                                {event.meta.timezone && ` (${formatTimezone(event.meta.timezone)})`}
                            </span>
                        </div>

                        {(event.meta.location_name || event.meta.location_address) && (
                            <div className="mayo-event-modal-location">
                                <span className="dashicons dashicons-location"></span>
                                <span>
                                    {event.meta.location_name && <strong>{event.meta.location_name}</strong>}
                                    {event.meta.location_name && event.meta.location_address && <br />}
                                    {event.meta.location_address && (
                                        <LocationAddress address={event.meta.location_address} />
                                    )}
                                    {event.meta.location_details && (
                                        <>
                                            <br />
                                            <em>{event.meta.location_details}</em>
                                        </>
                                    )}
                                </span>
                            </div>
                        )}

                        {event.meta.service_body && (
                            <div className="mayo-event-modal-service-body">
                                <span className="dashicons dashicons-groups"></span>
                                <span>{getServiceBodyName(event.meta.service_body, sourceId)}</span>
                            </div>
                        )}

                        {event.source && event.source.type === 'external' && (
                            <div className="mayo-event-modal-source">
                                <span className="dashicons dashicons-admin-site"></span>
                                <span>{event.source.name}</span>
                            </div>
                        )}
                    </div>

                    {event.content.rendered && (
                        <div className="mayo-event-modal-description">
                            <div dangerouslySetInnerHTML={{ __html: event.content.rendered }} />
                        </div>
                    )}

                    {event.featured_image && (
                        <div className="mayo-event-modal-image">
                            <a href={event.featured_image} target="_blank" rel="noopener noreferrer">
                                <img src={event.featured_image} alt={event.title.rendered} />
                            </a>
                            <a
                                href={event.featured_image}
                                download
                                className="mayo-image-download"
                            >
                                <span className="dashicons dashicons-download"></span>
                                Download Flyer
                            </a>
                        </div>
                    )}

                    {(event.categories.length > 0 || event.tags.length > 0) && (
                        <div className="mayo-event-modal-taxonomies">
                            {event.categories.map(cat => (
                                <span key={cat.id} className="mayo-event-category">
                                    {cat.name}
                                </span>
                            ))}
                            {event.tags.map(tag => (
                                <span key={tag.id} className="mayo-event-tag">
                                    {tag.name}
                                </span>
                            ))}
                        </div>
                    )}

                    {event.meta.recurring_pattern && event.meta.recurring_pattern.type !== 'none' && (
                        <div className="mayo-event-modal-recurring">
                            <span className="dashicons dashicons-update"></span>
                            <span>{formatRecurringPattern(event.meta.recurring_pattern)}</span>
                        </div>
                    )}
                </div>

                <div className="mayo-event-modal-footer">
                    <a href={event.link} className="mayo-event-modal-link">
                        View Full Details
                        <span className="dashicons dashicons-arrow-right-alt2"></span>
                    </a>
                </div>
            </div>
        </div>
    );
};

export default EventModal;
