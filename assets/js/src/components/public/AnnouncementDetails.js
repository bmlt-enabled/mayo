import { useState, useEffect } from '@wordpress/element';
import { apiFetch } from '../../util';

const AnnouncementDetails = () => {
    const [announcement, setAnnouncement] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

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

    if (loading) return <div>Loading announcement...</div>;
    if (error) return <div className="mayo-error">{error}</div>;
    if (!announcement) return <div>Announcement not found</div>;

    const priorityColors = {
        urgent: '#dc3545',
        high: '#ff9800',
        normal: '#0073aa',
        low: '#6c757d'
    };

    const getPriorityBadge = (priority) => {
        if (!priority || priority === 'normal') return null;
        return (
            <span
                style={{
                    backgroundColor: priorityColors[priority] || priorityColors.normal,
                    color: '#fff',
                    padding: '4px 10px',
                    borderRadius: '4px',
                    fontSize: '12px',
                    textTransform: 'uppercase',
                    fontWeight: 'bold',
                    marginLeft: '12px',
                }}
            >
                {priority}
            </span>
        );
    };

    return (
        <div className="mayo-single-container">
            <article className="mayo-single-announcement">
                <div className="mayo-single-announcement-content">
                    <header className="mayo-single-announcement-header">
                        <h1 className="mayo-single-announcement-title">
                            <span className="dashicons dashicons-megaphone" style={{ marginRight: '10px', color: priorityColors[announcement.priority] || '#0073aa' }}></span>
                            <span dangerouslySetInnerHTML={{ __html: announcement.title }} />
                            {getPriorityBadge(announcement.priority)}
                        </h1>
                    </header>

                    {announcement.featured_image && (
                        <div className="mayo-single-announcement-image" style={{ marginBottom: '20px' }}>
                            <img src={announcement.featured_image} alt={announcement.title} style={{ maxWidth: '100%', height: 'auto' }} />
                        </div>
                    )}

                    <div className="mayo-single-announcement-body">
                        <div dangerouslySetInnerHTML={{ __html: announcement.content }} />
                    </div>

                    {announcement.linked_events && announcement.linked_events.length > 0 && (
                        <div className="mayo-single-announcement-events" style={{ marginTop: '30px', padding: '20px', backgroundColor: '#f5f5f5', borderRadius: '8px' }}>
                            <h3 style={{ marginTop: 0, display: 'flex', alignItems: 'center', gap: '8px' }}>
                                <span className="dashicons dashicons-calendar-alt"></span>
                                Related Events
                            </h3>
                            <ul style={{ listStyle: 'none', padding: 0, margin: 0 }}>
                                {announcement.linked_events.map((event) => {
                                    const isExternal = event.source && event.source.type === 'external';
                                    const isUnavailable = event.unavailable;
                                    return (
                                        <li
                                            key={`${event.source?.type || 'local'}-${event.source?.id || 'local'}-${event.id}`}
                                            style={{
                                                padding: '10px 0',
                                                borderBottom: '1px solid #ddd',
                                            }}
                                        >
                                            {isUnavailable ? (
                                                <span style={{ color: '#999', fontStyle: 'italic' }}>
                                                    {event.title}
                                                    {isExternal && event.source?.name && (
                                                        <span style={{ marginLeft: '8px', fontSize: '12px' }}>
                                                            ({event.source.name})
                                                        </span>
                                                    )}
                                                </span>
                                            ) : (
                                                <a
                                                    href={event.permalink}
                                                    target={isExternal ? '_blank' : '_self'}
                                                    rel={isExternal ? 'noopener noreferrer' : undefined}
                                                    style={{ color: '#0073aa', textDecoration: 'none', fontSize: '16px' }}
                                                >
                                                    {event.title}
                                                    {event.start_date && (
                                                        <span style={{ marginLeft: '10px', color: '#666', fontSize: '14px' }}>
                                                            ({event.start_date})
                                                        </span>
                                                    )}
                                                    {isExternal && event.source?.name && (
                                                        <span style={{
                                                            marginLeft: '10px',
                                                            fontSize: '11px',
                                                            backgroundColor: '#e0e0e0',
                                                            padding: '2px 6px',
                                                            borderRadius: '3px',
                                                            color: '#666',
                                                        }}>
                                                            {event.source.name}
                                                        </span>
                                                    )}
                                                </a>
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    )}

                    <div className="mayo-single-announcement-meta" style={{ marginTop: '30px', padding: '15px', backgroundColor: '#f9f9f9', borderRadius: '4px', fontSize: '14px', color: '#666' }}>
                        {announcement.display_start_date && (
                            <p style={{ margin: '5px 0' }}>
                                <strong>Display from:</strong> {announcement.display_start_date}
                            </p>
                        )}
                        {announcement.display_end_date && (
                            <p style={{ margin: '5px 0' }}>
                                <strong>Display until:</strong> {announcement.display_end_date}
                            </p>
                        )}
                    </div>
                </div>
            </article>
        </div>
    );
};

export default AnnouncementDetails;
