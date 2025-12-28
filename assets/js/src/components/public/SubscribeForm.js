import { useState, useEffect } from '@wordpress/element';
import { apiFetch } from '../../util';
import { useEventProvider } from '../providers/EventProvider';

const SubscribeForm = () => {
    const [email, setEmail] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isLoading, setIsLoading] = useState(true);
    const [message, setMessage] = useState(null);

    // Subscription options from admin settings
    const [options, setOptions] = useState({
        categories: [],
        tags: [],
        service_bodies: []
    });

    // User's selected preferences
    const [preferences, setPreferences] = useState({
        categories: [],
        tags: [],
        service_bodies: []
    });

    const { getServiceBodyName } = useEventProvider();

    // Fetch available subscription options on mount
    useEffect(() => {
        const fetchOptions = async () => {
            try {
                const result = await apiFetch('/subscription-options');
                if (result) {
                    setOptions({
                        categories: result.categories || [],
                        tags: result.tags || [],
                        service_bodies: result.service_bodies || []
                    });
                }
            } catch (error) {
                console.error('Failed to fetch subscription options:', error);
            } finally {
                setIsLoading(false);
            }
        };
        fetchOptions();
    }, []);

    // Check if at least one preference is selected
    const hasSelection = () => {
        return (
            preferences.categories.length > 0 ||
            preferences.tags.length > 0 ||
            preferences.service_bodies.length > 0
        );
    };

    // Check if options are available
    const hasOptions = () => {
        return (
            options.categories.length > 0 ||
            options.tags.length > 0 ||
            options.service_bodies.length > 0
        );
    };

    // Toggle a preference
    const togglePreference = (type, id) => {
        setPreferences(prev => {
            const current = prev[type] || [];
            const updated = current.includes(id)
                ? current.filter(item => item !== id)
                : [...current, id];
            return { ...prev, [type]: updated };
        });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();

        // Require at least one preference if options are available
        if (hasOptions() && !hasSelection()) {
            setMessage({
                type: 'error',
                text: 'Please select at least one preference to subscribe.'
            });
            return;
        }

        setIsSubmitting(true);
        setMessage(null);

        try {
            const body = { email };
            if (hasOptions()) {
                body.preferences = preferences;
            }

            const result = await apiFetch('/subscribe', {
                method: 'POST',
                body: JSON.stringify(body),
            });

            if (result.success) {
                setMessage({
                    type: 'success',
                    text: result.message
                });
                setEmail('');
                setPreferences({
                    categories: [],
                    tags: [],
                    service_bodies: []
                });
            } else {
                setMessage({
                    type: 'error',
                    text: result.message || 'An error occurred. Please try again.'
                });
            }
        } catch (error) {
            setMessage({
                type: 'error',
                text: error.message || 'An error occurred. Please try again.'
            });
        } finally {
            setIsSubmitting(false);
        }
    };

    if (isLoading) {
        return (
            <div className="mayo-subscribe-form">
                <p>Loading...</p>
            </div>
        );
    }

    return (
        <div className="mayo-subscribe-form">
            <form onSubmit={handleSubmit}>
                <div className="mayo-subscribe-input-group">
                    <input
                        type="email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        placeholder="Enter your email address"
                        required
                        disabled={isSubmitting}
                        className="mayo-subscribe-email"
                    />
                </div>

                {hasOptions() && (
                    <div className="mayo-subscribe-preferences">
                        <p className="mayo-subscribe-preferences-label">
                            Select what you'd like to receive notifications about:
                        </p>

                        {options.categories.length > 0 && (
                            <div className="mayo-subscribe-preference-group">
                                <span className="mayo-subscribe-preference-heading">Categories</span>
                                <div className="mayo-subscribe-checkboxes">
                                    {options.categories.map(cat => (
                                        <label key={cat.id} className="mayo-subscribe-checkbox">
                                            <input
                                                type="checkbox"
                                                checked={preferences.categories.includes(cat.id)}
                                                onChange={() => togglePreference('categories', cat.id)}
                                                disabled={isSubmitting}
                                            />
                                            <span>{cat.name}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        )}

                        {options.tags.length > 0 && (
                            <div className="mayo-subscribe-preference-group">
                                <span className="mayo-subscribe-preference-heading">Tags</span>
                                <div className="mayo-subscribe-checkboxes">
                                    {options.tags.map(tag => (
                                        <label key={tag.id} className="mayo-subscribe-checkbox">
                                            <input
                                                type="checkbox"
                                                checked={preferences.tags.includes(tag.id)}
                                                onChange={() => togglePreference('tags', tag.id)}
                                                disabled={isSubmitting}
                                            />
                                            <span>{tag.name}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        )}

                        {options.service_bodies.length > 0 && (
                            <div className="mayo-subscribe-preference-group">
                                <span className="mayo-subscribe-preference-heading">Service Bodies</span>
                                <div className="mayo-subscribe-checkboxes">
                                    {options.service_bodies.map(sb => (
                                        <label key={sb.id} className="mayo-subscribe-checkbox">
                                            <input
                                                type="checkbox"
                                                checked={preferences.service_bodies.includes(sb.id)}
                                                onChange={() => togglePreference('service_bodies', sb.id)}
                                                disabled={isSubmitting}
                                            />
                                            <span>{getServiceBodyName(sb.id, sb.source_id) || sb.name || sb.id}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                <button
                    type="submit"
                    disabled={isSubmitting || (hasOptions() && !hasSelection())}
                    className="mayo-subscribe-button"
                >
                    {isSubmitting ? 'Subscribing...' : 'Subscribe'}
                </button>

                {message && (
                    <div className={`mayo-subscribe-message mayo-subscribe-message-${message.type}`}>
                        {message.text}
                    </div>
                )}
            </form>
        </div>
    );
};

export default SubscribeForm;
