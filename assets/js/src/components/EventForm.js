import { useState } from '@wordpress/element';

const EventForm = () => {
    const [formData, setFormData] = useState({
        event_name: '',
        event_type: '',
        event_date: '',
        event_start_time: '',
        event_end_time: '',
        description: '',
        flyer: null
    });
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [message, setMessage] = useState(null);

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
                    event_date: '',
                    event_start_time: '',
                    event_end_time: '',
                    description: '',
                    flyer: null
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
                    <input
                        type="text"
                        id="event_type"
                        name="event_type"
                        value={formData.event_type}
                        onChange={handleChange}
                        required
                    />
                </div>

                <div className="mayo-form-field">
                    <label htmlFor="event_date">Date *</label>
                    <input
                        type="date"
                        id="event_date"
                        name="event_date"
                        value={formData.event_date}
                        onChange={handleChange}
                        required
                    />
                </div>

                <div className="mayo-form-field">
                    <label htmlFor="event_start_time">Start Time *</label>
                    <input
                        type="time"
                        id="event_start_time"
                        name="event_start_time"
                        value={formData.event_start_time}
                        onChange={handleChange}
                        required
                    />
                </div>

                <div className="mayo-form-field">
                    <label htmlFor="event_end_time">End Time *</label>
                    <input
                        type="time"
                        id="event_end_time"
                        name="event_end_time"
                        value={formData.event_end_time}
                        onChange={handleChange}
                        required
                    />
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