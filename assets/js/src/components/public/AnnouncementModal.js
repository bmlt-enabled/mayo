import { useEffect } from '@wordpress/element';

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

const AnnouncementModal = ({ announcements, timeFormat, onClose, backgroundColor, textColor }) => {
    // Build custom styles for header if colors are provided
    const headerStyle = {};
    if (backgroundColor) headerStyle.background = backgroundColor;
    if (textColor) headerStyle.color = textColor;

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

    if (announcements.length === 0) {
        return null;
    }

    return (
        <div className="mayo-announcement-modal-backdrop" onClick={handleBackdropClick}>
            <div className="mayo-announcement-modal">
                <button className="mayo-announcement-modal-close" onClick={onClose} title="Close">
                    <span className="dashicons dashicons-no-alt"></span>
                </button>

                <div className="mayo-announcement-modal-header" style={headerStyle}>
                    <span className="dashicons dashicons-megaphone"></span>
                    <h2>Announcements</h2>
                </div>

                <div className="mayo-announcement-modal-body">
                    <ul className="mayo-announcement-list">
                        {announcements.map(announcement => (
                            <li key={announcement.id} className="mayo-announcement-list-item">
                                <div className="mayo-announcement-list-header">
                                    {getPriorityBadge(announcement.priority)}
                                </div>
                                <a
                                    href={announcement.link}
                                    className="mayo-announcement-list-title"
                                    dangerouslySetInnerHTML={{ __html: announcement.title }}
                                />
                                {announcement.excerpt && (
                                    <div
                                        className="mayo-announcement-list-excerpt"
                                        dangerouslySetInnerHTML={{
                                            __html: announcement.excerpt.replace(/<[^>]+>/g, '').substring(0, 150) + '...'
                                        }}
                                    />
                                )}
                                {announcement.linked_events && announcement.linked_events.length > 0 && (
                                    <div className="mayo-announcement-linked-events" style={{ marginTop: '8px', fontSize: '12px', color: '#666' }}>
                                        <span style={{ marginRight: '4px' }}>Related:</span>
                                        {announcement.linked_events.map((event, index) => {
                                            const isCustom = event.source && event.source.type === 'custom';
                                            const isExternal = event.source && event.source.type === 'external';
                                            const isUnavailable = event.unavailable;

                                            // Custom links and external links open in new tab
                                            const opensInNewTab = isCustom || isExternal;

                                            return (
                                                <span key={`${event.source?.type || 'local'}-${event.source?.id || 'local'}-${event.id}`}>
                                                    {isUnavailable ? (
                                                        <span style={{ color: '#999', fontStyle: 'italic' }}>
                                                            {event.title}
                                                            {isExternal && event.source?.name && (
                                                                <span style={{ fontSize: '10px', marginLeft: '4px' }}>
                                                                    ({event.source.name})
                                                                </span>
                                                            )}
                                                        </span>
                                                    ) : (
                                                        <>
                                                            {isCustom && event.icon && (
                                                                <span
                                                                    className={`dashicons ${getIconClass(event.icon)}`}
                                                                    style={{ fontSize: '14px', marginRight: '2px', verticalAlign: 'middle', width: '14px', height: '14px' }}
                                                                />
                                                            )}
                                                            {!isCustom && (
                                                                <span className="dashicons dashicons-calendar-alt" style={{ fontSize: '14px', marginRight: '2px', verticalAlign: 'middle' }}></span>
                                                            )}
                                                            <a
                                                                href={event.permalink}
                                                                target={opensInNewTab ? '_blank' : '_self'}
                                                                rel={opensInNewTab ? 'noopener noreferrer' : undefined}
                                                                style={{ color: '#0073aa', textDecoration: 'none' }}
                                                            >
                                                                {event.title}
                                                                {isExternal && event.source?.name && (
                                                                    <span style={{
                                                                        fontSize: '10px',
                                                                        color: '#888',
                                                                        marginLeft: '4px'
                                                                    }}>
                                                                        ({event.source.name})
                                                                    </span>
                                                                )}
                                                            </a>
                                                        </>
                                                    )}
                                                    {index < announcement.linked_events.length - 1 && ', '}
                                                </span>
                                            );
                                        })}
                                    </div>
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
