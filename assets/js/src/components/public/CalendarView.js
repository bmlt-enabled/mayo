import { useState, useMemo } from '@wordpress/element';
import EventModal from './EventModal';
import { useEventProvider } from '../providers/EventProvider';
import { convertToUnicode } from '../../util';

const CalendarView = ({ events, timeFormat }) => {
    const [currentDate, setCurrentDate] = useState(new Date());
    const [selectedEvent, setSelectedEvent] = useState(null);
    const { getServiceBodyName } = useEventProvider();

    // Generate dynamic CSS classes for an event
    const getEventClasses = (event) => {
        const classes = ['mayo-calendar-event'];

        // Category classes
        event.categories.forEach(cat => {
            classes.push(`mayo-event-category-${convertToUnicode(cat.name).toLowerCase().replace(/\s+/g, '-')}`);
        });

        // Tag classes
        event.tags.forEach(tag => {
            classes.push(`mayo-event-tag-${convertToUnicode(tag.name).toLowerCase().replace(/\s+/g, '-')}`);
        });

        // Event type class
        if (event.meta.event_type) {
            classes.push(`mayo-event-type-${convertToUnicode(event.meta.event_type).toLowerCase().replace(/\s+/g, '-')}`);
        }

        // Service body class
        if (event.meta.service_body) {
            const sourceId = event.external_source ? event.external_source.id : 'local';
            const serviceBodyName = getServiceBodyName(event.meta.service_body, sourceId);
            classes.push(`mayo-event-service-body-${convertToUnicode(serviceBodyName).toLowerCase().replace(/\s+/g, '-')}`);
        }

        return classes.join(' ');
    };

    // Get the first and last day of the current month
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();

    const firstDayOfMonth = new Date(year, month, 1);
    const lastDayOfMonth = new Date(year, month + 1, 0);
    const daysInMonth = lastDayOfMonth.getDate();
    const startingDayOfWeek = firstDayOfMonth.getDay();

    const monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];

    const weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    // Group events by date
    const eventsByDate = useMemo(() => {
        const grouped = {};

        events.forEach(event => {
            if (!event.meta.event_start_date) return;

            const eventDate = new Date(event.meta.event_start_date + 'T00:00:00');
            const dateKey = `${eventDate.getFullYear()}-${String(eventDate.getMonth() + 1).padStart(2, '0')}-${String(eventDate.getDate()).padStart(2, '0')}`;

            if (!grouped[dateKey]) {
                grouped[dateKey] = [];
            }
            grouped[dateKey].push(event);
        });

        // Sort events within each day by start time
        Object.keys(grouped).forEach(dateKey => {
            grouped[dateKey].sort((a, b) => {
                const timeA = a.meta.event_start_time || '00:00';
                const timeB = b.meta.event_start_time || '00:00';
                return timeA.localeCompare(timeB);
            });
        });

        return grouped;
    }, [events]);

    const formatTime = (time) => {
        if (!time) return '';

        const [hours, minutes] = time.split(':');
        const hour = parseInt(hours, 10);

        if (timeFormat === '24hour') {
            return time;
        }

        const ampm = hour >= 12 ? 'pm' : 'am';
        const hour12 = hour % 12 || 12;
        return `${hour12}:${minutes}${ampm}`;
    };

    const goToPreviousMonth = () => {
        setCurrentDate(new Date(year, month - 1, 1));
    };

    const goToNextMonth = () => {
        setCurrentDate(new Date(year, month + 1, 1));
    };

    const goToToday = () => {
        setCurrentDate(new Date());
    };

    const handleEventClick = (event) => {
        setSelectedEvent(event);
    };

    const handleCloseModal = () => {
        setSelectedEvent(null);
    };

    // Generate calendar days
    const calendarDays = [];

    // Add empty cells for days before the first day of the month
    for (let i = 0; i < startingDayOfWeek; i++) {
        calendarDays.push(
            <div key={`empty-${i}`} className="mayo-calendar-day empty"></div>
        );
    }

    // Add cells for each day of the month
    for (let day = 1; day <= daysInMonth; day++) {
        const dateKey = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const dayEvents = eventsByDate[dateKey] || [];
        const isToday = new Date().toDateString() === new Date(year, month, day).toDateString();

        calendarDays.push(
            <div
                key={dateKey}
                className={`mayo-calendar-day ${isToday ? 'today' : ''} ${dayEvents.length > 0 ? 'has-events' : ''}`}
            >
                <span className="mayo-calendar-date">{day}</span>
                <div className="mayo-calendar-events">
                    {dayEvents.map((event, index) => (
                        <div
                            key={`${event.id}-${index}`}
                            className={getEventClasses(event)}
                            onClick={() => handleEventClick(event)}
                            title={event.title.rendered}
                        >
                            {event.meta.event_start_time && (
                                <span className="event-time">
                                    {formatTime(event.meta.event_start_time)}
                                </span>
                            )}
                            <span
                                className="event-title"
                                dangerouslySetInnerHTML={{ __html: event.title.rendered }}
                            />
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    return (
        <>
            <div className="mayo-calendar">
                <div className="mayo-calendar-header">
                    <button
                        onClick={goToPreviousMonth}
                        title="Previous Month"
                    >
                        <span className="dashicons dashicons-arrow-left-alt2"></span>
                    </button>
                    <h2>{monthNames[month]} {year}</h2>
                    <div className="mayo-calendar-header-right">
                        <button
                            onClick={goToToday}
                            className="mayo-calendar-today-button"
                            title="Go to Today"
                        >
                            Today
                        </button>
                        <button
                            onClick={goToNextMonth}
                            title="Next Month"
                        >
                            <span className="dashicons dashicons-arrow-right-alt2"></span>
                        </button>
                    </div>
                </div>
                <div className="mayo-calendar-grid">
                    <div className="mayo-calendar-weekdays">
                        {weekDays.map(day => (
                            <div key={day} className="mayo-calendar-weekday">{day}</div>
                        ))}
                    </div>
                    <div className="mayo-calendar-days">
                        {calendarDays}
                    </div>
                </div>
            </div>
            {selectedEvent && (
                <EventModal
                    event={selectedEvent}
                    timeFormat={timeFormat}
                    onClose={handleCloseModal}
                />
            )}
        </>
    );
};

export default CalendarView;
