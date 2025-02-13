import { useState, useEffect } from '@wordpress/element';

const EventCalendar = ({ events }) => {
    const [currentDate, setCurrentDate] = useState(new Date());
    
    // Get calendar data for current month
    const getDaysInMonth = (date) => {
        const year = date.getFullYear();
        const month = date.getMonth();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const firstDayOfMonth = new Date(year, month, 1).getDay();
        
        const days = [];
        // Add empty cells for days before the first of the month
        for (let i = 0; i < firstDayOfMonth; i++) {
            days.push(null);
        }
        
        // Add days of the month
        for (let i = 1; i <= daysInMonth; i++) {
            days.push(new Date(year, month, i));
        }
        
        return days;
    };

    const getEventsForDate = (date) => {
        if (!date) return [];
        return events.filter(event => {
            const eventDate = new Date(event.meta.event_start_date);
            return eventDate.toDateString() === date.toDateString();
        });
    };

    const days = getDaysInMonth(currentDate);
    const monthNames = ["January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];

    const changeMonth = (increment) => {
        setCurrentDate(prev => {
            const newDate = new Date(prev);
            newDate.setMonth(prev.getMonth() + increment);
            return newDate;
        });
    };

    const handleEventClick = (event, e) => {
        e.stopPropagation(); // Prevent day click when clicking event
        window.location.href = event.link;
    };

    return (
        <div className="mayo-calendar">
            <div className="mayo-calendar-header">
                <button onClick={() => changeMonth(-1)}>
                    <span className="dashicons dashicons-arrow-left-alt2"></span>
                </button>
                <h2>{monthNames[currentDate.getMonth()]} {currentDate.getFullYear()}</h2>
                <button onClick={() => changeMonth(1)}>
                    <span className="dashicons dashicons-arrow-right-alt2"></span>
                </button>
            </div>
            <div className="mayo-calendar-grid">
                <div className="mayo-calendar-weekdays">
                    {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(day => (
                        <div key={day} className="mayo-calendar-weekday">{day}</div>
                    ))}
                </div>
                <div className="mayo-calendar-days">
                    {days.map((date, index) => {
                        const dayEvents = getEventsForDate(date);
                        return (
                            <div key={index} className={`mayo-calendar-day ${!date ? 'empty' : ''}`}>
                                {date && (
                                    <>
                                        <span className="mayo-calendar-date">{date.getDate()}</span>
                                        {dayEvents.length > 0 && (
                                            <div className="mayo-calendar-events">
                                                {dayEvents.map(event => (
                                                    <div 
                                                        key={event.id} 
                                                        className="mayo-calendar-event"
                                                        onClick={(e) => handleEventClick(event, e)}
                                                        title={`View details for ${event.title.rendered}`}
                                                    >
                                                        <span className="event-time">
                                                            {event.meta.event_start_time}
                                                        </span>
                                                        <span className="event-title">
                                                            {event.title.rendered}
                                                        </span>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
};

export default EventCalendar; 