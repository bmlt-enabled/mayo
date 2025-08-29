/**
 * Comprehensive timezone list including international zones
 * Organized by region for better user experience
 */

export const timezones = [
    // North America
    { label: 'Eastern Time (US/Canada)', value: 'America/New_York', region: 'North America' },
    { label: 'Central Time (US/Canada)', value: 'America/Chicago', region: 'North America' },
    { label: 'Mountain Time (US/Canada)', value: 'America/Denver', region: 'North America' },
    { label: 'Pacific Time (US/Canada)', value: 'America/Los_Angeles', region: 'North America' },
    { label: 'Alaska Time (US)', value: 'America/Anchorage', region: 'North America' },
    { label: 'Hawaii Time (US)', value: 'Pacific/Honolulu', region: 'North America' },
    { label: 'Atlantic Time (Canada)', value: 'America/Halifax', region: 'North America' },
    { label: 'Newfoundland Time (Canada)', value: 'America/St_Johns', region: 'North America' },
    { label: 'Mexico City Time', value: 'America/Mexico_City', region: 'North America' },

    // Europe
    { label: 'London (GMT/BST)', value: 'Europe/London', region: 'Europe' },
    { label: 'Paris (CET/CEST)', value: 'Europe/Paris', region: 'Europe' },
    { label: 'Berlin (CET/CEST)', value: 'Europe/Berlin', region: 'Europe' },
    { label: 'Rome (CET/CEST)', value: 'Europe/Rome', region: 'Europe' },
    { label: 'Madrid (CET/CEST)', value: 'Europe/Madrid', region: 'Europe' },
    { label: 'Amsterdam (CET/CEST)', value: 'Europe/Amsterdam', region: 'Europe' },
    { label: 'Brussels (CET/CEST)', value: 'Europe/Brussels', region: 'Europe' },
    { label: 'Zurich (CET/CEST)', value: 'Europe/Zurich', region: 'Europe' },
    { label: 'Vienna (CET/CEST)', value: 'Europe/Vienna', region: 'Europe' },
    { label: 'Stockholm (CET/CEST)', value: 'Europe/Stockholm', region: 'Europe' },
    { label: 'Oslo (CET/CEST)', value: 'Europe/Oslo', region: 'Europe' },
    { label: 'Copenhagen (CET/CEST)', value: 'Europe/Copenhagen', region: 'Europe' },
    { label: 'Helsinki (EET/EEST)', value: 'Europe/Helsinki', region: 'Europe' },
    { label: 'Athens (EET/EEST)', value: 'Europe/Athens', region: 'Europe' },
    { label: 'Istanbul (TRT)', value: 'Europe/Istanbul', region: 'Europe' },
    { label: 'Moscow (MSK)', value: 'Europe/Moscow', region: 'Europe' },
    { label: 'Dublin (GMT/IST)', value: 'Europe/Dublin', region: 'Europe' },
    { label: 'Lisbon (WET/WEST)', value: 'Europe/Lisbon', region: 'Europe' },

    // Asia
    { label: 'Tokyo (JST)', value: 'Asia/Tokyo', region: 'Asia' },
    { label: 'Shanghai (CST)', value: 'Asia/Shanghai', region: 'Asia' },
    { label: 'Hong Kong (HKT)', value: 'Asia/Hong_Kong', region: 'Asia' },
    { label: 'Singapore (SGT)', value: 'Asia/Singapore', region: 'Asia' },
    { label: 'Seoul (KST)', value: 'Asia/Seoul', region: 'Asia' },
    { label: 'Bangkok (ICT)', value: 'Asia/Bangkok', region: 'Asia' },
    { label: 'Manila (PHT)', value: 'Asia/Manila', region: 'Asia' },
    { label: 'Jakarta (WIB)', value: 'Asia/Jakarta', region: 'Asia' },
    { label: 'Mumbai (IST)', value: 'Asia/Kolkata', region: 'Asia' },
    { label: 'Dubai (GST)', value: 'Asia/Dubai', region: 'Asia' },
    { label: 'Riyadh (AST)', value: 'Asia/Riyadh', region: 'Asia' },
    { label: 'Tel Aviv (IST)', value: 'Asia/Jerusalem', region: 'Asia' },
    { label: 'Dhaka (BST)', value: 'Asia/Dhaka', region: 'Asia' },
    { label: 'Karachi (PKT)', value: 'Asia/Karachi', region: 'Asia' },
    { label: 'Tashkent (UZT)', value: 'Asia/Tashkent', region: 'Asia' },

    // Australia & Oceania
    { label: 'Sydney (AEST/AEDT)', value: 'Australia/Sydney', region: 'Australia & Oceania' },
    { label: 'Melbourne (AEST/AEDT)', value: 'Australia/Melbourne', region: 'Australia & Oceania' },
    { label: 'Brisbane (AEST)', value: 'Australia/Brisbane', region: 'Australia & Oceania' },
    { label: 'Perth (AWST)', value: 'Australia/Perth', region: 'Australia & Oceania' },
    { label: 'Adelaide (ACST/ACDT)', value: 'Australia/Adelaide', region: 'Australia & Oceania' },
    { label: 'Darwin (ACST)', value: 'Australia/Darwin', region: 'Australia & Oceania' },
    { label: 'Hobart (AEST/AEDT)', value: 'Australia/Hobart', region: 'Australia & Oceania' },
    { label: 'Auckland (NZST/NZDT)', value: 'Pacific/Auckland', region: 'Australia & Oceania' },
    { label: 'Wellington (NZST/NZDT)', value: 'Pacific/Auckland', region: 'Australia & Oceania' },
    { label: 'Fiji (FJT)', value: 'Pacific/Fiji', region: 'Australia & Oceania' },

    // Africa
    { label: 'Cairo (EET)', value: 'Africa/Cairo', region: 'Africa' },
    { label: 'Cape Town (SAST)', value: 'Africa/Johannesburg', region: 'Africa' },
    { label: 'Lagos (WAT)', value: 'Africa/Lagos', region: 'Africa' },
    { label: 'Nairobi (EAT)', value: 'Africa/Nairobi', region: 'Africa' },
    { label: 'Casablanca (WET)', value: 'Africa/Casablanca', region: 'Africa' },
    { label: 'Tunis (CET)', value: 'Africa/Tunis', region: 'Africa' },

    // South America
    { label: 'São Paulo (BRT)', value: 'America/Sao_Paulo', region: 'South America' },
    { label: 'Buenos Aires (ART)', value: 'America/Argentina/Buenos_Aires', region: 'South America' },
    { label: 'Santiago (CLT)', value: 'America/Santiago', region: 'South America' },
    { label: 'Lima (PET)', value: 'America/Lima', region: 'South America' },
    { label: 'Bogotá (COT)', value: 'America/Bogota', region: 'South America' },
    { label: 'Caracas (VET)', value: 'America/Caracas', region: 'South America' }
];

/**
 * Get timezone options grouped by region for select elements
 */
export const getTimezonesByRegion = () => {
    const grouped = {};
    timezones.forEach(tz => {
        if (!grouped[tz.region]) {
            grouped[tz.region] = [];
        }
        grouped[tz.region].push(tz);
    });
    return grouped;
};

/**
 * Get flat list of timezone options for simple selects
 */
export const getTimezoneOptions = () => {
    return timezones.map(tz => ({
        label: tz.label,
        value: tz.value
    }));
};

/**
 * Find timezone by value
 */
export const getTimezoneByValue = (value) => {
    return timezones.find(tz => tz.value === value);
};

/**
 * Get user's detected timezone from browser
 */
export const getUserTimezone = () => {
    try {
        const userTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        // Check if the detected timezone is in our list
        const found = timezones.find(tz => tz.value === userTz);
        return found ? userTz : 'America/New_York'; // Fallback to Eastern Time
    } catch (e) {
        return 'America/New_York'; // Fallback
    }
};