import { useState, useEffect } from '@wordpress/element';
import { apiFetch } from '../../util';
import { useEventProvider } from '../providers/EventProvider';

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

const AnnouncementDetails = () => {
    const [announcement, setAnnouncement] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const { getServiceBodyName } = useEventProvider();

    useEffect(() => {
        const fetchAnnouncement = async () => {
            try {
                const pathParts = window.location.pathname.split('/');
                const announcementSlug = pathParts[pathParts.length - 2];

                const response = await apiFetch(`/announcement-by-slug/${announcementSlug}`);
                if (response) {
                    setAnnouncement(response);
                } else {
                    throw new Error('Announcement not found');
                }
            } catch (err) {
                console.error('Error fetching announcement:', err);
                setError('Failed to load announcement');
            } finally {
                setLoading(false);
            }
        };

        fetchAnnouncement();
    }, []);

    if (loading) {
        return (
            <div className="mayo-single-container">
                <div className="mayo-loading">Loading announcement...</div>
            </div>
        );
    }
    if (error) {
        return (
            <div className="mayo-single-container">
                <div className="mayo-error">{error}</div>
            </div>
        );
    }
    if (!announcement) {
        return (
            <div className="mayo-single-container">
                <div className="mayo-error">Announcement not found</div>
            </div>
        );
    }

    const priorityColors = {
        urgent: '#dc3545',
        high: '#ff9800',
        normal: '#0073aa',
        low: '#6c757d'
    };

    const priorityColor = priorityColors[announcement.priority] || priorityColors.normal;

    // Format time from 24h to 12h format
    const formatTime = (time) => {
        if (!time) return '';
        try {
            const [hours, minutes] = time.split(':');
            const hour = parseInt(hours, 10);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const hour12 = hour % 12 || 12;
            return `${hour12}:${minutes} ${ampm}`;
        } catch {
            return time;
        }
    };

    // Format date and time together
    const formatDateTime = (date, time) => {
        if (!date) return null;
        let formatted = date;
        if (time) {
            formatted += ` at ${formatTime(time)}`;
        }
        return formatted;
    };

    return (
        <div className="mayo-single-container">
            <article className={`mayo-single-announcement mayo-priority-${announcement.priority || 'normal'}`}>
                <header className="mayo-single-announcement-header">
                    <div className="mayo-announcement-priority-bar" style={{ backgroundColor: priorityColor }} />
                    <h1 className="mayo-single-announcement-title">
                        <span dangerouslySetInnerHTML={{ __html: announcement.title }} />
                    </h1>
                    {announcement.priority && announcement.priority !== 'normal' && (
                        <span
                            className="mayo-priority-badge"
                            style={{ backgroundColor: priorityColor }}
                        >
                            {announcement.priority}
                        </span>
                    )}
                </header>

                {announcement.featured_image && (
                    <div className="mayo-single-announcement-image">
                        <a href={announcement.featured_image} target="_blank" rel="noopener noreferrer">
                            <img src={announcement.featured_image} alt={announcement.title} />
                        </a>
                    </div>
                )}

                <div className="mayo-single-announcement-body">
                    <div dangerouslySetInnerHTML={{ __html: announcement.content }} />
                </div>

                {announcement.linked_events && announcement.linked_events.length > 0 && (
                    <div className="mayo-single-announcement-events">
                        <h3>
                            <span className="dashicons dashicons-admin-links"></span>
                            Related Links & Events
                        </h3>
                        <ul>
                            {announcement.linked_events.map((event) => {
                                const isCustom = event.source && event.source.type === 'custom';
                                const isExternal = event.source && event.source.type === 'external';
                                const isUnavailable = event.unavailable;

                                // Custom links and external links open in new tab
                                const opensInNewTab = isCustom || isExternal;

                                return (
                                    <li key={`${event.source?.type || 'local'}-${event.source?.id || 'local'}-${event.id}`}>
                                        {isUnavailable ? (
                                            <span className="mayo-event-unavailable">
                                                {event.title}
                                                {isExternal && event.source?.name && (
                                                    <span className="mayo-event-source">({event.source.name})</span>
                                                )}
                                            </span>
                                        ) : (
                                            <a
                                                href={event.permalink}
                                                target={opensInNewTab ? '_blank' : '_self'}
                                                rel={opensInNewTab ? 'noopener noreferrer' : undefined}
                                            >
                                                {isCustom && event.icon && (
                                                    <span className={`dashicons ${getIconClass(event.icon)} mayo-custom-link-icon`}></span>
                                                )}
                                                {!isCustom && (
                                                    <span className="dashicons dashicons-calendar-alt mayo-event-icon"></span>
                                                )}
                                                <span className="mayo-event-title">{event.title}</span>
                                                {event.start_date && !isCustom && (
                                                    <span className="mayo-event-date">{event.start_date}</span>
                                                )}
                                                {isCustom && (
                                                    <span className="mayo-custom-link-badge">Link</span>
                                                )}
                                                {isExternal && event.source?.name && (
                                                    <span className="mayo-event-source-badge">{event.source.name}</span>
                                                )}
                                            </a>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                )}

                <div className="mayo-single-announcement-meta">
                    {announcement.service_body && (
                        <div className="mayo-announcement-service-body">
                            <h3>Service Body</h3>
                            <p>{getServiceBodyName(announcement.service_body)}</p>
                        </div>
                    )}

                    {(announcement.display_start_date || announcement.display_end_date) && (
                        <div className="mayo-announcement-display-window">
                            <h3>Display Window</h3>
                            {announcement.display_start_date && (
                                <p><strong>From:</strong> {formatDateTime(announcement.display_start_date, announcement.display_start_time)}</p>
                            )}
                            {announcement.display_end_date && (
                                <p><strong>Until:</strong> {formatDateTime(announcement.display_end_date, announcement.display_end_time)}</p>
                            )}
                        </div>
                    )}
                </div>

                <div className="mayo-single-announcement-taxonomies">
                    {announcement.categories?.length > 0 && (
                        <div className="mayo-single-announcement-categories">
                            <h3>Categories</h3>
                            {announcement.categories.map(cat => (
                                <a key={cat.id} href={cat.link}>{cat.name}</a>
                            ))}
                        </div>
                    )}

                    {announcement.tags?.length > 0 && (
                        <div className="mayo-single-announcement-tags">
                            <h3>Tags</h3>
                            {announcement.tags.map(tag => (
                                <a key={tag.id} href={tag.link}>{tag.name}</a>
                            ))}
                        </div>
                    )}
                </div>
            </article>
        </div>
    );
};

export default AnnouncementDetails;
