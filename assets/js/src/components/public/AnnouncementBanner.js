import { useEffect, useRef, useState } from '@wordpress/element';
import { monthNames } from '../../util';

const AnnouncementBanner = ({ events, currentIndex, timeFormat, onPrev, onNext, onClose, backgroundColor, textColor, autoRotateInterval = 5000 }) => {
    const bannerRef = useRef(null);
    const [isPaused, setIsPaused] = useState(false);

    // Build custom styles if colors are provided
    const customStyle = {};
    if (backgroundColor) customStyle.background = backgroundColor;
    if (textColor) customStyle.color = textColor;

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

    // Auto-rotate through announcements
    useEffect(() => {
        if (events.length <= 1 || isPaused) {
            return;
        }

        const interval = setInterval(() => {
            onNext();
        }, autoRotateInterval);

        return () => clearInterval(interval);
    }, [events.length, isPaused, onNext, autoRotateInterval]);

    if (events.length === 0) {
        return null;
    }

    const currentEvent = events[currentIndex];

    // Guard against undefined event (can happen during state transitions)
    if (!currentEvent) {
        return null;
    }

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
        <div
            className="mayo-announcement-banner"
            ref={bannerRef}
            style={customStyle}
            onMouseEnter={() => setIsPaused(true)}
            onMouseLeave={() => setIsPaused(false)}
        >
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

                <div className="mayo-announcement-slider">
                    <div key={currentIndex} className="mayo-announcement-item mayo-slide-enter-down">
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
