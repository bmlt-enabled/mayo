import { useState, useEffect, useMemo } from '@wordpress/element';
import { useEventProvider } from '../providers/EventProvider';
import { apiFetch } from '../../util';

const AnnouncementForm = () => {
    // Get the settings from the data attribute
    const formElement = document.getElementById('mayo-announcement-form');
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

    // Define default required fields
    const defaultRequiredFields = [
        'title',
        'description',
        'service_body',
        'email',
        'contact_name'
    ];

    // Get additional required fields from settings
    const additionalRequiredFields = settings.additionalRequiredFields ?
        settings.additionalRequiredFields.split(',').map(field => field.trim()) :
        [];

    // Combine both arrays for all required fields
    const allRequiredFields = [...defaultRequiredFields, ...additionalRequiredFields];

    // Filter service bodies based on subscription options and shortcode configuration
    const getFilteredServiceBodies = () => {
        let filtered = serviceBodies;

        // First filter by subscription options (if any are configured)
        const allowedSubscriptionIds = subscriptionOptions.service_bodies.map(sb => sb.id.toString());
        if (allowedSubscriptionIds.length > 0) {
            filtered = filtered.filter(body => allowedSubscriptionIds.includes(body.id.toString()));
        }

        // Then filter by shortcode configuration
        if (serviceBodySettings.default_service_bodies) {
            const allowedIds = serviceBodySettings.default_service_bodies
                .split(',')
                .map(id => id.trim())
                .filter(id => id);

            filtered = filtered.filter(body => allowedIds.includes(body.id.toString()));
        }

        return filtered;
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
        // Check if subscription options restrict service bodies
        const allowedSubscriptionIds = subscriptionOptions.service_bodies.map(sb => sb.id.toString());
        if (allowedSubscriptionIds.length > 0 && !allowedSubscriptionIds.includes('0')) {
            return false;
        }

        // Check shortcode settings
        if (!serviceBodySettings.default_service_bodies) return true;
        return serviceBodySettings.default_service_bodies.includes('0');
    };

    // Check if flyer upload should be shown (via shortcode param)
    const showFlyer = settings.showFlyer === true || settings.showFlyer === 'true';

    const [formData, setFormData] = useState({
        title: '',
        description: '',
        start_date: '',
        end_date: '',
        flyer: null,
        categories: [],
        tags: [],
        service_body: '',
        email: '',
        contact_name: ''
    });
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [message, setMessage] = useState(null);
    const [categories, setCategories] = useState([]);
    const [tags, setTags] = useState([]);
    const [error, setError] = useState(null);
    const { serviceBodies } = useEventProvider();
    const [uploadType, setUploadType] = useState(null);
    const [serviceBodySettings, setServiceBodySettings] = useState({
        default_service_bodies: ''
    });
    const [subscriptionOptions, setSubscriptionOptions] = useState({
        categories: [],
        tags: [],
        service_bodies: []
    });

    // Load service body settings and subscription options
    useEffect(() => {
        const fetchSettings = async () => {
            try {
                const [settingsResponse, subscriptionResponse] = await Promise.all([
                    apiFetch('/settings'),
                    apiFetch('/subscription-options')
                ]);

                // Start with global settings
                let finalSettings = {
                    default_service_bodies: settingsResponse.default_service_bodies || ''
                };

                // Override with shortcode parameters if provided
                if (settings.defaultServiceBodies !== undefined && settings.defaultServiceBodies !== '') {
                    finalSettings.default_service_bodies = settings.defaultServiceBodies;
                }

                setServiceBodySettings(finalSettings);
                setSubscriptionOptions(subscriptionResponse || { categories: [], tags: [], service_bodies: [] });

                // If there are default service bodies and only one, pre-select it
                const defaultIds = finalSettings.default_service_bodies?.split(',').map(id => id.trim()).filter(id => id);
                if (defaultIds && defaultIds.length === 1) {
                    setFormData(prev => ({
                        ...prev,
                        service_body: defaultIds[0]
                    }));
                }
            } catch (error) {
                console.error('Error fetching settings:', error);
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

                // Get allowed category and tag IDs from subscription options
                const allowedCategoryIds = subscriptionOptions.categories.map(c => c.id);
                const allowedTagIds = subscriptionOptions.tags.map(t => t.id);

                // Filter categories: first by subscription options, then by shortcode filters
                const filteredCategories = categoriesData.filter(cat => {
                    // First check subscription options (if any are configured)
                    if (allowedCategoryIds.length > 0 && !allowedCategoryIds.includes(cat.id)) {
                        return false;
                    }

                    // Then apply shortcode filters
                    const catSlug = (cat.slug || '').toLowerCase();
                    if (includedCategories.length > 0) {
                        return includedCategories.includes(catSlug);
                    } else if (excludedCategories.length > 0) {
                        return !excludedCategories.includes(catSlug);
                    }
                    return true;
                });

                // Filter tags: first by subscription options, then by shortcode filters
                const filteredTags = tagsData.filter(tag => {
                    // First check subscription options (if any are configured)
                    if (allowedTagIds.length > 0 && !allowedTagIds.includes(tag.id)) {
                        return false;
                    }

                    // Then apply shortcode filters
                    const tagSlug = (tag.slug || '').toLowerCase();
                    if (includedTags.length > 0) {
                        return includedTags.includes(tagSlug);
                    } else if (excludedTags.length > 0) {
                        return !excludedTags.includes(tagSlug);
                    }
                    return true;
                });

                setCategories(Array.isArray(filteredCategories) ? filteredCategories : []);
                setTags(Array.isArray(filteredTags) ? filteredTags : []);
            } catch (error) {
                console.error('Error fetching taxonomies:', error);
                setCategories([]);
                setTags([]);
            }
        };

        fetchTaxonomies();
    }, [includedCategories, excludedCategories, includedTags, excludedTags, subscriptionOptions]);

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
                    throw new Error('You did not attach a valid image file. Please choose a valid image file (JPG, PNG, or GIF)');
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
                // Handle other fields
                else if (formData[key] != null && formData[key] !== '') {
                    data.append(key, formData[key]);
                }
            });

            const nonce = window.mayoApiSettings?.nonce ||
                         document.querySelector('#_wpnonce')?.value ||
                         window.wpApiSettings?.nonce;

            const result = await apiFetch('/submit-announcement', {
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
                    text: 'Announcement submitted successfully!'
                });

                // Reset form - preserve service_body if only one is configured
                const defaultIds = serviceBodySettings.default_service_bodies?.split(',').map(id => id.trim()).filter(id => id);
                const preservedServiceBody = (defaultIds && defaultIds.length === 1) ? defaultIds[0] : '';

                setFormData({
                    title: '',
                    description: '',
                    start_date: '',
                    end_date: '',
                    flyer: null,
                    categories: [],
                    tags: [],
                    service_body: preservedServiceBody,
                    email: '',
                    contact_name: ''
                });
                setUploadType(null);
            } else {
                throw new Error(result.message || 'Failed to submit announcement');
            }
        } catch (error) {
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
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            const fileExtension = file.name.split('.').pop().toLowerCase();
            const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (!allowedTypes.includes(file.type) || !allowedExtensions.includes(fileExtension)) {
                setMessage({
                    type: 'error',
                    text: 'The selected file is not a valid image. Please use a valid image file (JPG, PNG, or GIF)'
                });
                e.target.value = '';
                setFormData(prev => ({
                    ...prev,
                    flyer: null
                }));
                setUploadType(null);
                return;
            }

            // Verify it's actually an image
            const reader = new FileReader();
            reader.onload = (event) => {
                const img = new Image();
                img.onload = () => {
                    setUploadType('image');
                    setFormData(prev => ({
                        ...prev,
                        flyer: file
                    }));
                    setMessage(null);
                };
                img.onerror = () => {
                    setMessage({
                        type: 'error',
                        text: 'The selected file is not a valid image. Please choose a valid image file (JPG, PNG, or GIF)'
                    });
                    e.target.value = '';
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
                e.target.value = '';
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

    const handleServiceBodyChange = (event) => {
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

    const isFieldRequired = (fieldName) => {
        return allRequiredFields.includes(fieldName);
    };

    if (error) return <div className="mayo-error">{error}</div>;

    return (
        <div className="mayo-announcement-form">
            <form onSubmit={handleSubmit}>
                <div className="mayo-form-field">
                    <label htmlFor="title">
                        Announcement Title {isFieldRequired('title') && '*'}
                    </label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        value={formData.title}
                        onChange={handleChange}
                        required={isFieldRequired('title')}
                    />
                </div>

                {shouldShowServiceBodyField() && (
                    <div className="mayo-form-field">
                        <label htmlFor="service_body">Service Body *</label>
                        <select
                            id="service_body"
                            name="service_body"
                            value={formData.service_body}
                            onChange={handleServiceBodyChange}
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
                        <label htmlFor="start_date">
                            Start Date {isFieldRequired('start_date') && '*'}
                        </label>
                        <input
                            type="date"
                            id="start_date"
                            name="start_date"
                            value={formData.start_date}
                            onChange={handleChange}
                            required={isFieldRequired('start_date')}
                        />
                    </div>

                    <div className="mayo-form-field">
                        <label htmlFor="end_date">
                            End Date {isFieldRequired('end_date') && '*'}
                        </label>
                        <input
                            type="date"
                            id="end_date"
                            name="end_date"
                            value={formData.end_date}
                            onChange={handleChange}
                            required={isFieldRequired('end_date')}
                        />
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
                        rows="6"
                    />
                </div>

                {showFlyer && (
                    <div className="mayo-form-field">
                        <label>
                            Image/Flyer {isFieldRequired('flyer') && '*'}
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
                                        Upload Image
                                    </label>
                                    <p className="mayo-upload-info">
                                        Supported file types: Images (.jpg, .jpeg, .png, .gif)
                                        {isFieldRequired('flyer') && ' (Required)'}
                                    </p>
                                </>
                            )}

                            {uploadType && (
                                <div className="mayo-upload-preview">
                                    <p>
                                        Selected: {formData.flyer?.name || 'No file selected'}
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
                )}

                {categories.length > 0 && (
                    <div className="mayo-form-field">
                        <label>Categories</label>
                        <div className="mayo-taxonomy-list">
                            {categories.map(category => (
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
                )}

                {tags.length > 0 && (
                    <div className="mayo-form-field">
                        <label>Tags</label>
                        <div className="mayo-taxonomy-list">
                            {tags.map(tag => (
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
                )}

                <button
                    type="submit"
                    disabled={isSubmitting}
                    className="mayo-submit-button"
                >
                    {isSubmitting ? 'Submitting...' : 'Submit Announcement'}
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

export default AnnouncementForm;
