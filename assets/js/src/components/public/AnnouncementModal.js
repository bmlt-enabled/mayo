import { useEffect } from '@wordpress/element';
import { monthNames } from '../../util';

const AnnouncementModal = ({ events, timeFormat, onClose }) => {
    // Close on escape key and prevent body scroll
    useEffect(() => {
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                onClose();
            }
        };
        document.addEventListener('keydown', handleEscape);
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', handleEscape);
            document.body.style.overflow = '';
        };
    }, [onClose]);

    // Close when clicking backdrop
    const handleBackdropClick = (e) => {
        if (e.target === e.currentTarget) {
            onClose();
        }
    };

    // Format date for display
    const formatEventDate = (event) => {
        if (!event.meta.event_start_date) return '';

        const startDate = new Date(event.meta.event_start_date + 'T00:00:00');
        const hasEndDate = event.meta.event_end_date && event.meta.event_end_date !== event.meta.event_start_date;

        if (hasEndDate) {
            const endDate = new Date(event.meta.event_end_date + 'T00:00:00');
            return `${monthNames[startDate.getMonth()]} ${startDate.getDate()} - ${monthNames[endDate.getMonth()]} ${endDate.getDate()}, ${endDate.getFullYear()}`;
        }

        return `${monthNames[startDate.getMonth()]} ${startDate.getDate()}, ${startDate.getFullYear()}`;
    };

    if (events.length === 0) {
        return null;
    }

    return (
        <div className="mayo-announcement-modal-backdrop" onClick={handleBackdropClick}>
            <div className="mayo-announcement-modal">
                <button className="mayo-announcement-modal-close" onClick={onClose} title="Close">
                    <span className="dashicons dashicons-no-alt"></span>
                </button>

                <div className="mayo-announcement-modal-header">
                    <span className="dashicons dashicons-megaphone"></span>
                    <h2>Announcements</h2>
                </div>

                <div className="mayo-announcement-modal-body">
                    <ul className="mayo-announcement-list">
                        {events.map(event => (
                            <li key={event.id} className="mayo-announcement-list-item">
                                <div className="mayo-announcement-list-date">
                                    {formatEventDate(event)}
                                </div>
                                <a
                                    href={event.link}
                                    className="mayo-announcement-list-title"
                                    dangerouslySetInnerHTML={{ __html: event.title.rendered }}
                                />
                                {event.content.rendered && (
                                    <div
                                        className="mayo-announcement-list-excerpt"
                                        dangerouslySetInnerHTML={{
                                            __html: event.content.rendered.replace(/<[^>]+>/g, '').substring(0, 150) + '...'
                                        }}
                                    />
                                )}
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="mayo-announcement-modal-footer">
                    <button className="mayo-announcement-dismiss-button" onClick={onClose}>
                        Dismiss
                    </button>
                </div>
            </div>
        </div>
    );
};

export default AnnouncementModal;
