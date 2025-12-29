import { useState, useEffect, useMemo, Fragment } from '@wordpress/element';
import { Spinner, Modal, SelectControl, CheckboxControl, Button } from '@wordpress/components';
import { apiFetch } from '../../util';

// Helper to check if a value is in an array (handles type coercion)
const includesValue = (arr, value) => {
    if (!arr || !Array.isArray(arr)) return false;
    return arr.some(item => String(item) === String(value));
};

// Modal component for editing subscriber
const SubscriberEditModal = ({ subscriber, options, onSave, onClose, saving }) => {
    const [status, setStatus] = useState(subscriber.status);
    const [preferences, setPreferences] = useState(() => {
        const prefs = subscriber.preferences || {};
        return {
            categories: (prefs.categories || []).map(c => Number(c)),
            tags: (prefs.tags || []).map(t => Number(t)),
            service_bodies: (prefs.service_bodies || []).map(s => String(s))
        };
    });

    const togglePreference = (type, value, checked) => {
        setPreferences(prev => {
            const current = prev[type] || [];
            if (checked) {
                return { ...prev, [type]: [...current, value] };
            } else {
                return { ...prev, [type]: current.filter(v => v !== value) };
            }
        });
    };

    const handleSave = () => {
        onSave(subscriber.id, { status, preferences });
    };

    // Check if a category is selected
    const isCategorySelected = (catId) => {
        return preferences.categories.some(c => Number(c) === Number(catId));
    };

    // Check if a tag is selected
    const isTagSelected = (tagId) => {
        return preferences.tags.some(t => Number(t) === Number(tagId));
    };

    // Check if a service body is selected
    const isServiceBodySelected = (sbId) => {
        return preferences.service_bodies.some(s => String(s) === String(sbId));
    };

    return (
        <Modal
            title={`Edit Subscriber: ${subscriber.email}`}
            onRequestClose={onClose}
            className="mayo-subscriber-edit-modal"
        >
            <div style={{ minWidth: '400px' }}>
                <SelectControl
                    label="Status"
                    value={status}
                    options={[
                        { label: 'Active', value: 'active' },
                        { label: 'Pending', value: 'pending' },
                        { label: 'Unsubscribed', value: 'unsubscribed' }
                    ]}
                    onChange={setStatus}
                />

                <div style={{ marginTop: '16px' }}>
                    <h4 style={{ marginBottom: '8px' }}>Subscription Preferences</h4>
                    <p className="description" style={{ marginBottom: '12px' }}>
                        Select which categories, tags, and service bodies this subscriber should receive notifications for.
                        If none are selected, they will receive all announcements.
                    </p>

                    {options?.categories?.length > 0 && (
                        <div style={{ marginBottom: '16px' }}>
                            <strong style={{ display: 'block', marginBottom: '8px' }}>Categories</strong>
                            {options.categories.map(cat => (
                                <CheckboxControl
                                    key={cat.id}
                                    label={cat.name}
                                    checked={isCategorySelected(cat.id)}
                                    onChange={(checked) => togglePreference('categories', Number(cat.id), checked)}
                                />
                            ))}
                        </div>
                    )}

                    {options?.tags?.length > 0 && (
                        <div style={{ marginBottom: '16px' }}>
                            <strong style={{ display: 'block', marginBottom: '8px' }}>Tags</strong>
                            {options.tags.map(tag => (
                                <CheckboxControl
                                    key={tag.id}
                                    label={tag.name}
                                    checked={isTagSelected(tag.id)}
                                    onChange={(checked) => togglePreference('tags', Number(tag.id), checked)}
                                />
                            ))}
                        </div>
                    )}

                    {options?.service_bodies?.length > 0 && (
                        <div style={{ marginBottom: '16px' }}>
                            <strong style={{ display: 'block', marginBottom: '8px' }}>Service Bodies</strong>
                            {options.service_bodies.map(sb => (
                                <CheckboxControl
                                    key={sb.id}
                                    label={sb.name}
                                    checked={isServiceBodySelected(sb.id)}
                                    onChange={(checked) => togglePreference('service_bodies', String(sb.id), checked)}
                                />
                            ))}
                        </div>
                    )}

                    {(!options?.categories?.length && !options?.tags?.length && !options?.service_bodies?.length) && (
                        <p style={{ color: '#646970', fontStyle: 'italic' }}>
                            No subscription options configured. Configure them in the Settings page.
                        </p>
                    )}
                </div>

                <div style={{ marginTop: '20px', display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
                    <Button isSecondary onClick={onClose} disabled={saving}>
                        Cancel
                    </Button>
                    <Button isPrimary onClick={handleSave} disabled={saving}>
                        {saving ? 'Saving...' : 'Save Changes'}
                    </Button>
                </div>
            </div>
        </Modal>
    );
};

const Subscribers = () => {
    const [subscribers, setSubscribers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [statusFilter, setStatusFilter] = useState('all');
    const [searchTerm, setSearchTerm] = useState('');
    const [editingSubscriber, setEditingSubscriber] = useState(null);
    const [subscriptionOptions, setSubscriptionOptions] = useState(null);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        const fetchData = async () => {
            try {
                const [subscribersData, optionsData] = await Promise.all([
                    apiFetch('/subscribers'),
                    apiFetch('/subscription-options')
                ]);
                setSubscribers(subscribersData);
                setSubscriptionOptions(optionsData);
            } catch (err) {
                setError(err.message || 'Failed to load subscribers');
            } finally {
                setLoading(false);
            }
        };
        fetchData();
    }, []);

    const handleSave = async (id, data) => {
        setSaving(true);
        try {
            await apiFetch(`/subscribers/${id}`, {
                method: 'PUT',
                body: JSON.stringify(data)
            });
            // Refresh subscriber list
            const updated = await apiFetch('/subscribers');
            setSubscribers(updated);
            setEditingSubscriber(null);
        } catch (err) {
            alert('Failed to update subscriber: ' + (err.message || 'Unknown error'));
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (e, subscriber) => {
        e.preventDefault();
        if (!window.confirm(`Are you sure you want to delete subscriber "${subscriber.email}"? This action cannot be undone.`)) {
            return;
        }
        try {
            await apiFetch(`/subscribers/${subscriber.id}`, { method: 'DELETE' });
            setSubscribers(subscribers.filter(s => s.id !== subscriber.id));
        } catch (err) {
            alert('Failed to delete subscriber: ' + (err.message || 'Unknown error'));
        }
    };

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

    const getPreferencesCount = (prefs) => {
        if (!prefs) return 0;
        return (prefs.categories?.length || 0) +
               (prefs.tags?.length || 0) +
               (prefs.service_bodies?.length || 0);
    };

    // Get name for a category ID
    const getCategoryName = (catId) => {
        const cat = subscriptionOptions?.categories?.find(c => String(c.id) === String(catId));
        return cat?.name || `Category ${catId}`;
    };

    // Get name for a tag ID
    const getTagName = (tagId) => {
        const tag = subscriptionOptions?.tags?.find(t => String(t.id) === String(tagId));
        return tag?.name || `Tag ${tagId}`;
    };

    // Get name for a service body ID
    const getServiceBodyName = (sbId) => {
        const sb = subscriptionOptions?.service_bodies?.find(s => String(s.id) === String(sbId));
        return sb?.name || `Service Body ${sbId}`;
    };

    // Badge styles matching the email recipients modal
    const badgeStyles = {
        category: {
            display: 'inline-block',
            padding: '3px 8px',
            fontSize: '11px',
            backgroundColor: '#e3f2fd',
            color: '#1565c0',
            borderRadius: '3px',
            marginRight: '4px',
            marginBottom: '4px',
        },
        tag: {
            display: 'inline-block',
            padding: '3px 8px',
            fontSize: '11px',
            backgroundColor: '#fff3e0',
            color: '#e65100',
            borderRadius: '3px',
            marginRight: '4px',
            marginBottom: '4px',
        },
        serviceBody: {
            display: 'inline-block',
            padding: '3px 8px',
            fontSize: '11px',
            backgroundColor: '#e8f5e9',
            color: '#2e7d32',
            borderRadius: '3px',
            marginRight: '4px',
            marginBottom: '4px',
        },
        all: {
            display: 'inline-block',
            padding: '3px 8px',
            fontSize: '11px',
            backgroundColor: '#f0f0f0',
            color: '#666',
            borderRadius: '3px',
        },
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
                                        <div className="row-actions visible" style={{ visibility: 'visible' }}>
                                            <span className="edit">
                                                <button
                                                    type="button"
                                                    className="button button-small button-primary"
                                                    onClick={() => setEditingSubscriber(sub)}
                                                    style={{ marginRight: '4px' }}
                                                >
                                                    Edit
                                                </button>
                                            </span>
                                            <span className="delete">
                                                <button
                                                    type="button"
                                                    className="button button-small button-link-delete"
                                                    onClick={(e) => handleDelete(e, sub)}
                                                    style={{ color: '#b32d2e' }}
                                                >
                                                    Delete
                                                </button>
                                            </span>
                                        </div>
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
                                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '2px' }}>
                                            {getPreferencesCount(sub.preferences) > 0 ? (
                                                <>
                                                    {sub.preferences.categories?.map((catId, idx) => (
                                                        <span key={`cat-${catId}`} style={badgeStyles.category}>
                                                            {sub.preferences_display?.categories?.[idx] || getCategoryName(catId)}
                                                        </span>
                                                    ))}
                                                    {sub.preferences.tags?.map((tagId, idx) => (
                                                        <span key={`tag-${tagId}`} style={badgeStyles.tag}>
                                                            {sub.preferences_display?.tags?.[idx] || getTagName(tagId)}
                                                        </span>
                                                    ))}
                                                    {sub.preferences.service_bodies?.map((sbId, idx) => (
                                                        <span key={`sb-${sbId}`} style={badgeStyles.serviceBody}>
                                                            {sub.preferences_display?.service_bodies?.[idx] || getServiceBodyName(sbId)}
                                                        </span>
                                                    ))}
                                                </>
                                            ) : (
                                                <span style={badgeStyles.all}>All announcements</span>
                                            )}
                                        </div>
                                    </td>
                                </tr>
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

            {/* Edit Modal */}
            {editingSubscriber && (
                <SubscriberEditModal
                    subscriber={editingSubscriber}
                    options={subscriptionOptions}
                    onSave={handleSave}
                    onClose={() => setEditingSubscriber(null)}
                    saving={saving}
                />
            )}
        </div>
    );
};

export default Subscribers;
