import { __, sprintf, _n } from '@wordpress/i18n';

export const dayNames = [
    __('Sunday', 'mayo-events-manager'),
    __('Monday', 'mayo-events-manager'),
    __('Tuesday', 'mayo-events-manager'),
    __('Wednesday', 'mayo-events-manager'),
    __('Thursday', 'mayo-events-manager'),
    __('Friday', 'mayo-events-manager'),
    __('Saturday', 'mayo-events-manager')
];
export const monthNames = [
    __('Jan', 'mayo-events-manager'),
    __('Feb', 'mayo-events-manager'),
    __('Mar', 'mayo-events-manager'),
    __('Apr', 'mayo-events-manager'),
    __('May', 'mayo-events-manager'),
    __('Jun', 'mayo-events-manager'),
    __('Jul', 'mayo-events-manager'),
    __('Aug', 'mayo-events-manager'),
    __('Sep', 'mayo-events-manager'),
    __('Oct', 'mayo-events-manager'),
    __('Nov', 'mayo-events-manager'),
    __('Dec', 'mayo-events-manager')
];

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

    const { type, interval, weekdays = [], endDate, monthlyType, monthlyWeekday, monthlyDate, relativeOffsetWeekday, relativeOffsetDirection } = pattern;
    let text = '';

    switch (type) {
        case 'daily':
            text = interval > 1
                ? sprintf(
                    /* translators: %d: number of days */
                    _n('This event repeats every %d day', 'This event repeats every %d days', interval, 'mayo-events-manager'),
                    interval
                )
                : __('This event repeats daily', 'mayo-events-manager');
            break;
        case 'weekly':
            text = interval > 1
                ? sprintf(
                    /* translators: %d: number of weeks */
                    _n('This event repeats every %d week', 'This event repeats every %d weeks', interval, 'mayo-events-manager'),
                    interval
                )
                : __('This event repeats weekly', 'mayo-events-manager');
            if (weekdays && weekdays.length) {
                const days = weekdays.map(day => dayNames[parseInt(day)]);
                text += ' ' + sprintf(
                    /* translators: %s: comma-separated weekday names */
                    __('on %s', 'mayo-events-manager'),
                    days.join(', ')
                );
            }
            break;
        case 'monthly':
            text = interval > 1
                ? sprintf(
                    /* translators: %d: number of months */
                    _n('This event repeats every %d month', 'This event repeats every %d months', interval, 'mayo-events-manager'),
                    interval
                )
                : __('This event repeats monthly', 'mayo-events-manager');
            if (monthlyType === 'date' && monthlyDate) {
                text += ' ' + sprintf(
                    /* translators: %s: day-of-month number */
                    __('on day %s', 'mayo-events-manager'),
                    monthlyDate
                );
            } else if (monthlyType === 'weekday' && monthlyWeekday) {
                const [week, weekday] = monthlyWeekday.split(',').map(Number);
                const weekTexts = [
                    __('first', 'mayo-events-manager'),
                    __('second', 'mayo-events-manager'),
                    __('third', 'mayo-events-manager'),
                    __('fourth', 'mayo-events-manager'),
                    __('fifth', 'mayo-events-manager')
                ];
                const weekText = week > 0 ? weekTexts[week - 1] : __('last', 'mayo-events-manager');
                text += ' ' + sprintf(
                    /* translators: 1: ordinal week (e.g. "first"), 2: weekday name */
                    __('on the %1$s %2$s', 'mayo-events-manager'),
                    weekText,
                    dayNames[weekday]
                );
            } else if (monthlyType === 'relative' && monthlyWeekday) {
                const [week, weekday] = monthlyWeekday.split(',').map(Number);
                const weekTexts = [
                    __('first', 'mayo-events-manager'),
                    __('second', 'mayo-events-manager'),
                    __('third', 'mayo-events-manager'),
                    __('fourth', 'mayo-events-manager'),
                    __('fifth', 'mayo-events-manager')
                ];
                const weekText = week > 0 ? weekTexts[week - 1] : __('last', 'mayo-events-manager');
                const targetDay = dayNames[parseInt(relativeOffsetWeekday ?? 1)];
                const direction = relativeOffsetDirection === 'after'
                    ? __('after', 'mayo-events-manager')
                    : __('before', 'mayo-events-manager');
                text += ' ' + sprintf(
                    /* translators: 1: target weekday (e.g. "Monday"), 2: "before"/"after", 3: ordinal week (e.g. "third"), 4: anchor weekday (e.g. "Tuesday") */
                    __('on the %1$s %2$s the %3$s %4$s', 'mayo-events-manager'),
                    targetDay,
                    direction,
                    weekText,
                    dayNames[weekday]
                );
            }
            break;
        default:
            return '';
    }

    if (endDate) {
        text += ' ' + sprintf(
            /* translators: %s: end date */
            __('until %s', 'mayo-events-manager'),
            endDate
        );
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
            // Prefer the REST API error message (e.g. WP_Error) when present
            // so callers can show a meaningful reason instead of a bare status.
            let message = `API error: ${response.status} ${response.statusText}`;
            try {
                const errorBody = await response.json();
                if (errorBody && errorBody.message) {
                    message = errorBody.message;
                }
            } catch (e) {
                // Response body wasn't JSON; keep the status-based message.
            }
            throw new Error(message);
        }

        return await response.json();
    } catch (error) {
        console.error('API fetch error:', error);
        throw error;
    }
};