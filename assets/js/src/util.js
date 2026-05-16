export const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
export const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// Helper function to convert emoji and special characters to Unicode for CSS class names
export const convertToUnicode = (str) => {
    return str.split('')
        .map(char => {
            const code = char.codePointAt(0);
            return code > 127 ? `u${code}` : char;
        })
        .join('');
};

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

export const formatDateTimeDisplay = (event, timeFormat) => {
    const { event_start_date, event_end_date, event_start_time, event_end_time, timezone } = event.meta;
    
    if (!event_start_date) return '';
    if (!event_start_time) return '';
    
    // Check if this is a multi-day event
    const isMultiDay = event_end_date && event_start_date !== event_end_date;
    
    let display = '';
    
    if (isMultiDay) {
        // Multi-day event: show full date range with times
        const startDate = new Date(event_start_date + 'T00:00:00');
        const endDate = new Date(event_end_date + 'T00:00:00');
        
        const startDateStr = `${monthNames[startDate.getMonth()]} ${startDate.getDate()}`;
        const endDateStr = `${monthNames[endDate.getMonth()]} ${endDate.getDate()}`;
        
        display = `${startDateStr}, ${formatTime(event_start_time, timeFormat)} - ${endDateStr}, ${formatTime(event_end_time || event_start_time, timeFormat)}`;
    } else {
        // Single-day event: show just the time range
        display = formatTime(event_start_time, timeFormat);
        if (event_end_time) {
            display += ` - ${formatTime(event_end_time, timeFormat)}`;
        }
    }
    
    if (timezone) {
        display += ` (${formatTimezone(timezone)})`;
    }
    
    return display;
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

/**
 * Call the WordPress REST API
 * 
 * @param {string} endpoint - API endpoint path
 * @param {Object} options - Fetch options
 * @returns {Promise} Fetch promise
 */
export const apiFetch = async (endpoint, options = {}) => {
    let baseUrl = window.mayoApiSettings?.root || window.wpApiSettings?.root || '/wp-json/';
    // Ensure baseUrl ends with a trailing slash
    if (!baseUrl.endsWith('/')) {
        baseUrl += '/';
    }
    const url = `${baseUrl}event-manager/v1${endpoint}`;

    const method = (options.method || 'GET').toUpperCase();
    const { authenticated, ...restOptions } = options;

    const headers = {
        'Content-Type': 'application/json',
    };

    // Only send nonce for write operations or when explicitly requested.
    // Public GET endpoints don't need it, and sending a stale nonce
    // (from CDN-cached pages) causes 403 errors.
    if (method !== 'GET' || authenticated) {
        const nonce = window.mayoApiSettings?.nonce || window.wpApiSettings?.nonce || '';
        if (nonce) {
            headers['X-WP-Nonce'] = nonce;
        }
    }

    const defaultOptions = {
        credentials: 'same-origin',
        headers
    };

    const fetchOptions = { ...defaultOptions, ...restOptions };

    try {
        const response = await fetch(url, fetchOptions);

        if (!response.ok) {
            throw new Error(`API error: ${response.status} ${response.statusText}`);
        }

        return await response.json();
    } catch (error) {
        console.error('API fetch error:', error);
        throw error;
    }
};