import { useState } from '@wordpress/element';
import { apiFetch } from '../../util';

const SubscribeForm = () => {
    const [email, setEmail] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [message, setMessage] = useState(null);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setIsSubmitting(true);
        setMessage(null);

        try {
            const result = await apiFetch('/subscribe', {
                method: 'POST',
                body: JSON.stringify({ email }),
            });

            if (result.success) {
                setMessage({
                    type: 'success',
                    text: result.message
                });
                setEmail('');
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
                    <button
                        type="submit"
                        disabled={isSubmitting}
                        className="mayo-subscribe-button"
                    >
                        {isSubmitting ? 'Subscribing...' : 'Subscribe'}
                    </button>
                </div>

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
