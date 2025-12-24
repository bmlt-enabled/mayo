import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import { useSelect, useDispatch } from '@wordpress/data';
import {
    TextControl,
    SelectControl,
    PanelBody,
    Button,
    Spinner,
    __experimentalInputControl as InputControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { apiFetch as customApiFetch } from '../../util';

const AnnouncementEditor = () => {
    const [searchTerm, setSearchTerm] = useState('');
    const [searchResults, setSearchResults] = useState([]);
    const [isSearching, setIsSearching] = useState(false);
    const [hasPrelinked, setHasPrelinked] = useState(false);

    const postType = useSelect(select =>
        select('core/editor').getCurrentPostType()
    );

    const postStatus = useSelect(select =>
        select('core/editor').getEditedPostAttribute('status')
    );

    const meta = useSelect(select =>
        select('core/editor').getEditedPostAttribute('meta') || {}
    );

    const { editPost } = useDispatch('core/editor');

    // Handle pre-linking from URL parameter (when coming from event editor)
    useEffect(() => {
        if (hasPrelinked || postType !== 'mayo_announcement' || postStatus !== 'auto-draft') return;

        const urlParams = new URLSearchParams(window.location.search);
        const linkedEventId = urlParams.get('linked_event');

        if (linkedEventId) {
            const eventId = parseInt(linkedEventId, 10);
            if (!isNaN(eventId) && eventId > 0) {
                const currentLinked = meta.linked_events || [];
                if (!currentLinked.includes(eventId)) {
                    editPost({ meta: { ...meta, linked_events: [...currentLinked, eventId] } });
                }
            }
            setHasPrelinked(true);
        }
    }, [postType, postStatus, hasPrelinked, meta.linked_events]);

    if (postType !== 'mayo_announcement') return null;

    const updateMetaValue = (key, value) => {
        editPost({ meta: { ...meta, [key]: value } });
    };

    const linkedEvents = meta.linked_events || [];

    // Debounced search for events
    useEffect(() => {
        if (!searchTerm || searchTerm.length < 2) {
            setSearchResults([]);
            return;
        }

        const timer = setTimeout(async () => {
            setIsSearching(true);
            try {
                const response = await apiFetch({
                    path: `/event-manager/v1/events/search?search=${encodeURIComponent(searchTerm)}&limit=10`,
                });
                // Filter out already linked events using current linkedEvents value
                const currentLinked = meta.linked_events || [];
                const filtered = (response.events || []).filter(
                    event => !currentLinked.includes(event.id)
                );
                setSearchResults(filtered);
            } catch (error) {
                console.error('Error searching events:', error);
                setSearchResults([]);
            }
            setIsSearching(false);
        }, 300);

        return () => clearTimeout(timer);
    }, [searchTerm, meta.linked_events]);

    const addLinkedEvent = (eventId) => {
        if (!linkedEvents.includes(eventId)) {
            updateMetaValue('linked_events', [...linkedEvents, eventId]);
        }
        setSearchTerm('');
        setSearchResults([]);
    };

    const removeLinkedEvent = (eventId) => {
        updateMetaValue('linked_events', linkedEvents.filter(id => id !== eventId));
    };

    // Fetch linked event details
    const [linkedEventDetails, setLinkedEventDetails] = useState({});
    const [isLoadingEventDetails, setIsLoadingEventDetails] = useState(false);

    useEffect(() => {
        const fetchEventDetails = async () => {
            // Find events we don't have details for yet
            const eventsToFetch = linkedEvents.filter(id => !linkedEventDetails[id]);
            if (eventsToFetch.length === 0) return;

            setIsLoadingEventDetails(true);
            const details = {};

            for (const eventId of eventsToFetch) {
                try {
                    // Use our custom event endpoint to get event by ID
                    const event = await customApiFetch(`/events/${eventId}`);

                    if (event && !event.code) {
                        details[eventId] = {
                            title: event.title || 'Unknown Event',
                            start_date: event.start_date || '',
                            permalink: event.permalink || '',
                            edit_link: event.edit_link || '',
                        };
                    } else {
                        details[eventId] = { title: `Event #${eventId}`, start_date: '', permalink: '', edit_link: '' };
                    }
                } catch (error) {
                    console.error(`Error fetching event ${eventId}:`, error);
                    details[eventId] = { title: `Event #${eventId}`, start_date: '', permalink: '', edit_link: '' };
                }
            }

            if (Object.keys(details).length > 0) {
                setLinkedEventDetails(prev => ({ ...prev, ...details }));
            }
            setIsLoadingEventDetails(false);
        };

        if (linkedEvents.length > 0) {
            fetchEventDetails();
        }
    }, [linkedEvents]);

    const formatDate = (dateString) => {
        if (!dateString) return '';
        try {
            const [year, month, day] = dateString.split('-').map(Number);
            const date = new Date(year, month - 1, day);
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        } catch (e) {
            return dateString;
        }
    };

    const priorityColors = {
        low: '#6c757d',
        normal: '#0073aa',
        high: '#ff9800',
        urgent: '#dc3545'
    };

    return (
        <>
            <PluginDocumentSettingPanel
                name="mayo-announcement-details"
                title="Announcement Settings"
                className="mayo-announcement-details"
            >
                <PanelBody title="Display Window" initialOpen={true}>
                    <p className="components-base-control__help" style={{ marginTop: 0 }}>
                        Control when this announcement is visible on the frontend.
                    </p>
                    <TextControl
                        label="Start Date"
                        type="date"
                        value={meta.display_start_date || ''}
                        onChange={value => updateMetaValue('display_start_date', value)}
                        help="Leave empty to start showing immediately"
                        __nextHasNoMarginBottom={true}
                    />
                    <TextControl
                        label="End Date"
                        type="date"
                        value={meta.display_end_date || ''}
                        onChange={value => updateMetaValue('display_end_date', value)}
                        help="Leave empty to show indefinitely"
                        __nextHasNoMarginBottom={true}
                    />
                </PanelBody>

                <PanelBody title="Priority" initialOpen={true}>
                    <SelectControl
                        label="Priority Level"
                        value={meta.priority || 'normal'}
                        options={[
                            { label: 'Low', value: 'low' },
                            { label: 'Normal', value: 'normal' },
                            { label: 'High', value: 'high' },
                            { label: 'Urgent', value: 'urgent' },
                        ]}
                        onChange={value => updateMetaValue('priority', value)}
                        __nextHasNoMarginBottom={true}
                    />
                    <p className="components-base-control__help">
                        Priority affects display order and styling.
                        <span style={{
                            display: 'inline-block',
                            marginLeft: '8px',
                            color: priorityColors[meta.priority || 'normal'],
                            fontWeight: 600
                        }}>
                            {(meta.priority || 'normal').charAt(0).toUpperCase() + (meta.priority || 'normal').slice(1)}
                        </span>
                    </p>
                </PanelBody>
            </PluginDocumentSettingPanel>

            <PluginDocumentSettingPanel
                name="mayo-linked-events"
                title="Linked Events"
                className="mayo-linked-events"
            >
                <p className="components-base-control__help" style={{ marginTop: 0 }}>
                    Optionally link this announcement to one or more events.
                </p>

                {/* Linked Events List */}
                {isLoadingEventDetails && linkedEvents.length > 0 && (
                    <div style={{ textAlign: 'center', padding: '16px' }}>
                        <Spinner />
                        <p style={{ margin: '8px 0 0', color: '#666', fontSize: '12px' }}>Loading event details...</p>
                    </div>
                )}

                {!isLoadingEventDetails && linkedEvents.length > 0 && (
                    <div className="mayo-linked-events-list" style={{ marginBottom: '16px' }}>
                        {linkedEvents.map(eventId => {
                            const details = linkedEventDetails[eventId] || {};
                            const hasDetails = details.title && details.title !== `Event #${eventId}`;

                            return (
                                <div
                                    key={eventId}
                                    style={{
                                        padding: '12px',
                                        backgroundColor: '#f9f9f9',
                                        borderRadius: '4px',
                                        marginBottom: '8px',
                                        borderLeft: '3px solid #0073aa',
                                    }}
                                >
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                                        <div style={{ flex: 1 }}>
                                            {hasDetails ? (
                                                <>
                                                    <strong style={{ display: 'block', marginBottom: '4px' }}>
                                                        {details.title}
                                                    </strong>
                                                    {details.start_date && (
                                                        <div style={{ color: '#666', fontSize: '12px', marginBottom: '8px' }}>
                                                            <span className="dashicons dashicons-calendar-alt" style={{ fontSize: '14px', marginRight: '4px', verticalAlign: 'middle' }}></span>
                                                            {formatDate(details.start_date)}
                                                        </div>
                                                    )}
                                                    <div style={{ display: 'flex', gap: '8px', marginTop: '8px' }}>
                                                        {details.permalink && (
                                                            <a
                                                                href={details.permalink}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                style={{
                                                                    display: 'inline-flex',
                                                                    alignItems: 'center',
                                                                    padding: '4px 8px',
                                                                    fontSize: '11px',
                                                                    backgroundColor: '#f0f0f0',
                                                                    border: '1px solid #c3c4c7',
                                                                    borderRadius: '3px',
                                                                    textDecoration: 'none',
                                                                    color: '#2271b1',
                                                                    whiteSpace: 'nowrap',
                                                                }}
                                                            >
                                                                <span className="dashicons dashicons-external" style={{ fontSize: '14px', marginRight: '4px', width: '14px', height: '14px' }}></span>
                                                                View
                                                            </a>
                                                        )}
                                                        {details.edit_link && (
                                                            <a
                                                                href={details.edit_link}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                style={{
                                                                    display: 'inline-flex',
                                                                    alignItems: 'center',
                                                                    padding: '4px 8px',
                                                                    fontSize: '11px',
                                                                    backgroundColor: '#f0f0f0',
                                                                    border: '1px solid #c3c4c7',
                                                                    borderRadius: '3px',
                                                                    textDecoration: 'none',
                                                                    color: '#2271b1',
                                                                    whiteSpace: 'nowrap',
                                                                }}
                                                            >
                                                                <span className="dashicons dashicons-edit" style={{ fontSize: '14px', marginRight: '4px', width: '14px', height: '14px' }}></span>
                                                                Edit
                                                            </a>
                                                        )}
                                                    </div>
                                                </>
                                            ) : (
                                                <span style={{ color: '#666' }}>
                                                    Event #{eventId}
                                                    <span style={{ fontSize: '12px', marginLeft: '8px' }}>(details not available)</span>
                                                </span>
                                            )}
                                        </div>
                                        <Button
                                            isSmall
                                            isDestructive
                                            onClick={() => removeLinkedEvent(eventId)}
                                            style={{ marginLeft: '12px' }}
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* Search Input */}
                <TextControl
                    label="Search Events"
                    value={searchTerm}
                    onChange={setSearchTerm}
                    placeholder="Type to search events..."
                    __nextHasNoMarginBottom={true}
                />

                {/* Search Results */}
                {isSearching && (
                    <div style={{ textAlign: 'center', padding: '8px' }}>
                        <Spinner />
                    </div>
                )}

                {!isSearching && searchResults.length > 0 && (
                    <div
                        className="mayo-event-search-results"
                        style={{
                            maxHeight: '200px',
                            overflowY: 'auto',
                            border: '1px solid #ddd',
                            borderRadius: '4px',
                            marginTop: '8px',
                        }}
                    >
                        {searchResults.map(event => (
                            <div
                                key={event.id}
                                style={{
                                    padding: '8px 12px',
                                    borderBottom: '1px solid #eee',
                                    cursor: 'pointer',
                                    transition: 'background-color 0.2s',
                                }}
                                onClick={() => addLinkedEvent(event.id)}
                                onMouseEnter={e => e.target.style.backgroundColor = '#f5f5f5'}
                                onMouseLeave={e => e.target.style.backgroundColor = 'transparent'}
                            >
                                <strong>{event.title}</strong>
                                {event.start_date && (
                                    <span style={{ color: '#666', marginLeft: '8px', fontSize: '12px' }}>
                                        {formatDate(event.start_date)}
                                    </span>
                                )}
                            </div>
                        ))}
                    </div>
                )}

                {!isSearching && searchTerm.length >= 2 && searchResults.length === 0 && (
                    <p style={{ color: '#666', fontSize: '12px', marginTop: '8px' }}>
                        No events found matching "{searchTerm}"
                    </p>
                )}
            </PluginDocumentSettingPanel>
        </>
    );
};

export default AnnouncementEditor;
