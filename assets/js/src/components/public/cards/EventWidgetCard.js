import { formatTime, dayNames, monthNames } from '../../../util';

const EventWidgetCard = ({ event, timeFormat }) => {
    const eventDate = new Date(event.meta.event_start_date + 'T00:00:00');

    return (
        <div key={`${event.id}-${event.meta.event_start_date}`} className="mayo-widget-event">
            <div className="mayo-widget-event-date">
                <span className="mayo-event-day-name">{dayNames[eventDate.getDay()]}</span>
                <span className="mayo-event-day-number">{eventDate.getDate()}</span>
                <span className="mayo-event-month">{monthNames[eventDate.getMonth()]}</span>
            </div>
            <h4 className="mayo-widget-event-title">{event.title.rendered}</h4>
            <div className="mayo-widget-event-time">
                {formatTime(event.meta.event_start_time, timeFormat)} - 
                {formatTime(event.meta.event_end_time, timeFormat)}
            </div>
            <a href={event.link} className="mayo-widget-event-link">
                {event.featured_image && (
                    <img 
                        src={event.featured_image} 
                        alt={event.title.rendered}
                        className="mayo-widget-event-image"
                    />
                )}
            </a>
            <a href={event.link} className="mayo-widget-event-link">Read More...</a>
        </div>
    )
}

export default EventWidgetCard;