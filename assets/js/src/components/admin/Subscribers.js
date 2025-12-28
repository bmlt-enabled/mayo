import { useState, useEffect, useMemo, Fragment } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { apiFetch } from '../../util';

const Subscribers = () => {
    const [subscribers, setSubscribers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [statusFilter, setStatusFilter] = useState('all');
    const [searchTerm, setSearchTerm] = useState('');
    const [expandedId, setExpandedId] = useState(null);

    useEffect(() => {
        const fetchSubscribers = async () => {
            try {
                const data = await apiFetch('/subscribers');
                setSubscribers(data);
            } catch (err) {
                setError(err.message || 'Failed to load subscribers');
            } finally {
                setLoading(false);
            }
        };
        fetchSubscribers();
    }, []);

    // Filter and search
    const filteredSubscribers = useMemo(() => {
        return subscribers.filter(sub => {
            if (statusFilter !== 'all' && sub.status !== statusFilter) {
                return false;
            }
            if (searchTerm && !sub.email.toLowerCase().includes(searchTerm.toLowerCase())) {
                return false;
            }
            return true;
        });
    }, [subscribers, statusFilter, searchTerm]);

    // Count by status
    const counts = useMemo(() => {
        const c = { all: subscribers.length, active: 0, pending: 0, unsubscribed: 0 };
        subscribers.forEach(s => {
            if (c[s.status] !== undefined) {
                c[s.status]++;
            }
        });
        return c;
    }, [subscribers]);

    const formatDate = (dateStr) => {
        if (!dateStr) return '—';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    };

    const getPreferencesDisplay = (prefs) => {
        if (!prefs) return null;
        const parts = [];
        if (prefs.categories?.length > 0) {
            parts.push(`Categories: ${prefs.categories.join(', ')}`);
        }
        if (prefs.tags?.length > 0) {
            parts.push(`Tags: ${prefs.tags.join(', ')}`);
        }
        if (prefs.service_bodies?.length > 0) {
            parts.push(`Service Bodies: ${prefs.service_bodies.join(', ')}`);
        }
        return parts;
    };

    const getPreferencesCount = (prefs) => {
        if (!prefs) return 0;
        return (prefs.categories?.length || 0) +
               (prefs.tags?.length || 0) +
               (prefs.service_bodies?.length || 0);
    };

    const toggleExpand = (id) => {
        setExpandedId(expandedId === id ? null : id);
    };

    if (loading) {
        return (
            <div className="wrap">
                <h1 className="wp-heading-inline">Subscribers</h1>
                <hr className="wp-header-end" />
                <div style={{ padding: '20px', textAlign: 'center' }}>
                    <Spinner />
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="wrap">
                <h1 className="wp-heading-inline">Subscribers</h1>
                <hr className="wp-header-end" />
                <div className="notice notice-error">
                    <p>{error}</p>
                </div>
            </div>
        );
    }

    return (
        <div className="wrap">
            <h1 className="wp-heading-inline">Subscribers</h1>
            <hr className="wp-header-end" />

            {/* WordPress-style subsubsub filter links */}
            <ul className="subsubsub">
                <li className="all">
                    <a
                        href="#"
                        className={statusFilter === 'all' ? 'current' : ''}
                        onClick={(e) => { e.preventDefault(); setStatusFilter('all'); }}
                    >
                        All <span className="count">({counts.all})</span>
                    </a> |
                </li>
                <li className="active">
                    <a
                        href="#"
                        className={statusFilter === 'active' ? 'current' : ''}
                        onClick={(e) => { e.preventDefault(); setStatusFilter('active'); }}
                    >
                        Active <span className="count">({counts.active})</span>
                    </a> |
                </li>
                <li className="pending">
                    <a
                        href="#"
                        className={statusFilter === 'pending' ? 'current' : ''}
                        onClick={(e) => { e.preventDefault(); setStatusFilter('pending'); }}
                    >
                        Pending <span className="count">({counts.pending})</span>
                    </a> |
                </li>
                <li className="unsubscribed">
                    <a
                        href="#"
                        className={statusFilter === 'unsubscribed' ? 'current' : ''}
                        onClick={(e) => { e.preventDefault(); setStatusFilter('unsubscribed'); }}
                    >
                        Unsubscribed <span className="count">({counts.unsubscribed})</span>
                    </a>
                </li>
            </ul>

            {/* WordPress-style search box */}
            <p className="search-box">
                <label className="screen-reader-text" htmlFor="subscriber-search-input">Search Subscribers:</label>
                <input
                    type="search"
                    id="subscriber-search-input"
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    placeholder="Search by email..."
                />
            </p>

            {/* Clear float */}
            <div style={{ clear: 'both' }}></div>

            {/* WordPress-style table */}
            <table className="wp-list-table widefat fixed striped table-view-list">
                <thead>
                    <tr>
                        <th scope="col" className="manage-column column-email column-primary">Email</th>
                        <th scope="col" className="manage-column column-status">Status</th>
                        <th scope="col" className="manage-column column-date">Subscribed</th>
                        <th scope="col" className="manage-column column-date">Confirmed</th>
                        <th scope="col" className="manage-column column-preferences">Preferences</th>
                    </tr>
                </thead>
                <tbody id="the-list">
                    {filteredSubscribers.length === 0 ? (
                        <tr className="no-items">
                            <td className="colspanchange" colSpan="5">No subscribers found.</td>
                        </tr>
                    ) : (
                        filteredSubscribers.map(sub => (
                            <Fragment key={sub.id}>
                                <tr>
                                    <td className="email column-email column-primary" data-colname="Email">
                                        <strong>{sub.email}</strong>
                                    </td>
                                    <td className="status column-status" data-colname="Status">
                                        <span className={`mayo-subscriber-status mayo-status-${sub.status}`}>
                                            {sub.status.charAt(0).toUpperCase() + sub.status.slice(1)}
                                        </span>
                                    </td>
                                    <td className="date column-date" data-colname="Subscribed">
                                        {formatDate(sub.created_at)}
                                    </td>
                                    <td className="date column-date" data-colname="Confirmed">
                                        {formatDate(sub.confirmed_at)}
                                    </td>
                                    <td className="preferences column-preferences" data-colname="Preferences">
                                        {getPreferencesCount(sub.preferences) > 0 ? (
                                            <button
                                                type="button"
                                                className="button-link"
                                                onClick={() => toggleExpand(sub.id)}
                                                style={{
                                                    cursor: 'pointer',
                                                    color: '#2271b1',
                                                    textDecoration: 'none'
                                                }}
                                            >
                                                {getPreferencesCount(sub.preferences)} preference{getPreferencesCount(sub.preferences) !== 1 ? 's' : ''}
                                                <span
                                                    className="dashicons"
                                                    style={{
                                                        fontSize: '14px',
                                                        width: '14px',
                                                        height: '14px',
                                                        verticalAlign: 'middle',
                                                        marginLeft: '2px'
                                                    }}
                                                >
                                                    {expandedId === sub.id ? '▲' : '▼'}
                                                </span>
                                            </button>
                                        ) : (
                                            <span style={{ color: '#646970' }}>—</span>
                                        )}
                                    </td>
                                </tr>
                                {expandedId === sub.id && sub.preferences && (
                                    <tr className="mayo-prefs-expanded-row" style={{ background: '#f6f7f7' }}>
                                        <td colSpan="4"></td>
                                        <td style={{ padding: '12px 10px 12px 16px', borderLeft: '4px solid #2271b1' }}>
                                            {sub.preferences.categories?.length > 0 && (
                                                <div style={{ marginBottom: '8px' }}>
                                                    <strong style={{ fontSize: '12px', color: '#646970' }}>Categories:</strong>
                                                    <span style={{ fontSize: '13px', marginLeft: '4px' }}>
                                                        {sub.preferences.categories.join(', ')}
                                                    </span>
                                                </div>
                                            )}
                                            {sub.preferences.tags?.length > 0 && (
                                                <div style={{ marginBottom: '8px' }}>
                                                    <strong style={{ fontSize: '12px', color: '#646970' }}>Tags:</strong>
                                                    <span style={{ fontSize: '13px', marginLeft: '4px' }}>
                                                        {sub.preferences.tags.join(', ')}
                                                    </span>
                                                </div>
                                            )}
                                            {sub.preferences.service_bodies?.length > 0 && (
                                                <div>
                                                    <strong style={{ fontSize: '12px', color: '#646970' }}>Service Bodies:</strong>
                                                    <span style={{ fontSize: '13px', marginLeft: '4px' }}>
                                                        {sub.preferences.service_bodies.join(', ')}
                                                    </span>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                )}
                            </Fragment>
                        ))
                    )}
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="col" className="manage-column column-email column-primary">Email</th>
                        <th scope="col" className="manage-column column-status">Status</th>
                        <th scope="col" className="manage-column column-date">Subscribed</th>
                        <th scope="col" className="manage-column column-date">Confirmed</th>
                        <th scope="col" className="manage-column column-preferences">Preferences</th>
                    </tr>
                </tfoot>
            </table>

            {/* Item count */}
            <div className="tablenav bottom">
                <div className="tablenav-pages one-page">
                    <span className="displaying-num">{filteredSubscribers.length} item{filteredSubscribers.length !== 1 ? 's' : ''}</span>
                </div>
            </div>
        </div>
    );
};

export default Subscribers;
