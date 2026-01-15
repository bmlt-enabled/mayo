import { useEffect, useRef, useState, createPortal } from '@wordpress/element';

// Map icon names to dashicon classes
const getIconClass = (iconName) => {
    const iconMap = {
        'external': 'dashicons-external',
        'hotel': 'dashicons-building',
        'info': 'dashicons-info',
        'calendar': 'dashicons-calendar-alt',
        'location': 'dashicons-location',
        'link': 'dashicons-admin-links',
    };
    return iconMap[iconName] || 'dashicons-external';
};

const AnnouncementBanner = ({ announcements, currentIndex, onPrev, onNext, onClose, backgroundColor, textColor, autoRotateInterval = 5000 }) => {
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
    }, [announcements]);

    // Auto-rotate through announcements
    useEffect(() => {
        if (announcements.length <= 1 || isPaused) {
            return;
        }

        const interval = setInterval(() => {
            onNext();
        }, autoRotateInterval);

        return () => clearInterval(interval);
    }, [announcements.length, isPaused, onNext, autoRotateInterval]);

    if (announcements.length === 0) {
        return null;
    }

    const currentAnnouncement = announcements[currentIndex];

    // Guard against undefined announcement (can happen during state transitions)
    if (!currentAnnouncement) {
        return null;
    }

    const hasMultiple = announcements.length > 1;

    // Get priority badge
    const getPriorityBadge = (priority) => {
        if (!priority || priority === 'normal') return null;
        const colors = {
            low: '#6c757d',
            high: '#ff9800',
            urgent: '#dc3545'
        };
        return (
            <span
                className="mayo-announcement-priority"
                style={{
                    backgroundColor: colors[priority] || colors.normal,
                    color: '#fff',
                    padding: '2px 6px',
                    borderRadius: '3px',
                    fontSize: '10px',
                    textTransform: 'uppercase',
                    marginRight: '8px'
                }}
            >
                {priority}
            </span>
        );
    };

    return createPortal(
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
                        {getPriorityBadge(currentAnnouncement.priority)}
                        <a
                            href={currentAnnouncement.link}
                            className="mayo-announcement-title"
                            dangerouslySetInnerHTML={{ __html: currentAnnouncement.title }}
                        />
                        {currentAnnouncement.linked_events && currentAnnouncement.linked_events.length > 0 && (
                            <span className="mayo-announcement-linked-events" style={{ marginLeft: '8px', fontSize: '12px', opacity: 0.9 }}>
                                {currentAnnouncement.linked_events.map((event, index) => {
                                    const isCustom = event.source && event.source.type === 'custom';
                                    const isExternal = event.source && event.source.type === 'external';
                                    const isUnavailable = event.unavailable;

                                    // Custom links and external links open in new tab
                                    const opensInNewTab = isCustom || isExternal;

                                    return (
                                        <span key={`${event.source?.type || 'local'}-${event.source?.id || 'local'}-${event.id}`}>
                                            {isUnavailable ? (
                                                <span style={{ opacity: 0.7, fontStyle: 'italic' }}>
                                                    {event.title}
                                                </span>
                                            ) : (
                                                <>
                                                    {isCustom && event.icon && (
                                                        <span
                                                            className={`dashicons ${getIconClass(event.icon)}`}
                                                            style={{ fontSize: '12px', marginRight: '2px', verticalAlign: 'middle', width: '12px', height: '12px' }}
                                                        />
                                                    )}
                                                    {!isCustom && index === 0 && (
                                                        <span className="dashicons dashicons-calendar-alt" style={{ fontSize: '12px', marginRight: '4px', verticalAlign: 'middle' }}></span>
                                                    )}
                                                    <a
                                                        href={event.permalink}
                                                        target={opensInNewTab ? '_blank' : '_self'}
                                                        rel={opensInNewTab ? 'noopener noreferrer' : undefined}
                                                        style={{ color: 'inherit', textDecoration: 'underline' }}
                                                    >
                                                        {event.title}
                                                        {isExternal && event.source?.name && (
                                                            <span style={{ opacity: 0.8, marginLeft: '2px' }}>
                                                                ({event.source.name})
                                                            </span>
                                                        )}
                                                    </a>
                                                </>
                                            )}
                                            {index < currentAnnouncement.linked_events.length - 1 && ', '}
                                        </span>
                                    );
                                })}
                            </span>
                        )}
                        {hasMultiple && (
                            <span className="mayo-announcement-counter">
                                {currentIndex + 1} / {announcements.length}
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
        </div>,
        document.body
    );
};

export default AnnouncementBanner;
