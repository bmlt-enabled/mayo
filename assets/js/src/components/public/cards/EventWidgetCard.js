import { formatTime, formatDateTimeDisplay, dayNames, monthNames } from '../../../util';

const EventWidgetCard = ({ event, timeFormat }) => {
    // Check for valid date
    const hasValidDate = event.meta.event_start_date && 
        event.meta.event_start_date !== '' && 
        !isNaN(new Date(event.meta.event_start_date + 'T00:00:00').getTime());
    
    // Create date object if valid
    const eventDate = hasValidDate ? new Date(event.meta.event_start_date + 'T00:00:00') : null;

    return (
        <div key={`${event.id}-${event.meta.event_start_date}`} className="mayo-widget-event">
            <div className="mayo-widget-event-date">
                {hasValidDate ? (
                    <>
                        <span className="mayo-event-day-name">{dayNames[eventDate.getDay()]}</span>
                        <span className="mayo-event-day-number">{eventDate.getDate()}</span>
                        <span className="mayo-event-month">{monthNames[eventDate.getMonth()]}</span>
                    </>
                ) : (
                    <span className="mayo-event-date-error">No Date</span>
                )}
            </div>
            <h4 className="mayo-widget-event-title">{event.title.rendered}</h4>
            {!hasValidDate && (
                <div className="mayo-event-date-warning">
                    Event date not set
                </div>
            )}
            {formatDateTimeDisplay(event, timeFormat) && (
                <div className="mayo-widget-event-time">
                    {formatDateTimeDisplay(event, timeFormat)}
                </div>
            )}
            <a href={event.link} className="mayo-widget-event-link">
                {event.featured_image && (
                    <img 
                        src={event.featured_image} 
                        alt={event.title.rendered}
                        className="mayo-widget-event-image"
                    />
                )}
            </a>
            <div className="mayo-widget-event-actions">
                <a href={event.link} className="mayo-widget-event-link">Read More...</a>
                
            </div>
        </div>
    )
}

export default EventWidgetCard;