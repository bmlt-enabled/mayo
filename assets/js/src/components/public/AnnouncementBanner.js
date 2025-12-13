import { useEffect, useRef } from '@wordpress/element';
import { monthNames } from '../../util';

const AnnouncementBanner = ({ events, currentIndex, timeFormat, onPrev, onNext, onClose }) => {
    const bannerRef = useRef(null);

    // Adjust body padding when banner is shown
    useEffect(() => {
        const updateBodyPadding = () => {
            if (bannerRef.current) {
                const height = bannerRef.current.offsetHeight;
                document.body.style.paddingTop = `${height}px`;
            }
        };

        updateBodyPadding();
        window.addEventListener('resize', updateBodyPadding);

        return () => {
            document.body.style.paddingTop = '';
            window.removeEventListener('resize', updateBodyPadding);
        };
    }, [events]);

    if (events.length === 0) {
        return null;
    }

    const currentEvent = events[currentIndex];
    const hasMultiple = events.length > 1;

    // Format date for display
    const formatEventDate = (event) => {
        if (!event.meta.event_start_date) return '';

        const startDate = new Date(event.meta.event_start_date + 'T00:00:00');
        const hasEndDate = event.meta.event_end_date && event.meta.event_end_date !== event.meta.event_start_date;

        if (hasEndDate) {
            const endDate = new Date(event.meta.event_end_date + 'T00:00:00');
            return `${monthNames[startDate.getMonth()]} ${startDate.getDate()} - ${monthNames[endDate.getMonth()]} ${endDate.getDate()}`;
        }

        return `${monthNames[startDate.getMonth()]} ${startDate.getDate()}`;
    };

    return (
        <div className="mayo-announcement-banner" ref={bannerRef}>
            <div className="mayo-announcement-banner-content">
                {hasMultiple && (
                    <button
                        className="mayo-announcement-nav mayo-announcement-prev"
                        onClick={onPrev}
                        title="Previous"
                    >
                        <span className="dashicons dashicons-arrow-left-alt2"></span>
                    </button>
                )}

                <div className="mayo-announcement-item">
                    <span className="mayo-announcement-icon">
                        <span className="dashicons dashicons-megaphone"></span>
                    </span>
                    <span className="mayo-announcement-date">
                        {formatEventDate(currentEvent)}
                    </span>
                    <a
                        href={currentEvent.link}
                        className="mayo-announcement-title"
                        dangerouslySetInnerHTML={{ __html: currentEvent.title.rendered }}
                    />
                    {hasMultiple && (
                        <span className="mayo-announcement-counter">
                            {currentIndex + 1} / {events.length}
                        </span>
                    )}
                </div>

                {hasMultiple && (
                    <button
                        className="mayo-announcement-nav mayo-announcement-next"
                        onClick={onNext}
                        title="Next"
                    >
                        <span className="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                )}
            </div>

            <button
                className="mayo-announcement-close"
                onClick={onClose}
                title="Dismiss"
            >
                <span className="dashicons dashicons-no-alt"></span>
            </button>
        </div>
    );
};

export default AnnouncementBanner;
