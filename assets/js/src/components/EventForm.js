import { useState, useEffect } from '@wordpress/element';

const EventForm = () => {
    const [formData, setFormData] = useState({
        event_name: '',
        event_type: '',
        event_start_date: '',
        event_end_date: '',
        event_start_time: '',
        event_end_time: '',
        description: '',
        flyer: null,
        location_name: '',
        location_address: '',
        location_details: '',
        categories: [],
        tags: []
    });
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [message, setMessage] = useState(null);
    const [categories, setCategories] = useState([]);
    const [tags, setTags] = useState([]);
    const [recurringPattern, setRecurringPattern] = useState({
        type: 'none', // none, daily, weekly, monthly
        interval: 1,
        weekdays: [], // for weekly pattern
        endDate: '', // optional end date
    });

    const recurringOptions = [
        { value: 'none', label: 'No Recurrence' },
        { value: 'daily', label: 'Daily' },
        { value: 'weekly', label: 'Weekly' },
        { value: 'monthly', label: 'Monthly' }
    ];

    const weekdays = [
        { value: 0, label: 'Sun' },
        { value: 1, label: 'Mon' },
        { value: 2, label: 'Tue' },
        { value: 3, label: 'Wed' },
        { value: 4, label: 'Thu' },
        { value: 5, label: 'Fri' },
        { value: 6, label: 'Sat' }
    ];

    useEffect(() => {
        // Fetch available categories and tags
        const fetchTaxonomies = async () => {
            try {
                const [categoriesRes, tagsRes] = await Promise.all([
                    fetch('/wp-json/wp/v2/mayo_event_category'),
                    fetch('/wp-json/wp/v2/mayo_event_tag')
                ]);
                const categoriesData = await categoriesRes.json();
                const tagsData = await tagsRes.json();
                
                setCategories(categoriesData);
                setTags(tagsData);
            } catch (error) {
                console.error('Error fetching taxonomies:', error);
            }
        };
        
        fetchTaxonomies();
    }, []);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setIsSubmitting(true);
        setMessage(null);

        const data = new FormData();
        Object.keys(formData).forEach(key => {
            data.append(key, formData[key]);
        });

        try {
            const response = await fetch('/wp-json/event-manager/v1/submit-event', {
                method: 'POST',
                body: data
            });
            const result = await response.json();
            
            if (result.success) {
                setMessage({ type: 'success', text: 'Event submitted successfully! Awaiting approval.' });
                setFormData({
                    event_name: '',
                    event_type: '',
                    event_start_date: '',
                    event_end_date: '',
                    event_start_time: '',
                    event_end_time: '',
                    description: '',
                    flyer: null,
                    location_name: '',
                    location_address: '',
                    location_details: '',
                    categories: [],
                    tags: []
                });
            } else {
                setMessage({ type: 'error', text: result.message });
            }
        } catch (error) {
            setMessage({ type: 'error', text: 'Error submitting event. Please try again.' });
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleChange = (e) => {
        const { name, value, files } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: files ? files[0] : value
        }));
    };

    return (
        <div className="mayo-event-form">
            {message && (
                <div className={`mayo-message mayo-message-${message.type}`}>
                    {message.text}
                </div>
            )}
            <form onSubmit={handleSubmit}>
                <div className="mayo-form-field">
                    <label htmlFor="event_name">Event Name *</label>
                    <input
                        type="text"
                        id="event_name"
                        name="event_name"
                        value={formData.event_name}
                        onChange={handleChange}
                        required
                    />
                </div>

                <div className="mayo-form-field">
                    <label htmlFor="event_type">Event Type *</label>
                    <select
                        id="event_type"
                        name="event_type"
                        value={formData.event_type}
                        onChange={handleChange}
                        required
                    >
                        <option value="">Select Event Type</option>
                        <option value="Service">Service</option>
                        <option value="Activity">Activity</option>
                    </select>
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
                    <label htmlFor="description">Description</label>
                    <textarea
                        id="description"
                        name="description"
                        value={formData.description}
                        onChange={handleChange}
                    />
                </div>

                <div className="mayo-form-field">
                    <label htmlFor="flyer">Event Flyer</label>
                    <input
                        type="file"
                        id="flyer"
                        name="flyer"
                        accept="image/*"
                        onChange={handleChange}
                    />
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
                        {categories.map(category => (
                            <label key={category.id} className="mayo-taxonomy-item">
                                <input
                                    type="checkbox"
                                    checked={formData.categories.includes(category.id)}
                                    onChange={(e) => {
                                        const newCategories = e.target.checked
                                            ? [...formData.categories, category.id]
                                            : formData.categories.filter(id => id !== category.id);
                                        setFormData({...formData, categories: newCategories});
                                    }}
                                />
                                {category.name}
                            </label>
                        ))}
                    </div>
                </div>

                <div className="mayo-form-field">
                    <label>Tags</label>
                    <div className="mayo-taxonomy-list">
                        {tags.map(tag => (
                            <label key={tag.id} className="mayo-taxonomy-item">
                                <input
                                    type="checkbox"
                                    checked={formData.tags.includes(tag.id)}
                                    onChange={(e) => {
                                        const newTags = e.target.checked
                                            ? [...formData.tags, tag.id]
                                            : formData.tags.filter(id => id !== tag.id);
                                        setFormData({...formData, tags: newTags});
                                    }}
                                />
                                {tag.name}
                            </label>
                        ))}
                    </div>
                </div>

                <div className="mayo-form-field">
                    <label>Recurring Pattern</label>
                    <select
                        value={recurringPattern.type}
                        onChange={(e) => setRecurringPattern({
                            ...recurringPattern,
                            type: e.target.value
                        })}
                    >
                        {recurringOptions.map(option => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>

                {recurringPattern.type !== 'none' && (
                    <div className="mayo-recurring-options">
                        <div className="mayo-form-field">
                            <label>Repeat every</label>
                            <div className="mayo-recurring-interval">
                                <input
                                    type="number"
                                    min="1"
                                    value={recurringPattern.interval}
                                    onChange={(e) => setRecurringPattern({
                                        ...recurringPattern,
                                        interval: parseInt(e.target.value)
                                    })}
                                />
                                <span>{recurringPattern.type === 'daily' ? 'days' : 
                                      recurringPattern.type === 'weekly' ? 'weeks' : 'months'}</span>
                            </div>
                        </div>

                        {recurringPattern.type === 'weekly' && (
                            <div className="mayo-form-field">
                                <label>On these days</label>
                                <div className="mayo-weekday-selector">
                                    {weekdays.map(day => (
                                        <label key={day.value} className="mayo-weekday-option">
                                            <input
                                                type="checkbox"
                                                checked={recurringPattern.weekdays.includes(day.value)}
                                                onChange={(e) => {
                                                    const newWeekdays = e.target.checked
                                                        ? [...recurringPattern.weekdays, day.value]
                                                        : recurringPattern.weekdays.filter(d => d !== day.value);
                                                    setRecurringPattern({
                                                        ...recurringPattern,
                                                        weekdays: newWeekdays
                                                    });
                                                }}
                                            />
                                            {day.label}
                                        </label>
                                    ))}
                                </div>
                            </div>
                        )}

                        <div className="mayo-form-field">
                            <label>End Date (optional)</label>
                            <input
                                type="date"
                                value={recurringPattern.endDate}
                                onChange={(e) => setRecurringPattern({
                                    ...recurringPattern,
                                    endDate: e.target.value
                                })}
                            />
                        </div>
                    </div>
                )}

                <button 
                    type="submit" 
                    disabled={isSubmitting}
                    className="mayo-submit-button"
                >
                    {isSubmitting ? 'Submitting...' : 'Submit Event'}
                </button>
            </form>
        </div>
    );
};

export default EventForm; 