import { useState, useEffect } from '@wordpress/element';
import { useEventProvider } from '../../providers/EventProvider';
import { formatTime, formatTimezone, formatRecurringPattern, dayNames, monthNames } from '../../../util';

// Helper function to convert emoji and special characters to Unicode
const convertToUnicode = (str) => {
    return str.split('')
        .map(char => {
            const code = char.codePointAt(0);
            return code > 127 ? `u${code}` : char;
        })
        .join('');
};

const EventCard = ({ event, timeFormat, forceExpanded }) => {
    const [isExpanded, setIsExpanded] = useState(false);
    
    // Update expansion state when forceExpanded changes
    useEffect(() => {
        setIsExpanded(forceExpanded);
    }, [forceExpanded]);
    
    // Check for valid date
    const hasValidDate = event.meta.event_start_date && 
        event.meta.event_start_date !== '' && 
        !isNaN(new Date(event.meta.event_start_date + 'T00:00:00').getTime());
    
    // Create date object if valid
    const eventDate = hasValidDate ? new Date(event.meta.event_start_date + 'T00:00:00') : null;
    
    const { getServiceBodyName, updateExternalServiceBodies } = useEventProvider();

    // Update external service bodies if this is an external event
    useEffect(() => {
        if (event.external_source && event.external_source.service_bodies) {
            updateExternalServiceBodies(event.external_source.id, event.external_source.service_bodies);
        }
    }, [event.external_source?.id, event.external_source?.service_bodies, updateExternalServiceBodies]);

    // Generate class names with emoji handling
    const categoryClasses = event.categories
        .map(cat => `mayo-event-category-${convertToUnicode(cat.name).toLowerCase().replace(/\s+/g, '-')}`)
        .join(' ');

    const tagClasses = event.tags
        .map(tag => `mayo-event-tag-${convertToUnicode(tag.name).toLowerCase().replace(/\s+/g, '-')}`)
        .join(' ');

    // Add event type class
    const eventTypeClass = event.meta.event_type ? 
        `mayo-event-type-${convertToUnicode(event.meta.event_type).toLowerCase().replace(/\s+/g, '-')}` : 
        '';

    // Determine the source ID for service body lookup
    const sourceId = event.external_source ? event.external_source.id : 'local';
    
    const serviceBodyClass = `mayo-event-service-body-${convertToUnicode(getServiceBodyName(event.meta.service_body, sourceId)).toLowerCase().replace(/\s+/g, '-')}`;

    const cardClasses = [
        'mayo-event-card',
        categoryClasses,
        tagClasses,
        eventTypeClass,
        serviceBodyClass
    ].filter(Boolean).join(' ');

    return (
        <div className={cardClasses}>
            <div 
                className="mayo-event-header"
                onClick={() => setIsExpanded(!isExpanded)}
            >
                <div className="mayo-event-date-badge">
                    {hasValidDate ? (
                        <>
                            <span className="mayo-event-day-name">{dayNames[eventDate.getDay()]}</span>
                            <span className="mayo-event-day-number">{eventDate.getDate()}</span>
                            <span className="mayo-event-month">
                                {monthNames[eventDate.getMonth()]}
                                <span className="mayo-event-year">{eventDate.getFullYear()}</span>
                            </span>
                        </>
                    ) : (
                        <span className="mayo-event-date-error">No Date</span>
                    )}
                </div>
                <div className="mayo-event-summary">
                    <h3 dangerouslySetInnerHTML={{ __html: event.title.rendered }} />
                    {!hasValidDate && (
                        <div className="mayo-event-date-warning">
                            This event has no date set
                        </div>
                    )}
                    <div className="mayo-event-brief">
                        <span className="mayo-event-type">{event.meta.event_type}</span>
                        {event.meta.event_start_time && (
                            <span className="mayo-event-time">
                                {formatTime(event.meta.event_start_time, timeFormat)} 
                                {event.meta.event_end_time && ` - ${formatTime(event.meta.event_end_time, timeFormat)}`}
                                {event.meta.timezone && (
                                    <span className="mayo-event-timezone">
                                        {' '}({formatTimezone(event.meta.timezone)})
                                    </span>
                                )}
                            </span>
                        )}
                        {event.external_source && (
                            <span className="mayo-event-source">
                                Source: {event.external_source.url}
                            </span>
                        )}
                        {(event.categories.length > 0 || event.tags.length > 0) && (
                            <div className="mayo-event-brief-taxonomies">
                                {event.categories.map(cat => (
                                    <span key={cat.id} className="mayo-event-category mayo-event-category-small">
                                        {cat.name}
                                    </span>
                                ))}
                                {event.tags.map(tag => (
                                    <span key={tag.id} className="mayo-event-tag mayo-event-tag-small">
                                        {tag.name}
                                    </span>
                                ))}
                                <span key={event.meta.service_body} className="mayo-event-service-body mayo-event-service-body-small">
                                    {getServiceBodyName(event.meta.service_body, sourceId)}
                                </span>
                            </div>
                        )}
                    </div>
                </div>
                <span className={`mayo-caret dashicons ${isExpanded ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2'}`} />
            </div>
            {isExpanded && (
                <div className="mayo-event-details">
                    <div className="mayo-event-content">
                        <div className="mayo-event-metadata">
                            <div className="mayo-event-datetime-details">
                                <h4>Date & Time</h4>
                                <p>
                                    <strong>Start:</strong> {event.meta.event_start_date} at {formatTime(event.meta.event_start_time, timeFormat)}
                                    {event.meta.timezone && ` (${formatTimezone(event.meta.timezone)})`}
                                </p>
                                {(event.meta.event_end_date || event.meta.event_end_time) && (
                                    <p>
                                        <strong>End:</strong> {event.meta.event_end_date || event.meta.event_start_date} at {formatTime(event.meta.event_end_time, timeFormat)}
                                    </p>
                                )}
                            </div>

                            {event.meta.event_type && (
                                <div className="mayo-event-type-details">
                                    <h4>Event Type</h4>
                                    <p>{event.meta.event_type}</p>
                                </div>
                            )}
                        </div>

                        <div className="mayo-event-description">
                            <h4>Description</h4>
                            <div dangerouslySetInnerHTML={{ __html: event.content.rendered }} />
                        </div>

                        {event.featured_image && (
                            <div className="mayo-event-attachments">
                                <h4>Event Flyer</h4>
                                <div className="mayo-event-image">
                                    <div className="mayo-image-actions">
                                        <a 
                                            href={event.featured_image}
                                            download
                                            className="mayo-image-link"
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            Download Flyer
                                        </a>
                                    </div>
                                    <a href={event.featured_image} target="_blank" rel="noopener noreferrer">
                                        <img src={event.featured_image} alt={event.title.rendered} />
                                    </a>
                                </div>
                            </div>
                        )}

                        </div>

                        {(event.meta.location_name || event.meta.location_address || event.meta.location_details) && (
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

                        {event.meta.service_body && (
                            <div>
                                <h4>Service Body</h4>
                                <p>{getServiceBodyName(event.meta.service_body, sourceId)}</p>
                            </div>
                        )}

                        <div className="mayo-event-taxonomies">
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

                        {event.meta.recurring_pattern && event.meta.recurring_pattern.type !== 'none' && (
                            <div className="mayo-event-recurring">
                                {formatRecurringPattern(event.meta.recurring_pattern)}
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
            )}
        </div>
    );
};

export default EventCard;