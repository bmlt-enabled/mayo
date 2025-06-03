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

/**
 * Call the WordPress REST API
 * 
 * @param {string} endpoint - API endpoint path
 * @param {Object} options - Fetch options
 * @returns {Promise} Fetch promise
 */
export const apiFetch = async (endpoint, options = {}) => {
    const baseUrl = window.mayoApiSettings?.root || window.wpApiSettings?.root || '/wp-json';
    const url = `${baseUrl}event-manager/v1${endpoint}`;
    
    // Check for nonce in various places
    let nonce = '';
    
    // First check if mayoApiSettings exists (our plugin's settings)
    if (window.mayoApiSettings && window.mayoApiSettings.nonce) {
        nonce = window.mayoApiSettings.nonce;
    } 
    // Fallback to WordPress core's wpApiSettings
    else if (window.wpApiSettings && window.wpApiSettings.nonce) {
        nonce = window.wpApiSettings.nonce;
    }
    
    const defaultOptions = {
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce
        }
    };
    
    const fetchOptions = { ...defaultOptions, ...options };
    
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