export const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
export const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

export const formatTime = (time, format) => {
    if (!time) return '';
    
    if (format === '24hour') {
        return time;
    }
    
    // Convert to 12-hour format
    const [hours, minutes] = time.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
};

export const formatTimezone = (timezone) => {
    try {
        const date = new Date();
        const timeString = date.toLocaleTimeString('en-US', { timeZone: timezone, timeZoneName: 'short' });
        return timeString.split(' ')[2]; // Extract timezone abbreviation (e.g., EST, CST)
    } catch (e) {
        return timezone.split('/').pop().replace('_', ' '); // Fallback to city name
    }
};

export const formatRecurringPattern = (pattern) => {
    if (!pattern || pattern.type === 'none') return '';
    
    const { type, interval, weekdays = [], endDate, monthlyType, monthlyWeekday, monthlyDate } = pattern;
    let text = "This event repeats ";
    
    switch (type) {
        case 'daily':
            text += interval > 1 ? `every ${interval} days` : "daily";
            break;
        case 'weekly':
            text += interval > 1 ? `every ${interval} weeks` : "weekly";
            if (weekdays && weekdays.length) {
                const days = weekdays.map(day => {
                    return dayNames[parseInt(day)];
                });
                text += ` on ${days.join(', ')}`;
            }
            break;
        case 'monthly':
            text += interval > 1 ? `every ${interval} months` : "monthly";
            if (monthlyType === 'date' && monthlyDate) {
                text += ` on day ${monthlyDate}`;
            } else if (monthlyType === 'weekday' && monthlyWeekday) {
                const [week, weekday] = monthlyWeekday.split(',').map(Number);
                const weekText = week > 0 
                    ? ['first', 'second', 'third', 'fourth', 'fifth'][week - 1] 
                    : 'last';
                text += ` on the ${weekText} ${dayNames[weekday]}`;
            }
            break;
        default:
            return '';
    }
    
    if (endDate) {
        text += ` until ${endDate}`;
    }
    
    return text;
};