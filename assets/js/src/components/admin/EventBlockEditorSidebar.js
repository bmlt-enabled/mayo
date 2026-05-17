import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect, useDispatch } from '@wordpress/data';
import {
    TextControl,
    SelectControl,
    PanelBody,
    CheckboxControl,
    __experimentalNumberControl as NumberControl,
    RadioControl,
    Button,
    Spinner
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEventProvider } from '../providers/EventProvider';
import { useState, useEffect } from '@wordpress/element';
import { getTimezoneOptions, getUserTimezone } from '../../timezones';
import { apiFetch } from '../../util';

const EventBlockEditorSidebar = () => {
    const { serviceBodies } = useEventProvider();
    const [isAddingSkip, setIsAddingSkip] = useState(false);
    const [skipDate, setSkipDate] = useState('');
    const [linkedAnnouncements, setLinkedAnnouncements] = useState([]);
    const [isLoadingAnnouncements, setIsLoadingAnnouncements] = useState(false);

    const postType = useSelect(select =>
        select('core/editor').getCurrentPostType()
    );

    const postId = useSelect(select =>
        select('core/editor').getCurrentPostId()
    );

    const meta = useSelect(select =>
        select('core/editor').getEditedPostAttribute('meta') || {}
    );

    const { editPost } = useDispatch('core/editor');

    // Check if this is a new post (auto-draft status means it's new)
    const postStatus = useSelect(select =>
        select('core/editor').getEditedPostAttribute('status')
    );

    // Fetch linked announcements for this event
    useEffect(() => {
        if (postType !== 'mayo_event' || !postId || postStatus === 'auto-draft') return;

        const fetchAnnouncements = async () => {
            setIsLoadingAnnouncements(true);
            try {
                const response = await apiFetch(`/announcements?linked_event=${postId}`);
                setLinkedAnnouncements(response.announcements || []);
            } catch (error) {
                console.error('Error fetching linked announcements:', error);
                setLinkedAnnouncements([]);
            }
            setIsLoadingAnnouncements(false);
        };

        fetchAnnouncements();
    }, [postType, postId, postStatus]);

    if (postType !== 'mayo_event') return null;

    // Determine if this is a new event
    const isNewEvent = postStatus === 'auto-draft';

    const updateMetaValue = (key, value) => {
        editPost({ meta: { ...meta, [key]: value } });
    };

    // Auto-set timezone for new events based on browser timezone
    useEffect(() => {
        if (isNewEvent && !meta.timezone) {
            const detectedTimezone = getUserTimezone();
            updateMetaValue('timezone', detectedTimezone);
        }
    }, [isNewEvent, meta.timezone]);

    const recurringPattern = meta.recurring_pattern || {
        type: 'none',
        interval: 1,
        weekdays: [],
        endDate: '',
        monthlyType: 'date',
        monthlyDate: '',
        monthlyWeekday: ''
    };

    const updateRecurringPattern = (updates) => {
        const newPattern = {
            ...recurringPattern,
            ...updates,
            // Ensure weekdays is always an array
            weekdays: updates.weekdays || recurringPattern.weekdays || []
        };
        updateMetaValue('recurring_pattern', newPattern);
    };

    // Helper functions for skipped occurrences
    const skippedOccurrences = meta.skipped_occurrences || [];

    const addSkippedOccurrence = () => {
        if (skipDate) {
            // Ensure the date is stored in YYYY-MM-DD format without timezone issues
            const newSkipped = [...skippedOccurrences, skipDate];
            updateMetaValue('skipped_occurrences', newSkipped);
            setSkipDate('');
            setIsAddingSkip(false);
        }
    };

    const removeSkippedOccurrence = (dateToRemove) => {
        const newSkipped = skippedOccurrences.filter(date => date !== dateToRemove);
        updateMetaValue('skipped_occurrences', newSkipped);
    };

    const formatDate = (dateString) => {
        // Parse the date string as local date to avoid timezone issues
        const [year, month, day] = dateString.split('-').map(Number);
        const date = new Date(year, month - 1, day); // month is 0-indexed in Date constructor

        return date.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    };

    const weekdays = [
        { value: 0, label: __('Sunday', 'mayo-events-manager') },
        { value: 1, label: __('Monday', 'mayo-events-manager') },
        { value: 2, label: __('Tuesday', 'mayo-events-manager') },
        { value: 3, label: __('Wednesday', 'mayo-events-manager') },
        { value: 4, label: __('Thursday', 'mayo-events-manager') },
        { value: 5, label: __('Friday', 'mayo-events-manager') },
        { value: 6, label: __('Saturday', 'mayo-events-manager') }
    ];

    const weekNumbers = [
        { value: '1', label: __('First', 'mayo-events-manager') },
        { value: '2', label: __('Second', 'mayo-events-manager') },
        { value: '3', label: __('Third', 'mayo-events-manager') },
        { value: '4', label: __('Fourth', 'mayo-events-manager') },
        { value: '5', label: __('Fifth', 'mayo-events-manager') },
        { value: '-1', label: __('Last', 'mayo-events-manager') }
    ];

    // Helper function to get initial date from event start date
    const getInitialMonthlyDate = () => {
        if (meta.event_start_date) {
            const date = new Date(meta.event_start_date);
            return date.getDate().toString();
        }
        return '';
    };

    // Helper function to get initial weekday from event start date
    const getInitialWeekdayPattern = () => {
        if (meta.event_start_date) {
            const date = new Date(meta.event_start_date);
            const weekNumber = Math.ceil(date.getDate() / 7);
            return `${weekNumber},${date.getDay()}`;
        }
        return '';
    };

    return (
        <>
            <PluginDocumentSettingPanel
                name="mayo-event-details"
                title={__('Event Details', 'mayo-events-manager')}
                className="mayo-event-details"
            >
                <SelectControl
                    label={__('Event Type', 'mayo-events-manager')}
                    value={meta.event_type}
                    options={[
                        { label: __('Select Event Type', 'mayo-events-manager'), value: '' },
                        { label: __('Service', 'mayo-events-manager'), value: 'Service' },
                        { label: __('Activity', 'mayo-events-manager'), value: 'Activity' },
                        { label: __('Celebration', 'mayo-events-manager'), value: 'Celebration' }
                    ]}
                    onChange={value => updateMetaValue('event_type', value)}
                    __nextHasNoMarginBottom={true}
                    __next40pxDefaultSize={true}
                />

                <PanelBody title={__('Date & Time', 'mayo-events-manager')} initialOpen={true}>
                    <div className="mayo-sidebar-datetime">
                        <p className="mayo-sidebar-label">{__('Start Date/Time', 'mayo-events-manager')}</p>
                        <div className="mayo-sidebar-datetime-inputs">
                            <TextControl
                                type="date"
                                value={meta.event_start_date}
                                onChange={value => updateMetaValue('event_start_date', value)}
                                __nextHasNoMarginBottom={true}
                                __next40pxDefaultSize={true}
                            />
                            <TextControl
                                type="time"
                                value={meta.event_start_time}
                                onChange={value => updateMetaValue('event_start_time', value)}
                                __nextHasNoMarginBottom={true}
                                __next40pxDefaultSize={true}
                            />
                        </div>

                        <p className="mayo-sidebar-label">{__('End Date/Time', 'mayo-events-manager')}</p>
                        <div className="mayo-sidebar-datetime-inputs">
                            <TextControl
                                type="date"
                                value={meta.event_end_date}
                                onChange={value => updateMetaValue('event_end_date', value)}
                                __nextHasNoMarginBottom={true}
                                __next40pxDefaultSize={true}
                            />
                            <TextControl
                                type="time"
                                value={meta.event_end_time}
                                onChange={value => updateMetaValue('event_end_time', value)}
                                __nextHasNoMarginBottom={true}
                                __next40pxDefaultSize={true}
                            />
                        </div>
                    </div>
                    <SelectControl
                        label={__('Timezone', 'mayo-events-manager')}
                        value={meta.timezone || ''}
                        options={[
                            { label: __('-- No timezone set --', 'mayo-events-manager'), value: '' },
                            ...getTimezoneOptions()
                        ]}
                        onChange={value => updateMetaValue('timezone', value)}
                        __nextHasNoMarginBottom={true}
                        __next40pxDefaultSize={true}
                    />
                </PanelBody>

                <PanelBody title={__('Recurring Pattern', 'mayo-events-manager')} initialOpen={true}>
                    <SelectControl
                        label={__('Repeat', 'mayo-events-manager')}
                        value={recurringPattern.type}
                        options={[
                            { label: __('No Recurrence', 'mayo-events-manager'), value: 'none' },
                            { label: __('Daily', 'mayo-events-manager'), value: 'daily' },
                            { label: __('Weekly', 'mayo-events-manager'), value: 'weekly' },
                            { label: __('Monthly', 'mayo-events-manager'), value: 'monthly' }
                        ]}
                        onChange={value => updateRecurringPattern({ type: value })}
                        __nextHasNoMarginBottom={true}
                        __next40pxDefaultSize={true}
                    />

                    {recurringPattern.type !== 'none' && (
                        <>
                            <div className="mayo-recurring-interval">
                                <NumberControl
                                    label={__('Repeat every', 'mayo-events-manager')}
                                    min={1}
                                    value={recurringPattern.interval}
                                    onChange={value => updateRecurringPattern({ interval: value })}
                                />
                                <span>
                                    {recurringPattern.type === 'daily' ? __('days', 'mayo-events-manager') :
                                     recurringPattern.type === 'weekly' ? __('weeks', 'mayo-events-manager') : __('months', 'mayo-events-manager')}
                                </span>
                            </div>

                            {recurringPattern.type === 'weekly' && (
                                <div className="mayo-weekday-controls">
                                    <p className="components-base-control__label">{__('On these days', 'mayo-events-manager')}</p>
                                    {weekdays.map(day => (
                                        <CheckboxControl
                                            key={day.value}
                                            label={day.label}
                                            checked={recurringPattern.weekdays.includes(day.value)}
                                            onChange={(checked) => {
                                                const newWeekdays = checked
                                                    ? [...recurringPattern.weekdays, day.value]
                                                    : recurringPattern.weekdays.filter(d => d !== day.value);
                                                updateRecurringPattern({ weekdays: newWeekdays });
                                            }}
                                        />
                                    ))}
                                </div>
                            )}

                            <TextControl
                                label={__('End Date (optional)', 'mayo-events-manager')}
                                type="date"
                                value={recurringPattern.endDate}
                                onChange={value => updateRecurringPattern({ endDate: value })}
                                __nextHasNoMarginBottom={true}
                                __next40pxDefaultSize={true}
                            />
                        </>
                    )}

                    {recurringPattern.type === 'monthly' && (
                        <div className="mayo-monthly-pattern">
                            <RadioControl
                                label={__('Monthly Pattern', 'mayo-events-manager')}
                                selected={recurringPattern.monthlyType || 'date'}
                                options={[
                                    { label: __('On a specific date', 'mayo-events-manager'), value: 'date' },
                                    { label: __('On a specific day', 'mayo-events-manager'), value: 'weekday' }
                                ]}
                                onChange={value => updateRecurringPattern({
                                    monthlyType: value,
                                    monthlyDate: value === 'date' ? getInitialMonthlyDate() : '',
                                    monthlyWeekday: value === 'weekday' ? getInitialWeekdayPattern() : ''
                                })}
                            />

                            {recurringPattern.monthlyType === 'date' && (
                                <NumberControl
                                    label={__('Day of month', 'mayo-events-manager')}
                                    value={recurringPattern.monthlyDate || getInitialMonthlyDate()}
                                    onChange={value => updateRecurringPattern({ monthlyDate: value })}
                                    min={1}
                                    max={31}
                                />
                            )}

                            {recurringPattern.monthlyType === 'weekday' && (
                                <div className="mayo-monthly-weekday">
                                    <SelectControl
                                        label={__('Week', 'mayo-events-manager')}
                                        value={recurringPattern.monthlyWeekday?.split(',')[0] || '1'}
                                        options={weekNumbers}
                                        onChange={value => {
                                            const currentDay = recurringPattern.monthlyWeekday?.split(',')[1] || '0';
                                            updateRecurringPattern({
                                                monthlyWeekday: `${value},${currentDay}`
                                            });
                                        }}
                                        __nextHasNoMarginBottom={true}
                                        __next40pxDefaultSize={true}
                                    />
                                    <SelectControl
                                        label={__('Day', 'mayo-events-manager')}
                                        value={recurringPattern.monthlyWeekday?.split(',')[1] || '0'}
                                        options={weekdays}
                                        onChange={value => {
                                            const currentWeek = recurringPattern.monthlyWeekday?.split(',')[0] || '1';
                                            updateRecurringPattern({
                                                monthlyWeekday: `${currentWeek},${value}`
                                            });
                                        }}
                                        __nextHasNoMarginBottom={true}
                                        __next40pxDefaultSize={true}
                                    />
                                </div>
                            )}
                        </div>
                    )}
                </PanelBody>

                {/* Skipped Occurrences Management */}
                {recurringPattern.type !== 'none' && (
                    <PanelBody title={__('Skipped Occurrences', 'mayo-events-manager')} initialOpen={false}>
                        <p className="components-base-control__help">
                            {__('Manage specific dates when this recurring event should not occur.', 'mayo-events-manager')}
                        </p>

                        {skippedOccurrences.length > 0 && (
                            <div className="mayo-skipped-occurrences-list">
                                <p className="components-base-control__label">{__('Skipped Dates:', 'mayo-events-manager')}</p>
                                {skippedOccurrences.map((date, index) => (
                                    <div key={index} className="mayo-skipped-occurrence-item">
                                        <span>{formatDate(date)}</span>
                                        <Button
                                            isSmall
                                            isDestructive
                                            onClick={() => removeSkippedOccurrence(date)}
                                        >
                                            {__('Remove', 'mayo-events-manager')}
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}

                        {isAddingSkip ? (
                            <div className="mayo-add-skip-form">
                                <TextControl
                                    type="date"
                                    label={__('Date to skip', 'mayo-events-manager')}
                                    value={skipDate}
                                    onChange={setSkipDate}
                                    __nextHasNoMarginBottom={true}
                                    __next40pxDefaultSize={true}
                                />
                                <div className="mayo-add-skip-actions">
                                    <Button
                                        isPrimary
                                        isSmall
                                        onClick={addSkippedOccurrence}
                                        disabled={!skipDate}
                                    >
                                        {__('Add Skip', 'mayo-events-manager')}
                                    </Button>
                                    <Button
                                        isSecondary
                                        isSmall
                                        onClick={() => {
                                            setIsAddingSkip(false);
                                            setSkipDate('');
                                        }}
                                    >
                                        {__('Cancel', 'mayo-events-manager')}
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <Button
                                isSecondary
                                isSmall
                                onClick={() => setIsAddingSkip(true)}
                            >
                                {__('Add Skipped Date', 'mayo-events-manager')}
                            </Button>
                        )}
                    </PanelBody>
                )}

                <PanelBody title={__('Service Body', 'mayo-events-manager')} initialOpen={true}>
                    <SelectControl
                        label={__('Service Body', 'mayo-events-manager')}
                        value={meta.service_body}
                        options={[
                            { label: __('Select a service body', 'mayo-events-manager'), value: '' },
                            { label: __('Unaffiliated (0)', 'mayo-events-manager'), value: '0' },
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

                <PanelBody
                    title={__('Location Details', 'mayo-events-manager')}
                    initialOpen={true}
                >
                    <TextControl
                        label={__('Location Name', 'mayo-events-manager')}
                        value={meta.location_name}
                        onChange={(value) => updateMetaValue('location_name', value)}
                        placeholder={__('e.g., Community Center', 'mayo-events-manager')}
                        __nextHasNoMarginBottom={true}
                        __next40pxDefaultSize={true}
                    />
                    <TextControl
                        label={__('Address', 'mayo-events-manager')}
                        value={meta.location_address}
                        onChange={(value) => updateMetaValue('location_address', value)}
                        placeholder={__('Full address', 'mayo-events-manager')}
                        __nextHasNoMarginBottom={true}
                        __next40pxDefaultSize={true}
                    />
                    <TextControl
                        label={__('Location Details', 'mayo-events-manager')}
                        value={meta.location_details}
                        onChange={(value) => updateMetaValue('location_details', value)}
                        placeholder={__('Parking info, entrance details, etc.', 'mayo-events-manager')}
                        __nextHasNoMarginBottom={true}
                        __next40pxDefaultSize={true}
                    />
                </PanelBody>
            </PluginDocumentSettingPanel>

            <PluginDocumentSettingPanel
                name="mayo-point-of-contact"
                title={__('Point of Contact', 'mayo-events-manager')}
                className="mayo-private-contact"
            >
                <PanelBody
                    title={__('Contact Information (Private)', 'mayo-events-manager')}
                    initialOpen={true}
                >
                    <TextControl
                        label={__('Contact Name', 'mayo-events-manager')}
                        value={meta.contact_name}
                        onChange={(value) => updateMetaValue('contact_name', value)}
                        placeholder={__('Full name of the contact person', 'mayo-events-manager')}
                        __nextHasNoMarginBottom={true}
                        __next40pxDefaultSize={true}
                    />
                    <TextControl
                        label={__('Contact Email', 'mayo-events-manager')}
                        value={meta.email}
                        onChange={(value) => updateMetaValue('email', value)}
                        placeholder={__('Email address', 'mayo-events-manager')}
                        type="email"
                        __nextHasNoMarginBottom={true}
                        __next40pxDefaultSize={true}
                    />
                </PanelBody>
            </PluginDocumentSettingPanel>

            {/* Linked Announcements Panel */}
            {!isNewEvent && (
                <PluginDocumentSettingPanel
                    name="mayo-linked-announcements"
                    title={__('Linked Announcements', 'mayo-events-manager')}
                    className="mayo-linked-announcements"
                >
                    <p className="components-base-control__help" style={{ marginTop: 0 }}>
                        {__('Announcements that reference this event.', 'mayo-events-manager')}
                    </p>

                    {isLoadingAnnouncements && (
                        <div style={{ textAlign: 'center', padding: '16px' }}>
                            <Spinner />
                        </div>
                    )}

                    {!isLoadingAnnouncements && linkedAnnouncements.length === 0 && (
                        <p style={{ color: '#666', fontStyle: 'italic' }}>
                            {__('No announcements linked to this event.', 'mayo-events-manager')}
                        </p>
                    )}

                    {!isLoadingAnnouncements && linkedAnnouncements.length > 0 && (
                        <div className="mayo-linked-announcements-list">
                            {linkedAnnouncements.map(announcement => {
                                const statusColor = announcement.is_active ? '#46b450' :
                                    (announcement.display_start_date && announcement.display_start_date > new Date().toISOString().split('T')[0]) ? '#0073aa' : '#dc3545';
                                const statusLabel = announcement.is_active ? __('Active', 'mayo-events-manager') :
                                    (announcement.display_start_date && announcement.display_start_date > new Date().toISOString().split('T')[0]) ? __('Scheduled', 'mayo-events-manager') : __('Expired', 'mayo-events-manager');

                                return (
                                    <div
                                        key={announcement.id}
                                        style={{
                                            padding: '10px 12px',
                                            backgroundColor: '#f9f9f9',
                                            borderRadius: '4px',
                                            marginBottom: '8px',
                                            borderLeft: `3px solid ${statusColor}`,
                                        }}
                                    >
                                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                                            <strong>{announcement.title}</strong>
                                            <span style={{ fontSize: '11px', color: statusColor, fontWeight: 600 }}>
                                                {statusLabel}
                                            </span>
                                        </div>
                                        <div style={{ fontSize: '12px', color: '#666', marginTop: '4px' }}>
                                            {__('Priority', 'mayo-events-manager')}: <span style={{
                                                color: announcement.priority === 'urgent' ? '#dc3545' :
                                                    announcement.priority === 'high' ? '#ff9800' :
                                                    announcement.priority === 'low' ? '#6c757d' : '#0073aa',
                                                fontWeight: 600
                                            }}>{announcement.priority}</span>
                                        </div>
                                        <div style={{ display: 'flex', gap: '8px', marginTop: '8px' }}>
                                            {announcement.edit_link && (
                                                <a
                                                    href={announcement.edit_link}
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
                                                    {__('Edit', 'mayo-events-manager')}
                                                </a>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    <div style={{ marginTop: '12px' }}>
                        <Button
                            isSecondary
                            href={`${window.location.origin}/wp-admin/post-new.php?post_type=mayo_announcement&linked_event=${postId}`}
                            target="_blank"
                        >
                            {__('Create Announcement', 'mayo-events-manager')}
                        </Button>
                    </div>
                </PluginDocumentSettingPanel>
            )}
        </>
    );
};

export default EventBlockEditorSidebar;
