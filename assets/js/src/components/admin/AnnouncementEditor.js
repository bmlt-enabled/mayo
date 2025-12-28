import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect, useDispatch } from '@wordpress/data';
import {
    TextControl,
    SelectControl,
    PanelBody,
    Button,
    Spinner,
    Modal,
    __experimentalInputControl as InputControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { apiFetch } from '../../util';
import { useEventProvider } from '../providers/EventProvider';

// Event Search Modal Component with infinite scroll
const EventSearchModal = ({ isOpen, onClose, onSelectEvent, onRemoveEvent, linkedEventRefs, getRefKey }) => {
    const [searchTerm, setSearchTerm] = useState('');
    const [events, setEvents] = useState([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isLoadingMore, setIsLoadingMore] = useState(false);
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(true);
    const [total, setTotal] = useState(0);
    const listRef = useRef(null);
    const searchTimeoutRef = useRef(null);

    const PER_PAGE = 20;

    // Format date helper
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

    // Check if event is already linked
    const isEventLinked = (event) => {
        return linkedEventRefs.some(ref => {
            if (ref.type === 'local' && event.source.type === 'local') {
                return ref.id === event.id;
            }
            if (ref.type === 'external' && event.source.type === 'external') {
                return ref.id === event.id && ref.source_id === event.source.id;
            }
            return false;
        });
    };

    // Fetch events
    const fetchEvents = useCallback(async (searchQuery, pageNum, append = false) => {
        if (pageNum === 1) {
            setIsLoading(true);
        } else {
            setIsLoadingMore(true);
        }

        try {
            const params = new URLSearchParams({
                per_page: PER_PAGE,
                page: pageNum,
            });
            if (searchQuery) {
                params.append('search', searchQuery);
            }

            const response = await apiFetch(`/events/search-all?${params.toString()}`);
            const newEvents = response.events || [];

            if (append) {
                setEvents(prev => [...prev, ...newEvents]);
            } else {
                setEvents(newEvents);
            }

            setTotal(response.total || 0);
            setHasMore(pageNum < (response.total_pages || 1));
            setPage(pageNum);
        } catch (error) {
            console.error('Error fetching events:', error);
            if (!append) {
                setEvents([]);
            }
            setHasMore(false);
        } finally {
            setIsLoading(false);
            setIsLoadingMore(false);
        }
    }, []);

    // Initial load and search
    useEffect(() => {
        if (!isOpen) return;

        // Clear previous timeout
        if (searchTimeoutRef.current) {
            clearTimeout(searchTimeoutRef.current);
        }

        // Debounce search
        searchTimeoutRef.current = setTimeout(() => {
            fetchEvents(searchTerm, 1, false);
        }, 300);

        return () => {
            if (searchTimeoutRef.current) {
                clearTimeout(searchTimeoutRef.current);
            }
        };
    }, [isOpen, searchTerm, fetchEvents]);

    // Reset when modal opens
    useEffect(() => {
        if (isOpen) {
            setSearchTerm('');
            setEvents([]);
            setPage(1);
            setHasMore(true);
        }
    }, [isOpen]);

    // Infinite scroll handler
    const handleScroll = useCallback(() => {
        if (!listRef.current || isLoading || isLoadingMore || !hasMore) return;

        const { scrollTop, scrollHeight, clientHeight } = listRef.current;
        // Load more when scrolled to bottom (with 100px threshold)
        if (scrollHeight - scrollTop - clientHeight < 100) {
            fetchEvents(searchTerm, page + 1, true);
        }
    }, [isLoading, isLoadingMore, hasMore, searchTerm, page, fetchEvents]);

    // Handle event selection
    const handleSelectEvent = (event) => {
        if (!isEventLinked(event)) {
            onSelectEvent(event);
        }
    };

    // Handle event removal
    const handleRemoveEvent = (event, e) => {
        e.stopPropagation();
        onRemoveEvent(event);
    };

    if (!isOpen) return null;

    return (
        <Modal
            title="Link Events"
            onRequestClose={onClose}
            style={{ maxWidth: '600px', width: '100%' }}
            className="mayo-event-search-modal"
        >
            <div style={{ marginBottom: '16px' }}>
                <TextControl
                    label="Search Events"
                    value={searchTerm}
                    onChange={setSearchTerm}
                    placeholder="Search by event name..."
                    __nextHasNoMarginBottom={true}
                    __next40pxDefaultSize={true}
                />
                {total > 0 && (
                    <p style={{ margin: '8px 0 0', fontSize: '12px', color: '#666' }}>
                        {total} event{total !== 1 ? 's' : ''} found
                    </p>
                )}
            </div>

            <div
                ref={listRef}
                onScroll={handleScroll}
                style={{
                    maxHeight: '400px',
                    overflowY: 'auto',
                    border: '1px solid #ddd',
                    borderRadius: '4px',
                    backgroundColor: '#fff',
                }}
            >
                {isLoading && (
                    <div style={{ textAlign: 'center', padding: '40px' }}>
                        <Spinner />
                        <p style={{ margin: '8px 0 0', color: '#666' }}>Loading events...</p>
                    </div>
                )}

                {!isLoading && events.length === 0 && (
                    <div style={{ textAlign: 'center', padding: '40px', color: '#666' }}>
                        {searchTerm ? `No events found matching "${searchTerm}"` : 'No events available'}
                    </div>
                )}

                {!isLoading && events.map((event) => {
                    const isExternal = event.source.type === 'external';
                    const isLinked = isEventLinked(event);
                    const uniqueKey = isExternal
                        ? `external-${event.source.id}-${event.id}`
                        : `local-${event.id}`;

                    return (
                        <div
                            key={uniqueKey}
                            style={{
                                padding: '12px 16px',
                                borderBottom: '1px solid #eee',
                                cursor: 'pointer',
                                transition: 'background-color 0.2s',
                                backgroundColor: isLinked ? '#e8f5e9' : 'transparent',
                                borderLeft: `4px solid ${isLinked ? '#4caf50' : (isExternal ? '#ff9800' : '#0073aa')}`,
                            }}
                            onClick={() => isLinked ? null : handleSelectEvent(event)}
                            onMouseEnter={e => {
                                if (!isLinked) e.currentTarget.style.backgroundColor = '#f0f7ff';
                            }}
                            onMouseLeave={e => {
                                if (!isLinked) e.currentTarget.style.backgroundColor = isLinked ? '#e8f5e9' : 'transparent';
                            }}
                        >
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <div style={{ flex: 1 }}>
                                    <div style={{ fontWeight: 600, marginBottom: '4px', display: 'flex', alignItems: 'center' }}>
                                        {isLinked && (
                                            <span className="dashicons dashicons-yes-alt" style={{
                                                color: '#4caf50',
                                                fontSize: '16px',
                                                marginRight: '6px',
                                                width: '16px',
                                                height: '16px',
                                            }}></span>
                                        )}
                                        {event.title}
                                    </div>
                                    {event.start_date && (
                                        <div style={{ fontSize: '12px', color: '#666', marginBottom: '4px' }}>
                                            <span className="dashicons dashicons-calendar-alt" style={{
                                                fontSize: '12px',
                                                marginRight: '4px',
                                                verticalAlign: 'middle',
                                                width: '12px',
                                                height: '12px',
                                            }}></span>
                                            {formatDate(event.start_date)}
                                        </div>
                                    )}
                                </div>
                                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                    <span style={{
                                        fontSize: '10px',
                                        backgroundColor: isExternal ? '#fff3e0' : '#e3f2fd',
                                        color: isExternal ? '#e65100' : '#1565c0',
                                        padding: '3px 8px',
                                        borderRadius: '3px',
                                        whiteSpace: 'nowrap',
                                    }}>
                                        {event.source.name}
                                    </span>
                                    {isLinked && (
                                        <Button
                                            isSmall
                                            isDestructive
                                            onClick={(e) => handleRemoveEvent(event, e)}
                                            style={{ minWidth: 'auto' }}
                                        >
                                            Unlink
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>
                    );
                })}

                {isLoadingMore && (
                    <div style={{ textAlign: 'center', padding: '16px' }}>
                        <Spinner />
                        <span style={{ marginLeft: '8px', color: '#666', fontSize: '12px' }}>Loading more...</span>
                    </div>
                )}

                {!isLoading && !isLoadingMore && !hasMore && events.length > 0 && (
                    <div style={{ textAlign: 'center', padding: '12px', color: '#666', fontSize: '12px' }}>
                        End of results
                    </div>
                )}
            </div>

            <div style={{ marginTop: '16px', textAlign: 'right' }}>
                <Button variant="secondary" onClick={onClose}>
                    Close
                </Button>
            </div>
        </Modal>
    );
};

const AnnouncementEditor = () => {
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [hasPrelinked, setHasPrelinked] = useState(false);
    const [subscriberCount, setSubscriberCount] = useState(null);
    const [matchingSubscribers, setMatchingSubscribers] = useState([]);
    const [isLoadingCount, setIsLoadingCount] = useState(false);
    const [showEmailList, setShowEmailList] = useState(false);
    const { serviceBodies } = useEventProvider();

    const postType = useSelect(select =>
        select('core/editor').getCurrentPostType()
    );

    const postStatus = useSelect(select =>
        select('core/editor').getEditedPostAttribute('status')
    );

    const meta = useSelect(select =>
        select('core/editor').getEditedPostAttribute('meta') || {}
    );

    // Get categories and tags from WordPress editor data store
    const categories = useSelect(select =>
        select('core/editor').getEditedPostAttribute('categories') || []
    );

    const tags = useSelect(select =>
        select('core/editor').getEditedPostAttribute('tags') || []
    );

    const { editPost } = useDispatch('core/editor');

    const serviceBody = meta.service_body || '';

    // Fetch subscriber count when categories, tags, or service body change
    useEffect(() => {
        if (postType !== 'mayo_announcement') return;

        const fetchCount = async () => {
            setIsLoadingCount(true);
            try {
                const result = await apiFetch('/subscribers/count', {
                    method: 'POST',
                    body: JSON.stringify({ categories, tags, service_body: serviceBody })
                });
                setSubscriberCount(result.count);
                setMatchingSubscribers(result.subscribers || []);
            } catch (err) {
                console.error('Error fetching subscriber count:', err);
                setSubscriberCount(null);
                setMatchingSubscribers([]);
            }
            setIsLoadingCount(false);
        };

        const timeout = setTimeout(fetchCount, 300);
        return () => clearTimeout(timeout);
    }, [postType, categories, tags, serviceBody]);

    // Handle pre-linking from URL parameter (when coming from event editor)
    useEffect(() => {
        if (hasPrelinked || postType !== 'mayo_announcement' || postStatus !== 'auto-draft') return;

        const urlParams = new URLSearchParams(window.location.search);
        const linkedEventId = urlParams.get('linked_event');

        if (linkedEventId) {
            const eventId = parseInt(linkedEventId, 10);
            if (!isNaN(eventId) && eventId > 0) {
                // Use new linked_event_refs format
                const currentRefs = meta.linked_event_refs || [];
                const alreadyLinked = currentRefs.some(ref => ref.type === 'local' && ref.id === eventId);
                if (!alreadyLinked) {
                    const newRef = { type: 'local', id: eventId };
                    editPost({ meta: { ...meta, linked_event_refs: [...currentRefs, newRef] } });
                }
            }
            setHasPrelinked(true);
        }
    }, [postType, postStatus, hasPrelinked, meta.linked_event_refs]);

    if (postType !== 'mayo_announcement') return null;

    const updateMetaValue = (key, value) => {
        editPost({ meta: { ...meta, [key]: value } });
    };

    // Get linked event refs - prefer new format, fall back to old
    const getLinkedEventRefs = () => {
        if (meta.linked_event_refs && Array.isArray(meta.linked_event_refs) && meta.linked_event_refs.length > 0) {
            return meta.linked_event_refs;
        }
        // Fall back to old linked_events format
        if (meta.linked_events && Array.isArray(meta.linked_events)) {
            return meta.linked_events.map(id => ({ type: 'local', id }));
        }
        return [];
    };

    const linkedEventRefs = getLinkedEventRefs();

    // Create a unique key for each event ref
    const getRefKey = (ref) => {
        if (ref.type === 'local') {
            return `local-${ref.id}`;
        }
        return `external-${ref.source_id}-${ref.id}`;
    };

    const addLinkedEvent = (event) => {
        const currentRefs = getLinkedEventRefs();
        let newRef;

        if (event.source.type === 'local') {
            newRef = { type: 'local', id: event.id };
        } else {
            newRef = {
                type: 'external',
                id: event.id,
                source_id: event.source.id,
            };
        }

        // Check if already linked
        const alreadyLinked = currentRefs.some(ref => {
            if (ref.type === 'local' && newRef.type === 'local') {
                return ref.id === newRef.id;
            }
            if (ref.type === 'external' && newRef.type === 'external') {
                return ref.id === newRef.id && ref.source_id === newRef.source_id;
            }
            return false;
        });

        if (!alreadyLinked) {
            updateMetaValue('linked_event_refs', [...currentRefs, newRef]);
        }
    };

    const removeLinkedEvent = (refToRemove) => {
        const currentRefs = getLinkedEventRefs();
        const filtered = currentRefs.filter(ref => {
            if (ref.type === 'local' && refToRemove.type === 'local') {
                return ref.id !== refToRemove.id;
            }
            if (ref.type === 'external' && refToRemove.type === 'external') {
                return !(ref.id === refToRemove.id && ref.source_id === refToRemove.source_id);
            }
            return true;
        });
        updateMetaValue('linked_event_refs', filtered);
    };

    // Fetch linked event details
    const [linkedEventDetails, setLinkedEventDetails] = useState({});
    const [isLoadingEventDetails, setIsLoadingEventDetails] = useState(false);

    useEffect(() => {
        const fetchEventDetails = async () => {
            // Find events we don't have details for yet
            const refsToFetch = linkedEventRefs.filter(ref => !linkedEventDetails[getRefKey(ref)]);
            if (refsToFetch.length === 0) return;

            setIsLoadingEventDetails(true);
            const details = {};

            for (const ref of refsToFetch) {
                const key = getRefKey(ref);
                try {
                    if (ref.type === 'local') {
                        // Fetch local event details
                        const event = await apiFetch(`/events/${ref.id}`);
                        if (event && !event.code) {
                            details[key] = {
                                title: event.title || 'Unknown Event',
                                start_date: event.start_date || '',
                                permalink: event.permalink || '',
                                edit_link: event.edit_link || '',
                                source: { type: 'local', id: 'local', name: 'Local' },
                            };
                        } else {
                            details[key] = {
                                title: `Event #${ref.id}`,
                                start_date: '',
                                permalink: '',
                                edit_link: '',
                                unavailable: true,
                                source: { type: 'local', id: 'local', name: 'Local' },
                            };
                        }
                    } else {
                        // For external events, we need to fetch from the external source
                        try {
                            const response = await apiFetch(`/events/search-all?per_page=100`);
                            const externalEvent = (response.events || []).find(
                                e => e.source.type === 'external' &&
                                    e.source.id === ref.source_id &&
                                    e.id === ref.id
                            );
                            if (externalEvent) {
                                details[key] = {
                                    title: externalEvent.title || 'Unknown Event',
                                    start_date: externalEvent.start_date || '',
                                    permalink: externalEvent.permalink || '',
                                    edit_link: '', // No edit link for external events
                                    source: externalEvent.source,
                                };
                            } else {
                                // Event not found in search results
                                details[key] = {
                                    title: `External Event #${ref.id}`,
                                    start_date: '',
                                    permalink: '',
                                    edit_link: '',
                                    unavailable: true,
                                    source: { type: 'external', id: ref.source_id, name: ref.source_id },
                                };
                            }
                        } catch (e) {
                            details[key] = {
                                title: `External Event #${ref.id}`,
                                start_date: '',
                                permalink: '',
                                edit_link: '',
                                unavailable: true,
                                source: { type: 'external', id: ref.source_id, name: ref.source_id },
                            };
                        }
                    }
                } catch (error) {
                    console.error(`Error fetching event details:`, error);
                    details[key] = {
                        title: ref.type === 'local' ? `Event #${ref.id}` : `External Event #${ref.id}`,
                        start_date: '',
                        permalink: '',
                        edit_link: '',
                        unavailable: true,
                        source: ref.type === 'local'
                            ? { type: 'local', id: 'local', name: 'Local' }
                            : { type: 'external', id: ref.source_id, name: ref.source_id },
                    };
                }
            }

            if (Object.keys(details).length > 0) {
                setLinkedEventDetails(prev => ({ ...prev, ...details }));
            }
            setIsLoadingEventDetails(false);
        };

        if (linkedEventRefs.length > 0) {
            fetchEventDetails();
        }
    }, [JSON.stringify(linkedEventRefs)]);

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
                        __next40pxDefaultSize={true}
                    />
                    <TextControl
                        label="End Date"
                        type="date"
                        value={meta.display_end_date || ''}
                        onChange={value => updateMetaValue('display_end_date', value)}
                        help="Leave empty to show indefinitely"
                        __nextHasNoMarginBottom={true}
                        __next40pxDefaultSize={true}
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
                        __next40pxDefaultSize={true}
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

                <PanelBody title="Service Body" initialOpen={true}>
                    <SelectControl
                        label="Service Body"
                        value={meta.service_body || ''}
                        options={[
                            { label: 'Select a service body', value: '' },
                            { label: 'Unaffiliated (0)', value: '0' },
                            ...serviceBodies.map(body => ({
                                label: `${body.name} (${body.id})`,
                                value: body.id
                            }))
                        ]}
                        onChange={value => updateMetaValue('service_body', value)}
                        __nextHasNoMarginBottom={true}
                        __next40pxDefaultSize={true}
                    />
                </PanelBody>
            </PluginDocumentSettingPanel>

            <PluginDocumentSettingPanel
                name="mayo-linked-events"
                title="Linked Events"
                className="mayo-linked-events"
            >
                <p className="components-base-control__help" style={{ marginTop: 0 }}>
                    Link this announcement to local or external events.
                </p>

                {/* Linked Events List */}
                {isLoadingEventDetails && linkedEventRefs.length > 0 && (
                    <div style={{ textAlign: 'center', padding: '16px' }}>
                        <Spinner />
                        <p style={{ margin: '8px 0 0', color: '#666', fontSize: '12px' }}>Loading event details...</p>
                    </div>
                )}

                {!isLoadingEventDetails && linkedEventRefs.length > 0 && (
                    <div className="mayo-linked-events-list" style={{ marginBottom: '16px' }}>
                        {linkedEventRefs.map((ref, index) => {
                            const key = getRefKey(ref);
                            const details = linkedEventDetails[key] || {};
                            const hasDetails = details.title && !details.title.startsWith('Event #') && !details.title.startsWith('External Event #');
                            const isExternal = ref.type === 'external';

                            return (
                                <div
                                    key={key}
                                    style={{
                                        padding: '12px',
                                        backgroundColor: '#f9f9f9',
                                        borderRadius: '4px',
                                        marginBottom: '8px',
                                        borderLeft: `3px solid ${isExternal ? '#ff9800' : '#0073aa'}`,
                                    }}
                                >
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                                        <div style={{ flex: 1 }}>
                                            {hasDetails || details.title ? (
                                                <>
                                                    <strong style={{ display: 'block', marginBottom: '4px' }}>
                                                        {details.title || (isExternal ? `External Event #${ref.id}` : `Event #${ref.id}`)}
                                                    </strong>
                                                    {/* Source badge for external events */}
                                                    {isExternal && details.source && (
                                                        <span style={{
                                                            display: 'inline-block',
                                                            fontSize: '11px',
                                                            backgroundColor: '#fff3e0',
                                                            color: '#e65100',
                                                            padding: '2px 6px',
                                                            borderRadius: '3px',
                                                            marginBottom: '4px',
                                                        }}>
                                                            {details.source.name || details.source.id}
                                                        </span>
                                                    )}
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
                                                                {isExternal ? 'View on External Site' : 'View'}
                                                            </a>
                                                        )}
                                                        {!isExternal && details.edit_link && (
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
                                                    {isExternal ? `External Event #${ref.id}` : `Event #${ref.id}`}
                                                    <span style={{ fontSize: '12px', marginLeft: '8px' }}>(loading...)</span>
                                                </span>
                                            )}
                                        </div>
                                        <Button
                                            isSmall
                                            isDestructive
                                            onClick={() => removeLinkedEvent(ref)}
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

                {/* Link Event Button */}
                <Button
                    variant="secondary"
                    onClick={() => setIsModalOpen(true)}
                    style={{ width: '100%', justifyContent: 'center' }}
                >
                    <span className="dashicons dashicons-plus-alt2" style={{ marginRight: '4px' }}></span>
                    Link Event
                </Button>

                {/* Event Search Modal */}
                <EventSearchModal
                    isOpen={isModalOpen}
                    onClose={() => setIsModalOpen(false)}
                    onSelectEvent={(event) => {
                        addLinkedEvent(event);
                        // Keep modal open to allow linking multiple events
                    }}
                    onRemoveEvent={(event) => {
                        // Convert event object to ref format for removal
                        const refToRemove = event.source.type === 'local'
                            ? { type: 'local', id: event.id }
                            : { type: 'external', id: event.id, source_id: event.source.id };
                        removeLinkedEvent(refToRemove);
                    }}
                    linkedEventRefs={linkedEventRefs}
                    getRefKey={getRefKey}
                />
            </PluginDocumentSettingPanel>

            <PluginDocumentSettingPanel
                name="mayo-email-recipients"
                title="Email Recipients"
                className="mayo-email-recipients"
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: '8px',
                        cursor: subscriberCount > 0 ? 'pointer' : 'default',
                    }}
                    onClick={() => subscriberCount > 0 && setShowEmailList(true)}
                >
                    {isLoadingCount ? (
                        <Spinner style={{ margin: 0 }} />
                    ) : (
                        <>
                            <span
                                className="dashicons dashicons-email-alt"
                                style={{ color: '#2271b1', fontSize: '18px', width: '18px', height: '18px' }}
                            />
                            <span style={{ textDecoration: subscriberCount > 0 ? 'underline' : 'none' }}>
                                <strong>{subscriberCount ?? 0}</strong> subscriber{subscriberCount !== 1 ? 's' : ''} will receive this announcement
                            </span>
                        </>
                    )}
                </div>

                <p className="components-base-control__help" style={{ marginTop: '8px' }}>
                    Based on selected categories, tags, and service body.
                    {subscriberCount > 0 && ' Click to view recipients.'}
                </p>

                {showEmailList && (
                    <Modal
                        title={`Email Recipients (${matchingSubscribers.length})`}
                        onRequestClose={() => setShowEmailList(false)}
                        style={{ maxWidth: '600px', width: '100%' }}
                    >
                        <div style={{
                            maxHeight: '400px',
                            overflowY: 'auto',
                            border: '1px solid #ddd',
                            borderRadius: '4px',
                        }}>
                            {matchingSubscribers.length > 0 ? (
                                matchingSubscribers.map((sub, index) => (
                                    <div
                                        key={sub.email}
                                        style={{
                                            padding: '12px',
                                            borderBottom: index < matchingSubscribers.length - 1 ? '1px solid #eee' : 'none',
                                        }}
                                    >
                                        <div style={{ fontSize: '13px', fontWeight: 500, marginBottom: '8px' }}>
                                            {sub.email}
                                        </div>
                                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px' }}>
                                            {sub.reason.all ? (
                                                <span style={{
                                                    display: 'inline-block',
                                                    padding: '3px 8px',
                                                    fontSize: '11px',
                                                    backgroundColor: '#f0f0f0',
                                                    color: '#666',
                                                    borderRadius: '3px',
                                                }}>
                                                    Receives all announcements
                                                </span>
                                            ) : (
                                                <>
                                                    {sub.reason.categories?.map(cat => (
                                                        <span
                                                            key={cat}
                                                            style={{
                                                                display: 'inline-block',
                                                                padding: '3px 8px',
                                                                fontSize: '11px',
                                                                backgroundColor: '#e3f2fd',
                                                                color: '#1565c0',
                                                                borderRadius: '3px',
                                                            }}
                                                        >
                                                            {cat}
                                                        </span>
                                                    ))}
                                                    {sub.reason.tags?.map(tag => (
                                                        <span
                                                            key={tag}
                                                            style={{
                                                                display: 'inline-block',
                                                                padding: '3px 8px',
                                                                fontSize: '11px',
                                                                backgroundColor: '#fff3e0',
                                                                color: '#e65100',
                                                                borderRadius: '3px',
                                                            }}
                                                        >
                                                            {tag}
                                                        </span>
                                                    ))}
                                                    {sub.reason.service_body && (
                                                        <span style={{
                                                            display: 'inline-block',
                                                            padding: '3px 8px',
                                                            fontSize: '11px',
                                                            backgroundColor: '#e8f5e9',
                                                            color: '#2e7d32',
                                                            borderRadius: '3px',
                                                        }}>
                                                            {sub.reason.service_body}
                                                        </span>
                                                    )}
                                                </>
                                            )}
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div style={{ padding: '20px', textAlign: 'center', color: '#666' }}>
                                    No matching subscribers
                                </div>
                            )}
                        </div>
                        <div style={{ marginTop: '16px', textAlign: 'right' }}>
                            <Button variant="secondary" onClick={() => setShowEmailList(false)}>
                                Close
                            </Button>
                        </div>
                    </Modal>
                )}
            </PluginDocumentSettingPanel>
        </>
    );
};

export default AnnouncementEditor;
