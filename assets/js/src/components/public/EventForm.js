import { useState, useEffect, useMemo } from '@wordpress/element';
import { useEventProvider } from '../providers/EventProvider';
import { apiFetch } from '../../util';
import { getTimezonesByRegion, getUserTimezone } from '../../timezones';

const EventForm = () => {
    // Get the settings from the data attribute
    const formElement = document.getElementById('mayo-event-form');
    const settingsKey = formElement?.dataset?.settings;
    const settings = window[settingsKey] || {};
    
    // Get categories from shortcode parameter
    const categoriesParam = formElement?.dataset?.categories || '';
    const categoriesFilter = useMemo(() => (
        categoriesParam
            ? categoriesParam.split(',').map(slug => slug.trim().toLowerCase())
            : []
    ), [categoriesParam]);
    // Split categories into included and excluded
    const includedCategories = useMemo(() => categoriesFilter.filter(slug => !slug.startsWith('-')), [categoriesFilter]);
    const excludedCategories = useMemo(() => categoriesFilter.filter(slug => slug.startsWith('-')).map(slug => slug.substring(1)), [categoriesFilter]);
    
    // Get tags from shortcode parameter
    const tagsParam = formElement?.dataset?.tags || '';
    const tagsFilter = useMemo(() => (
        tagsParam
            ? tagsParam.split(',').map(slug => slug.trim().toLowerCase())
            : []
    ), [tagsParam]);
    // Split tags into included and excluded
    const includedTags = useMemo(() => tagsFilter.filter(slug => !slug.startsWith('-')), [tagsFilter]);
    const excludedTags = useMemo(() => tagsFilter.filter(slug => slug.startsWith('-')).map(slug => slug.substring(1)), [tagsFilter]);
    
    // Helper function to decode HTML entities
    const decodeHtmlEntities = (text) => {
        const textarea = document.createElement('textarea');
        textarea.innerHTML = text;
        return textarea.value;
    };
    
    // Define default required fields that cannot be overridden
    const defaultRequiredFields = [
        'event_name',
        'event_type',
        'service_body',
        'email',
        'event_start_date',
        'event_start_time',
        'event_end_time',
        'event_end_date',
        'timezone'
    ];

    // Get additional required fields from settings
    const additionalRequiredFields = settings.additionalRequiredFields ? 
        settings.additionalRequiredFields.split(',').map(field => field.trim()) : 
        [];

    // Combine both arrays for all required fields
    const allRequiredFields = [...defaultRequiredFields, ...additionalRequiredFields];

    // Filter service bodies based on configuration
    const getFilteredServiceBodies = () => {
        if (!serviceBodySettings.default_service_bodies) {
            return serviceBodies;
        }
        
        const allowedIds = serviceBodySettings.default_service_bodies
            .split(',')
            .map(id => id.trim())
            .filter(id => id);
        
        return serviceBodies.filter(body => allowedIds.includes(body.id.toString()));
    };

    // Check if we should show the service body field at all
    const shouldShowServiceBodyField = () => {
        // If no service body restriction, always show
        if (!serviceBodySettings.default_service_bodies) return true;
        
        // If only one service body is configured, hide the field (it will be auto-selected)
        const allowedIds = serviceBodySettings.default_service_bodies?.split(',').map(id => id.trim()).filter(id => id);
        return allowedIds.length > 1;
    };

    // Check if unaffiliated option should be shown
    const shouldShowUnaffiliated = () => {
        if (!serviceBodySettings.default_service_bodies) return true;
        return serviceBodySettings.default_service_bodies.includes('0');
    };

    const [formData, setFormData] = useState({
        event_name: '',
        event_type: '',
        event_start_date: '',
        event_end_date: '',
        event_start_time: '',
        event_end_time: '',
        timezone: getUserTimezone(),
        description: '',
        flyer: null,
        location_name: '',
        location_address: '',
        location_details: '',
        categories: [],
        tags: [],
        service_body: '',
        email: '',
        contact_name: '',
        // Add recurring pattern fields
        recurring_pattern: {
            type: 'none',
            interval: 1,
            weekdays: [],
            endDate: '',
            monthlyType: 'date',
            monthlyDate: '',
            monthlyWeekday: ''
        }
    });
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [message, setMessage] = useState(null);
    const [categories, setCategories] = useState([]);
    const [tags, setTags] = useState([]);
    const [error, setError] = useState(null);
    const { serviceBodies } = useEventProvider();
    const [uploadType, setUploadType] = useState(null);
    // Add state for recurring pattern UI
    const [showRecurringOptions, setShowRecurringOptions] = useState(false);
    // Add state for service body settings
    const [serviceBodySettings, setServiceBodySettings] = useState({
        default_service_bodies: ''
    });

    // Load service body settings
    useEffect(() => {
        const fetchSettings = async () => {
            try {
                const response = await apiFetch('/settings');
                
                // Start with global settings
                let finalSettings = {
                    default_service_bodies: response.default_service_bodies || ''
                };
                
                // Override with shortcode parameters if provided
                if (settings.defaultServiceBodies !== undefined && settings.defaultServiceBodies !== '') {
                    finalSettings.default_service_bodies = settings.defaultServiceBodies;
                }
                
                setServiceBodySettings(finalSettings);

                // If there are default service bodies and only one, pre-select it
                const defaultIds = finalSettings.default_service_bodies?.split(',').map(id => id.trim()).filter(id => id);
                if (defaultIds && defaultIds.length === 1) {
                    setFormData(prev => ({
                        ...prev,
                        service_body: defaultIds[0]
                    }));
                }
            } catch (error) {
                console.error('Error fetching service body settings:', error);
            }
        };
        
        fetchSettings();
    }, [settings]);

    useEffect(() => {
        const fetchTaxonomies = async () => {
            try {
                const [categoriesRes, tagsRes] = await Promise.all([
                    fetch('/wp-json/wp/v2/categories?hide_empty=false&per_page=100'),
                    fetch('/wp-json/wp/v2/tags?hide_empty=false&per_page=100')
                ]);

                if (!categoriesRes.ok || !tagsRes.ok) {
                    throw new Error('Failed to fetch taxonomies');
                }

                const categoriesData = await categoriesRes.json();
                const tagsData = await tagsRes.json();
                
                // Filter categories based on included and excluded categories
                const filteredCategories = categoriesData.filter(cat => {
                    const catSlug = (cat.slug || '').toLowerCase();
                    if (includedCategories.length > 0) {
                        // If there are included categories, only show those
                        return includedCategories.includes(catSlug);
                    } else if (excludedCategories.length > 0) {
                        // If there are excluded categories, show all except those
                        return !excludedCategories.includes(catSlug);
                    }
                    // If no restrictions, show all categories
                    return true;
                });
                
                // Filter tags based on included and excluded tags
                const filteredTags = tagsData.filter(tag => {
                    const tagSlug = (tag.slug || '').toLowerCase();
                    if (includedTags.length > 0) {
                        // If there are included tags, only show those
                        return includedTags.includes(tagSlug);
                    } else if (excludedTags.length > 0) {
                        // If there are excluded tags, show all except those
                        return !excludedTags.includes(tagSlug);
                    }
                    // If no restrictions, show all tags
                    return true;
                });
                
                setCategories(Array.isArray(filteredCategories) ? filteredCategories : []);
                setTags(Array.isArray(filteredTags) ? filteredTags : []);
            } catch (error) {
                console.error('Error fetching taxonomies:', error);
                // Set empty arrays as fallback
                setCategories([]);
                setTags([]);
            }
        };
        
        fetchTaxonomies();
    }, [includedCategories, excludedCategories, includedTags, excludedTags]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setIsSubmitting(true);
        setMessage(null);

        try {
            // Validate file type before submission
            if (formData.flyer) {
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                const fileExtension = formData.flyer.name.split('.').pop().toLowerCase();
                const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (!allowedTypes.includes(formData.flyer.type) || !allowedExtensions.includes(fileExtension)) {
                    throw new Error('You did not attach a valid image file, so one will not be submitted.  Please choose a valid image file (JPG, PNG, or GIF)');
                }
            }

            // Check all required fields
            const missingFields = allRequiredFields.filter(field => {
                if (field === 'flyer') {
                    return !formData.flyer;
                }
                return !formData[field];
            });

            if (missingFields.length > 0) {
                throw new Error(`Please fill in all required fields: ${missingFields.join(', ')}`);
            }

            const data = new FormData();
            
            // Add all form fields to FormData
            Object.keys(formData).forEach(key => {
                if (key === 'flyer' && formData[key] instanceof File) {
                    data.append('flyer', formData[key]);
                }
                // Convert arrays to comma-separated strings for categories and tags
                else if (key === 'categories' || key === 'tags') {
                    data.append(key, formData[key].join(','));
                }
                // Handle recurring pattern as JSON
                else if (key === 'recurring_pattern') {
                    data.append(key, JSON.stringify(formData[key]));
                }
                // Handle other fields
                else if (formData[key] != null && formData[key] !== '') {
                    data.append(key, formData[key]);
                }
            });

            const nonce = window.mayoApiSettings?.nonce || 
                         document.querySelector('#_wpnonce')?.value || 
                         window.wpApiSettings?.nonce;

            const result = await apiFetch('/submit-event', {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': nonce
                }
            });

            if (result.id || result.success) {
                setMessage({ 
                    type: 'success', 
                    text: 'Event submitted successfully!' 
                });

                // Reset form - preserve service_body if only one is configured
                const defaultIds = serviceBodySettings.default_service_bodies?.split(',').map(id => id.trim()).filter(id => id);
                const preservedServiceBody = (defaultIds && defaultIds.length === 1) ? defaultIds[0] : '';

                console.log('Form reset - serviceBodySettings:', serviceBodySettings);
                console.log('Form reset - defaultIds:', defaultIds);
                console.log('Form reset - preservedServiceBody:', preservedServiceBody);

                setFormData({
                    event_name: '',
                    event_type: '',
                    event_start_date: '',
                    event_end_date: '',
                    event_start_time: '',
                    event_end_time: '',
                    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                    description: '',
                    flyer: null,
                    location_name: '',
                    location_address: '',
                    location_details: '',
                    categories: [],
                    tags: [],
                    service_body: preservedServiceBody,
                    email: '',
                    contact_name: '',
                    recurring_pattern: {
                        type: 'none',
                        interval: 1,
                        weekdays: [],
                        endDate: '',
                        monthlyType: 'date',
                        monthlyDate: '',
                        monthlyWeekday: ''
                    }
                });
                setUploadType(null);
            } else {
                throw new Error(result.message || 'Failed to submit event');
            }
        } catch (error) {
            // Remove console.error
            setMessage({ 
                type: 'error', 
                text: error.message || 'Error submitting form' 
            });
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleChange = (e) => {
        const { name, value, files } = e.target;
        
        if (files && files[0]) {
            const file = files[0];
            // Immediate validation of file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            const fileExtension = file.name.split('.').pop().toLowerCase();
            const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            // Immediately reject if not an allowed type
            if (!allowedTypes.includes(file.type) || !allowedExtensions.includes(fileExtension)) {
                setMessage({ 
                    type: 'error', 
                    text: 'The selected file is not a valid image, so one will not be submitted.  Please use a valid image file (JPG, PNG, or GIF)' 
                });
                e.target.value = ''; // Clear the file input
                setFormData(prev => ({
                    ...prev,
                    flyer: null
                }));
                setUploadType(null);
                return;
            }

            // If it passes initial validation, verify it's actually an image
            const reader = new FileReader();
            reader.onload = (event) => {
                const img = new Image();
                img.onload = () => {
                    // Valid image file
                    setUploadType('image');
                    setFormData(prev => ({
                        ...prev,
                        flyer: file
                    }));
                    setMessage(null); // Clear any previous error messages
                };
                img.onerror = () => {
                    // Not a valid image
                    setMessage({ 
                        type: 'error', 
                        text: 'The selected file is not a valid image, so one will not be submitted.  Please choose a valid image file (JPG, PNG, or GIF)' 
                    });
                    e.target.value = ''; // Clear the file input
                    setFormData(prev => ({
                        ...prev,
                        flyer: null
                    }));
                    setUploadType(null);
                };
                img.src = event.target.result;
            };
            reader.onerror = () => {
                setMessage({ 
                    type: 'error', 
                    text: 'Error reading the file' 
                });
                e.target.value = ''; // Clear the file input
                setFormData(prev => ({
                    ...prev,
                    flyer: null
                }));
                setUploadType(null);
            };
            reader.readAsDataURL(file);
        } else {
            setFormData(prev => ({
                ...prev,
                [name]: value
            }));
        }
    };

    const handleservice_bodyChange = (event) => {
        setFormData(prev => ({
            ...prev,
            service_body: event.target.value
        }));
    };

    const clearUploads = () => {
        setFormData(prev => ({
            ...prev,
            flyer: null
        }));
        setUploadType(null);
    };

    // Update form field rendering to use dynamic required attribute
    const isFieldRequired = (fieldName) => {
        return allRequiredFields.includes(fieldName);
    };

    // Helper function to get initial date from event start date
    const getInitialMonthlyDate = () => {
        if (formData.event_start_date) {
            const date = new Date(formData.event_start_date);
            return date.getDate().toString();
        }
        return '';
    };

    // Helper function to get initial weekday from event start date
    const getInitialWeekdayPattern = () => {
        if (formData.event_start_date) {
            const date = new Date(formData.event_start_date);
            const weekNumber = Math.ceil(date.getDate() / 7);
            return `${weekNumber},${date.getDay()}`;
        }
        return '';
    };

    // Update recurring pattern
    const updateRecurringPattern = (updates) => {
        setFormData(prev => ({
            ...prev,
            recurring_pattern: { ...prev.recurring_pattern, ...updates }
        }));
    };

    // Weekday options for recurring events
    const weekdays = [
        { value: 0, label: 'Sunday' },
        { value: 1, label: 'Monday' },
        { value: 2, label: 'Tuesday' },
        { value: 3, label: 'Wednesday' },
        { value: 4, label: 'Thursday' },
        { value: 5, label: 'Friday' },
        { value: 6, label: 'Saturday' }
    ];

    // Week number options for monthly recurring events
    const weekNumbers = [
        { value: '1', label: 'First' },
        { value: '2', label: 'Second' },
        { value: '3', label: 'Third' },
        { value: '4', label: 'Fourth' },
        { value: '5', label: 'Fifth' },
        { value: '-1', label: 'Last' }
    ];

    if (error) return <div className="mayo-error">{error}</div>;

    return (
        <div className="mayo-event-form">
            <form onSubmit={handleSubmit}>
                <div className="mayo-form-field">
                    <label htmlFor="event_name">
                        Event Name {isFieldRequired('event_name') && '*'}
                    </label>
                    <input
                        type="text"
                        id="event_name"
                        name="event_name"
                        value={formData.event_name}
                        onChange={handleChange}
                        required={isFieldRequired('event_name')}
                    />
                </div>

                <div className="mayo-form-field">
                    <label htmlFor="event_type">
                        Event Type {isFieldRequired('event_type') && '*'}
                    </label>
                    <select
                        id="event_type"
                        name="event_type"
                        value={formData.event_type}
                        onChange={handleChange}
                        required={isFieldRequired('event_type')}
                    >
                        <option value="">Select Event Type</option>
                        <option value="Service">Service</option>
                        <option value="Activity">Activity</option>
                    </select>
                </div>

                {shouldShowServiceBodyField() && (
                    <div className="mayo-form-field">
                        <label htmlFor="service_body">Service Body *</label>
                        <select
                            id="service_body"
                            name="service_body"
                            value={formData.service_body}
                            onChange={handleservice_bodyChange}
                            required
                        >
                            <option value="">Select a service body</option>
                            {shouldShowUnaffiliated() && (
                                <option value="0">Unaffiliated (0)</option>
                            )}
                            {getFilteredServiceBodies().map((body) => (
                                <option key={body.id} value={body.id}>
                                    {body.name} ({body.id})
                                </option>
                            ))}
                        </select>
                    </div>
                )}

                <div className="mayo-form-field">
                    <label htmlFor="contact_name">Point of Contact Name (Private) *</label>
                    <input
                        type="text"
                        id="contact_name"
                        name="contact_name"
                        value={formData.contact_name}
                        onChange={handleChange}
                        required
                        placeholder="Your name (will not be displayed publicly)"
                    />
                </div>

                <div className="mayo-form-field">
                    <label htmlFor="email">Point of Contact Email (Private) *</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value={formData.email}
                        onChange={handleChange}
                        required
                        placeholder="Your email address (will not be displayed publicly)"
                    />
                </div>

                <div className="mayo-datetime-group">
                    <div className="mayo-form-field">
                        <label>Start Date/Time *</label>
                        <div className="mayo-datetime-inputs">
                            <input
                                type="date"
                                id="event_start_date"
                                name="event_start_date"
                                value={formData.event_start_date}
                                onChange={handleChange}
                                required
                            />
                            <input
                                type="time"
                                id="event_start_time"
                                name="event_start_time"
                                value={formData.event_start_time}
                                onChange={handleChange}
                                required
                            />
                        </div>
                    </div>

                    <div className="mayo-form-field">
                        <label>End Date/Time *</label>
                        <div className="mayo-datetime-inputs">
                            <input
                                type="date"
                                id="event_end_date"
                                name="event_end_date"
                                value={formData.event_end_date}
                                onChange={handleChange}
                            />
                            <input
                                type="time"
                                id="event_end_time"
                                name="event_end_time"
                                value={formData.event_end_time}
                                onChange={handleChange}
                                required
                            />
                        </div>
                    </div>
                </div>

                <div className="mayo-form-field">
                    <label htmlFor="timezone">
                        Timezone {isFieldRequired('timezone') && '*'}
                    </label>
                    <select
                        id="timezone"
                        name="timezone"
                        value={formData.timezone}
                        onChange={handleChange}
                        required={isFieldRequired('timezone')}
                    >
                        {Object.entries(getTimezonesByRegion()).map(([region, timezones]) => (
                            <optgroup key={region} label={region}>
                                {timezones.map(tz => (
                                    <option key={tz.value} value={tz.value}>
                                        {tz.label}
                                    </option>
                                ))}
                            </optgroup>
                        ))}
                    </select>
                </div>

                <div className="mayo-form-field">
                    <label>Recurring Pattern</label>
                    <div className="mayo-recurring-pattern">
                        <select
                            value={formData.recurring_pattern.type}
                            onChange={(e) => {
                                const type = e.target.value;
                                updateRecurringPattern({ 
                                    type,
                                    // Reset other fields when changing type
                                    interval: 1,
                                    weekdays: [],
                                    endDate: '',
                                    monthlyType: 'date',
                                    monthlyDate: type === 'monthly' ? getInitialMonthlyDate() : '',
                                    monthlyWeekday: type === 'monthly' ? getInitialWeekdayPattern() : ''
                                });
                                setShowRecurringOptions(type !== 'none');
                            }}
                        >
                            <option value="none">No Recurrence</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>

                        {showRecurringOptions && (
                            <div className="mayo-recurring-options">
                                <div className="mayo-recurring-interval">
                                    <label>Repeat every</label>
                                    <input
                                        type="number"
                                        min="1"
                                        value={formData.recurring_pattern.interval}
                                        onChange={(e) => updateRecurringPattern({ interval: parseInt(e.target.value) })}
                                    />
                                    <span>
                                        {formData.recurring_pattern.type === 'daily' ? 'days' : 
                                         formData.recurring_pattern.type === 'weekly' ? 'weeks' : 'months'}
                                    </span>
                                </div>

                                {formData.recurring_pattern.type === 'weekly' && (
                                    <div className="mayo-weekday-controls">
                                        <label>On these days</label>
                                        {weekdays.map(day => (
                                            <label key={day.value} className="mayo-weekday-checkbox">
                                                <input
                                                    type="checkbox"
                                                    checked={formData.recurring_pattern.weekdays.includes(day.value)}
                                                    onChange={(e) => {
                                                        const newWeekdays = e.target.checked
                                                            ? [...formData.recurring_pattern.weekdays, day.value]
                                                            : formData.recurring_pattern.weekdays.filter(d => d !== day.value);
                                                        updateRecurringPattern({ weekdays: newWeekdays });
                                                    }}
                                                />
                                                {day.label}
                                            </label>
                                        ))}
                                    </div>
                                )}

                                {formData.recurring_pattern.type === 'monthly' && (
                                    <div className="mayo-monthly-pattern">
                                        <div className="mayo-monthly-type">
                                            <label>Monthly Pattern</label>
                                            <div className="mayo-radio-group">
                                                <label>
                                                    <input
                                                        type="radio"
                                                        name="monthlyType"
                                                        value="date"
                                                        checked={formData.recurring_pattern.monthlyType === 'date'}
                                                        onChange={() => updateRecurringPattern({ 
                                                            monthlyType: 'date',
                                                            monthlyDate: getInitialMonthlyDate(),
                                                            monthlyWeekday: ''
                                                        })}
                                                    />
                                                    On a specific date
                                                </label>
                                                <label>
                                                    <input
                                                        type="radio"
                                                        name="monthlyType"
                                                        value="weekday"
                                                        checked={formData.recurring_pattern.monthlyType === 'weekday'}
                                                        onChange={() => updateRecurringPattern({ 
                                                            monthlyType: 'weekday',
                                                            monthlyDate: '',
                                                            monthlyWeekday: getInitialWeekdayPattern()
                                                        })}
                                                    />
                                                    On a specific day
                                                </label>
                                            </div>
                                        </div>

                                        {formData.recurring_pattern.monthlyType === 'date' && (
                                            <div className="mayo-monthly-date">
                                                <label>Day of month</label>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    max="31"
                                                    value={formData.recurring_pattern.monthlyDate || getInitialMonthlyDate()}
                                                    onChange={(e) => updateRecurringPattern({ monthlyDate: e.target.value })}
                                                />
                                            </div>
                                        )}

                                        {formData.recurring_pattern.monthlyType === 'weekday' && (
                                            <div className="mayo-monthly-weekday">
                                                <div className="mayo-week-select">
                                                    <label>Week</label>
                                                    <select
                                                        value={formData.recurring_pattern.monthlyWeekday?.split(',')[0] || '1'}
                                                        onChange={(e) => {
                                                            const currentDay = formData.recurring_pattern.monthlyWeekday?.split(',')[1] || '0';
                                                            updateRecurringPattern({ 
                                                                monthlyWeekday: `${e.target.value},${currentDay}` 
                                                            });
                                                        }}
                                                    >
                                                        {weekNumbers.map(week => (
                                                            <option key={week.value} value={week.value}>
                                                                {week.label}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </div>
                                                <div className="mayo-day-select">
                                                    <label>Day</label>
                                                    <select
                                                        value={formData.recurring_pattern.monthlyWeekday?.split(',')[1] || '0'}
                                                        onChange={(e) => {
                                                            const currentWeek = formData.recurring_pattern.monthlyWeekday?.split(',')[0] || '1';
                                                            updateRecurringPattern({ 
                                                                monthlyWeekday: `${currentWeek},${e.target.value}` 
                                                            });
                                                        }}
                                                    >
                                                        {weekdays.map(day => (
                                                            <option key={day.value} value={day.value}>
                                                                {day.label}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )}

                                <div className="mayo-recurring-end-date">
                                    <label>End Date (optional)</label>
                                    <input
                                        type="date"
                                        value={formData.recurring_pattern.endDate}
                                        onChange={(e) => updateRecurringPattern({ endDate: e.target.value })}
                                    />
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                <div className="mayo-form-field">
                    <label htmlFor="description">
                        Description {isFieldRequired('description') && '*'}
                    </label>
                    <textarea
                        id="description"
                        name="description"
                        value={formData.description}
                        onChange={handleChange}
                        required={isFieldRequired('description')}
                    />
                </div>

                <div className="mayo-form-field">
                    <label>
                        Event Flyer {isFieldRequired('flyer') && '*'}
                    </label>
                    <div className="mayo-upload-section">
                        {!uploadType && (
                            <>
                                <input
                                    type="file"
                                    id="flyer-upload"
                                    name="flyer"
                                    accept="image/*"
                                    onChange={handleChange}
                                    required={isFieldRequired('flyer')}
                                    className="mayo-file-input"
                                />
                                <label htmlFor="flyer-upload" className="mayo-upload-button">
                                    Upload Flyer
                                </label>
                                <p className="mayo-upload-info">
                                    Supported file types: Images (.jpg, .jpeg, .png, .gif)
                                    {isFieldRequired('flyer') && ' (Required)'}
                                </p>
                                {message && message.type === 'error' && message.text.includes('image') && (
                                    <p className="mayo-upload-error">
                                        {message.text}
                                    </p>
                                )}
                            </>
                        )}

                        {uploadType && (
                            <div className="mayo-upload-preview">
                                <p>
                                    Selected {uploadType === 'Image'}: {' '}
                                    {formData.flyer?.name || 'No file selected'}
                                </p>
                                <button 
                                    type="button" 
                                    onClick={clearUploads}
                                    className="mayo-clear-upload"
                                >
                                    Clear Upload
                                </button>
                            </div>
                        )}
                    </div>
                </div>

                <div className="mayo-form-field">
                    <label htmlFor="location_name">Location Name</label>
                    <input
                        type="text"
                        id="location_name"
                        name="location_name"
                        value={formData.location_name}
                        onChange={handleChange}
                    />
                </div>

                <div className="mayo-form-field">
                    <label htmlFor="location_address">Address</label>
                    <input
                        type="text"
                        id="location_address"
                        name="location_address"
                        value={formData.location_address}
                        onChange={handleChange}
                    />
                </div>

                <div className="mayo-form-field">
                    <label htmlFor="location_details">Location Details</label>
                    <textarea
                        id="location_details"
                        name="location_details"
                        value={formData.location_details}
                        onChange={handleChange}
                        placeholder="Additional details about the location (e.g., parking, entrance info)"
                    />
                </div>

                <div className="mayo-form-field">
                    <label>Categories</label>
                    <div className="mayo-taxonomy-list">
                        {Array.isArray(categories) && categories.map(category => (
                            <label key={category?.id} className="mayo-taxonomy-item">
                                <input
                                    type="checkbox"
                                    checked={formData.categories.includes(category?.id)}
                                    onChange={(e) => {
                                        const newCategories = e.target.checked
                                            ? [...formData.categories, category?.id]
                                            : formData.categories.filter(id => id !== category?.id);
                                        setFormData({...formData, categories: newCategories});
                                    }}
                                />
                                {category?.name ? decodeHtmlEntities(category.name) : 'Unnamed Category'}
                            </label>
                        ))}
                    </div>
                </div>

                <div className="mayo-form-field">
                    <label>Tags</label>
                    <div className="mayo-taxonomy-list">
                        {Array.isArray(tags) && tags.map(tag => (
                            <label key={tag?.id || 'default'} className="mayo-taxonomy-item">
                                <input
                                    type="checkbox"
                                    checked={formData.tags.includes(tag?.name)}
                                    onChange={(e) => {
                                        const newTags = e.target.checked
                                            ? [...formData.tags, tag?.name]
                                            : formData.tags.filter(name => name !== tag?.name);
                                        setFormData({...formData, tags: newTags});
                                    }}
                                />
                                {tag?.name ? decodeHtmlEntities(tag.name) : 'Unnamed Tag'}
                            </label>
                        ))}
                    </div>
                </div>

                <button 
                    type="submit" 
                    disabled={isSubmitting}
                    className="mayo-submit-button"
                >
                    {isSubmitting ? 'Submitting...' : 'Submit Event'}
                </button>

                {message && (
                    <div className={`mayo-message mayo-message-${message.type}`}>
                        {typeof message.text === 'string' ? message.text : 'An error occurred while submitting the form. Please try again.'}
                    </div>
                )}
            </form>
        </div>
    );
};

export default EventForm; 