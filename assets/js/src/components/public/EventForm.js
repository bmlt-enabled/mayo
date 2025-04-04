import { useState, useEffect } from '@wordpress/element';
import { useEventProvider } from '../providers/EventProvider';
import { uploadPDF } from '../../util';

const EventForm = () => {
    const [formData, setFormData] = useState({
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
        service_body: '',
        email: ''
    });
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [message, setMessage] = useState(null);
    const [categories, setCategories] = useState([]);
    const [tags, setTags] = useState([]);
    const [error, setError] = useState(null);
    const { serviceBodies } = useEventProvider();
    const [pdfUploadStatus, setPdfUploadStatus] = useState({
        isUploading: false,
        error: null
    });
    const [uploadType, setUploadType] = useState(null);

    useEffect(() => {
        const fetchTaxonomies = async () => {
            try {
                const [categoriesRes, tagsRes] = await Promise.all([
                    fetch('/wp-json/wp/v2/categories'),
                    fetch('/wp-json/wp/v2/tags')
                ]);

                if (!categoriesRes.ok || !tagsRes.ok) {
                    throw new Error('Failed to fetch taxonomies');
                }

                const categoriesData = await categoriesRes.json();
                const tagsData = await tagsRes.json();
                
                setCategories(Array.isArray(categoriesData) ? categoriesData : []);
                setTags(Array.isArray(tagsData) ? tagsData : []);
            } catch (error) {
                console.error('Error fetching taxonomies:', error);
                // Set empty arrays as fallback
                setCategories([]);
                setTags([]);
            }
        };
        
        fetchTaxonomies();
    }, []);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setIsSubmitting(true);
        setMessage(null);

        try {
            // Check required fields
            if (!formData.event_name || !formData.event_type || !formData.service_body) {
                throw new Error('Please fill in all required fields: Event Name, Event Type, and Service Body');
            }

            const data = new FormData();
            
            // Add all form fields to FormData
            Object.keys(formData).forEach(key => {
                if (key === 'flyer' && formData[key] instanceof File) {
                    data.append('flyer', formData[key]);
                }
                else if (Array.isArray(formData[key])) {
                    formData[key].forEach(value => {
                        data.append(`${key}[]`, value);
                    });
                }
                else if (formData[key] != null && formData[key] !== '') {
                    data.append(key, formData[key]);
                }
            });

            // Log FormData for debugging
            console.log('Form data being sent:');
            for (let pair of data.entries()) {
                console.log(pair[0], pair[1]);
            }

            // Get the nonce
            const nonce = window.mayoApiSettings?.nonce || 
                         document.querySelector('#_wpnonce')?.value || 
                         window.wpApiSettings?.nonce;

            const response = await fetch('/wp-json/event-manager/v1/submit-event', {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': nonce
                }
            });

            const result = await response.json();
            console.log('Server response:', result);

            // Simplified success check
            if (response.ok && (result.id || result.success)) {
                setMessage({ 
                    type: 'success', 
                    text: 'Event submitted successfully!' 
                });

                // Reset form
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
                    service_body: '',
                    email: '',
                });
                setUploadType(null);
            } else {
                throw new Error(result.message || 'Failed to submit event');
            }
        } catch (error) {
            console.error('Form submission error:', error);
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
            // Set upload type for UI purposes only
            setUploadType(file.type === 'application/pdf' ? 'pdf' : 'image');
            // Store all files as attachments in the flyer field
            setFormData(prev => ({
                ...prev,
                flyer: file
            }));
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

    if (error) return <div className="mayo-error">{error}</div>;

    return (
        <div className="mayo-event-form">
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
                        {serviceBodies.map((body) => (
                            <option key={body.id} value={body.id}>
                                {body.name} ({body.id})
                            </option>
                        ))}
                    </select>
                </div>

                <div className="mayo-form-field">
                    <label htmlFor="email">Email *</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value={formData.email}
                        onChange={handleChange}
                        required
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
                    <label htmlFor="timezone">Timezone</label>
                    <select
                        id="timezone"
                        name="timezone"
                        value={formData.timezone}
                        onChange={handleChange}
                        required
                    >
                        <option value="America/New_York">Eastern Time</option>
                        <option value="America/Chicago">Central Time</option>
                        <option value="America/Denver">Mountain Time</option>
                        <option value="America/Los_Angeles">Pacific Time</option>
                        <option value="America/Anchorage">Alaska Time</option>
                        <option value="Pacific/Honolulu">Hawaii Time</option>
                    </select>
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
                    <label>Event Flyer</label>
                    <div className="mayo-upload-section">
                        {!uploadType && (
                            <>
                                <input
                                    type="file"
                                    id="flyer-upload"
                                    name="flyer"
                                    accept="image/*,.pdf"
                                    onChange={handleChange}
                                    style={{ display: 'none' }}
                                />
                                <label htmlFor="flyer-upload" className="mayo-upload-button">
                                    Upload Flyer
                                </label>
                                <p className="mayo-upload-info">
                                    Supported file types: Images (.jpg, .jpeg, .png, .gif) or PDF
                                </p>
                            </>
                        )}

                        {uploadType && (
                            <div className="mayo-upload-preview">
                                <p>
                                    Selected {uploadType === 'pdf' ? 'PDF' : 'Image'}: {' '}
                                    {uploadType === 'pdf' ? 
                                        (formData.pdf_file?.name || 'No file selected') : 
                                        (formData.flyer?.name || 'No file selected')
                                    }
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
                                {category?.name || 'Unnamed Category'}
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
                                {tag?.name || 'Unnamed Tag'}
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