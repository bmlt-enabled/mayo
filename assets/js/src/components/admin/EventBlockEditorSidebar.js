import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
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
import apiFetch from '@wordpress/api-fetch';

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
                const response = await apiFetch({
                    path: `/wp-json/event-manager/v1/announcements?linked_event=${postId}`,
                });
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
        { value: 0, label: 'Sunday' },
        { value: 1, label: 'Monday' },
        { value: 2, label: 'Tuesday' },
        { value: 3, label: 'Wednesday' },
        { value: 4, label: 'Thursday' },
        { value: 5, label: 'Friday' },
        { value: 6, label: 'Saturday' }
    ];

    const weekNumbers = [
        { value: '1', label: 'First' },
        { value: '2', label: 'Second' },
        { value: '3', label: 'Third' },
        { value: '4', label: 'Fourth' },
        { value: '5', label: 'Fifth' },
        { value: '-1', label: 'Last' }
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
                title="Event Details"
                className="mayo-event-details"
            >
                <SelectControl
                    label="Event Type"
                    value={meta.event_type}
                    options={[
                        { label: 'Select Event Type', value: '' },
                        { label: 'Service', value: 'Service' },
                        { label: 'Activity', value: 'Activity' }
                    ]}
                    onChange={value => updateMetaValue('event_type', value)}
                    __nextHasNoMarginBottom={true}
                />

                <PanelBody title="Event Date & Time" initialOpen={true}>
                    <div className="mayo-sidebar-datetime">
                        <p className="mayo-sidebar-label">Start Date/Time</p>
                        <div className="mayo-sidebar-datetime-inputs">
                            <TextControl
                                type="date"
                                value={meta.event_start_date}
                                onChange={value => updateMetaValue('event_start_date', value)}
                                __nextHasNoMarginBottom={true}
                            />
                            <TextControl
                                type="time"
                                value={meta.event_start_time}
                                onChange={value => updateMetaValue('event_start_time', value)}
                                __nextHasNoMarginBottom={true}
                            />
                        </div>

                        <p className="mayo-sidebar-label">End Date/Time</p>
                        <div className="mayo-sidebar-datetime-inputs">
                            <TextControl
                                type="date"
                                value={meta.event_end_date}
                                onChange={value => updateMetaValue('event_end_date', value)}
                                __nextHasNoMarginBottom={true}
                            />
                            <TextControl
                                type="time"
                                value={meta.event_end_time}
                                onChange={value => updateMetaValue('event_end_time', value)}
                                __nextHasNoMarginBottom={true}
                            />
                        </div>
                    </div>
                    <SelectControl
                        label="Timezone"
                        value={meta.timezone || (isNewEvent ? getUserTimezone() : '')}
                        options={[
                            { label: '-- No timezone set --', value: '' },
                            ...getTimezoneOptions()
                        ]}
                        onChange={value => updateMetaValue('timezone', value)}
                        __nextHasNoMarginBottom={true}
                    />
                </PanelBody>

                <PanelBody title="Recurring Pattern" initialOpen={true}>
                    <SelectControl
                        label="Repeat"
                        value={recurringPattern.type}
                        options={[
                            { label: 'No Recurrence', value: 'none' },
                            { label: 'Daily', value: 'daily' },
                            { label: 'Weekly', value: 'weekly' },
                            { label: 'Monthly', value: 'monthly' }
                        ]}
                        onChange={value => updateRecurringPattern({ type: value })}
                        __nextHasNoMarginBottom={true}
                    />

                    {recurringPattern.type !== 'none' && (
                        <>
                            <div className="mayo-recurring-interval">
                                <NumberControl
                                    label="Repeat every"
                                    min={1}
                                    value={recurringPattern.interval}
                                    onChange={value => updateRecurringPattern({ interval: value })}
                                />
                                <span>
                                    {recurringPattern.type === 'daily' ? 'days' : 
                                     recurringPattern.type === 'weekly' ? 'weeks' : 'months'}
                                </span>
                            </div>

                            {recurringPattern.type === 'weekly' && (
                                <div className="mayo-weekday-controls">
                                    <p className="components-base-control__label">On these days</p>
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
                                label="End Date (optional)"
                                type="date"
                                value={recurringPattern.endDate}
                                onChange={value => updateRecurringPattern({ endDate: value })}
                                __nextHasNoMarginBottom={true}
                            />
                        </>
                    )}

                    {recurringPattern.type === 'monthly' && (
                        <div className="mayo-monthly-pattern">
                            <RadioControl
                                label="Monthly Pattern"
                                selected={recurringPattern.monthlyType || 'date'}
                                options={[
                                    { label: 'On a specific date', value: 'date' },
                                    { label: 'On a specific day', value: 'weekday' }
                                ]}
                                onChange={value => updateRecurringPattern({ 
                                    monthlyType: value,
                                    monthlyDate: value === 'date' ? getInitialMonthlyDate() : '',
                                    monthlyWeekday: value === 'weekday' ? getInitialWeekdayPattern() : ''
                                })}
                            />

                            {recurringPattern.monthlyType === 'date' && (
                                <NumberControl
                                    label="Day of month"
                                    value={recurringPattern.monthlyDate || getInitialMonthlyDate()}
                                    onChange={value => updateRecurringPattern({ monthlyDate: value })}
                                    min={1}
                                    max={31}
                                />
                            )}

                            {recurringPattern.monthlyType === 'weekday' && (
                                <div className="mayo-monthly-weekday">
                                    <SelectControl
                                        label="Week"
                                        value={recurringPattern.monthlyWeekday?.split(',')[0] || '1'}
                                        options={weekNumbers}
                                        onChange={value => {
                                            const currentDay = recurringPattern.monthlyWeekday?.split(',')[1] || '0';
                                            updateRecurringPattern({ 
                                                monthlyWeekday: `${value},${currentDay}` 
                                            });
                                        }}
                                        __nextHasNoMarginBottom={true}
                                    />
                                    <SelectControl
                                        label="Day"
                                        value={recurringPattern.monthlyWeekday?.split(',')[1] || '0'}
                                        options={weekdays}
                                        onChange={value => {
                                            const currentWeek = recurringPattern.monthlyWeekday?.split(',')[0] || '1';
                                            updateRecurringPattern({ 
                                                monthlyWeekday: `${currentWeek},${value}` 
                                            });
                                        }}
                                        __nextHasNoMarginBottom={true}
                                    />
                                </div>
                            )}
                        </div>
                    )}
                </PanelBody>

                {/* Skipped Occurrences Management */}
                {recurringPattern.type !== 'none' && (
                    <PanelBody title="Skipped Occurrences" initialOpen={false}>
                        <p className="components-base-control__help">
                            Manage specific dates when this recurring event should not occur.
                        </p>
                        
                        {skippedOccurrences.length > 0 && (
                            <div className="mayo-skipped-occurrences-list">
                                <p className="components-base-control__label">Skipped Dates:</p>
                                {skippedOccurrences.map((date, index) => (
                                    <div key={index} className="mayo-skipped-occurrence-item">
                                        <span>{formatDate(date)}</span>
                                        <Button
                                            isSmall
                                            isDestructive
                                            onClick={() => removeSkippedOccurrence(date)}
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}

                        {isAddingSkip ? (
                            <div className="mayo-add-skip-form">
                                <TextControl
                                    type="date"
                                    label="Date to skip"
                                    value={skipDate}
                                    onChange={setSkipDate}
                                    __nextHasNoMarginBottom={true}
                                />
                                <div className="mayo-add-skip-actions">
                                    <Button
                                        isPrimary
                                        isSmall
                                        onClick={addSkippedOccurrence}
                                        disabled={!skipDate}
                                    >
                                        Add Skip
                                    </Button>
                                    <Button
                                        isSecondary
                                        isSmall
                                        onClick={() => {
                                            setIsAddingSkip(false);
                                            setSkipDate('');
                                        }}
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <Button
                                isSecondary
                                isSmall
                                onClick={() => setIsAddingSkip(true)}
                            >
                                Add Skipped Date
                            </Button>
                        )}
                    </PanelBody>
                )}

                <PanelBody title="Service Body" initialOpen={true}>
                    <SelectControl
                        label="Service Body"
                        value={meta.service_body}
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
                    />
                </PanelBody>

                <PanelBody
                    title="Location Details"
                    initialOpen={true}
                >
                    <TextControl
                        label="Location Name"
                        value={meta.location_name}
                        onChange={(value) => updateMetaValue('location_name', value)}
                        placeholder="e.g., Community Center"
                        __nextHasNoMarginBottom={true}
                    />
                    <TextControl
                        label="Address"
                        value={meta.location_address}
                        onChange={(value) => updateMetaValue('location_address', value)}
                        placeholder="Full address"
                        __nextHasNoMarginBottom={true}
                    />
                    <TextControl
                        label="Location Details"
                        value={meta.location_details}
                        onChange={(value) => updateMetaValue('location_details', value)}
                        placeholder="Parking info, entrance details, etc."
                        __nextHasNoMarginBottom={true}
                    />
                </PanelBody>
            </PluginDocumentSettingPanel>

            <PluginDocumentSettingPanel
                name="mayo-point-of-contact"
                title="Point of Contact"
                className="mayo-private-contact"
            >
                <PanelBody
                    title="Contact Information (Private)"
                    initialOpen={true}
                >
                    <TextControl
                        label="Contact Name"
                        value={meta.contact_name}
                        onChange={(value) => updateMetaValue('contact_name', value)}
                        placeholder="Full name of the contact person"
                        __nextHasNoMarginBottom={true}
                    />
                    <TextControl
                        label="Contact Email"
                        value={meta.email}
                        onChange={(value) => updateMetaValue('email', value)}
                        placeholder="Email address"
                        type="email"
                        __nextHasNoMarginBottom={true}
                    />
                </PanelBody>
            </PluginDocumentSettingPanel>

            {/* Linked Announcements Panel */}
            {!isNewEvent && (
                <PluginDocumentSettingPanel
                    name="mayo-linked-announcements"
                    title="Linked Announcements"
                    className="mayo-linked-announcements"
                >
                    <p className="components-base-control__help" style={{ marginTop: 0 }}>
                        Announcements that reference this event.
                    </p>

                    {isLoadingAnnouncements && (
                        <div style={{ textAlign: 'center', padding: '16px' }}>
                            <Spinner />
                        </div>
                    )}

                    {!isLoadingAnnouncements && linkedAnnouncements.length === 0 && (
                        <p style={{ color: '#666', fontStyle: 'italic' }}>
                            No announcements linked to this event.
                        </p>
                    )}

                    {!isLoadingAnnouncements && linkedAnnouncements.length > 0 && (
                        <div className="mayo-linked-announcements-list">
                            {linkedAnnouncements.map(announcement => {
                                const statusColor = announcement.is_active ? '#46b450' :
                                    (announcement.display_start_date && announcement.display_start_date > new Date().toISOString().split('T')[0]) ? '#0073aa' : '#dc3545';
                                const statusLabel = announcement.is_active ? 'Active' :
                                    (announcement.display_start_date && announcement.display_start_date > new Date().toISOString().split('T')[0]) ? 'Scheduled' : 'Expired';

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
                                            Priority: <span style={{
                                                color: announcement.priority === 'urgent' ? '#dc3545' :
                                                    announcement.priority === 'high' ? '#ff9800' :
                                                    announcement.priority === 'low' ? '#6c757d' : '#0073aa',
                                                fontWeight: 600
                                            }}>{announcement.priority}</span>
                                        </div>
                                        <Button
                                            isLink
                                            href={announcement.edit_link}
                                            target="_blank"
                                            style={{ marginTop: '4px', fontSize: '12px' }}
                                        >
                                            Edit Announcement
                                        </Button>
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
                            Create Announcement for This Event
                        </Button>
                    </div>
                </PluginDocumentSettingPanel>
            )}
        </>
    );
};

export default EventBlockEditorSidebar;
