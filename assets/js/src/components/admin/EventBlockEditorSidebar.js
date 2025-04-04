import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import { useSelect, useDispatch } from '@wordpress/data';
import { 
    TextControl, 
    SelectControl, 
    PanelBody,
    CheckboxControl,
    __experimentalNumberControl as NumberControl,
    RadioControl,
    Button
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEventProvider } from '../providers/EventProvider';

const EventBlockEditorSidebar = () => {
    const { serviceBodies } = useEventProvider();

    const postType = useSelect(select => 
        select('core/editor').getCurrentPostType()
    );

    const meta = useSelect(select => 
        select('core/editor').getEditedPostAttribute('meta') || {}
    );

    const { editPost } = useDispatch('core/editor');

    // Add this debug selector
    const postData = useSelect(select => 
        select('core/editor').getCurrentPost()
    );

    if (postType !== 'mayo_event') return null;

    const updateMetaValue = (key, value) => {
        editPost({ meta: { ...meta, [key]: value } });
    };

    const recurringPattern = meta.recurring_pattern || {
        type: 'none',
        interval: 1,
        weekdays: [],
        endDate: ''
    };

    const updateRecurringPattern = (updates) => {
        const newPattern = { ...recurringPattern, ...updates };
        updateMetaValue('recurring_pattern', newPattern);
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

    // Update the handlePdfUpload function with better logging
    const handlePdfUpload = () => {
        const fileFrame = window.wp.media({
            title: 'Select or Upload PDF',
            library: {
                type: 'application/pdf'
            },
            button: {
                text: 'Use this PDF'
            },
            multiple: false
        });

        fileFrame.on('select', function() {
            const attachment = fileFrame.state().get('selection').first().toJSON();
            console.log('Selected PDF attachment:', attachment);

            // Update meta values
            const updates = {
                meta: {
                    ...meta,
                    event_pdf_url: attachment.url,
                    event_pdf_id: attachment.id.toString()
                }
            };
            
            console.log('Updating post with:', updates);
            editPost(updates);

            // Add a timeout to check if the meta was updated
            setTimeout(() => {
                const updatedMeta = select('core/editor').getEditedPostAttribute('meta');
                console.log('Updated meta after save:', updatedMeta);
            }, 1000);
        });

        fileFrame.open();
    };

    return (
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
                    value={meta.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone}
                    options={[
                        { label: 'Eastern Time', value: 'America/New_York' },
                        { label: 'Central Time', value: 'America/Chicago' },
                        { label: 'Mountain Time', value: 'America/Denver' },
                        { label: 'Pacific Time', value: 'America/Los_Angeles' },
                        { label: 'Alaska Time', value: 'America/Anchorage' },
                        { label: 'Hawaii Time', value: 'Pacific/Honolulu' }
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

            <PanelBody title="Service Body" initialOpen={true}>
                <SelectControl
                    label="Service Body"
                    value={meta.service_body}
                    options={[
                        { label: 'Select a service body', value: '' },
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

            <PanelBody
                title="PDF Document"
                initialOpen={true}
            >
                <div className="mayo-pdf-section">
                    {meta.event_pdf_url ? (
                        <div className="mayo-pdf-preview">
                            <div className="mayo-pdf-link-wrapper" style={{ marginBottom: '8px' }}>
                                <a 
                                    href={meta.event_pdf_url} 
                                    target="_blank" 
                                    rel="noopener noreferrer"
                                    className="mayo-pdf-link"
                                    style={{ 
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                        padding: '8px 12px',
                                        background: '#f0f0f1',
                                        borderRadius: '2px',
                                        textDecoration: 'none',
                                        color: '#2271b1'
                                    }}
                                >
                                    <span 
                                        className="dashicons dashicons-pdf" 
                                        style={{ marginRight: '8px' }}
                                    />
                                    View PDF
                                </a>
                            </div>
                            <Button
                                isDestructive
                                variant="secondary"
                                onClick={() => {
                                    const updates = {
                                        meta: {
                                            ...meta,
                                            event_pdf_url: '',
                                            event_pdf_id: ''
                                        }
                                    };
                                    console.log('Removing PDF:', updates);
                                    editPost(updates);
                                }}
                            >
                                Remove PDF
                            </Button>
                        </div>
                    ) : (
                        <div className="mayo-pdf-upload">
                            <Button
                                variant="secondary"
                                onClick={handlePdfUpload}
                            >
                                {__('Upload PDF')}
                            </Button>
                        </div>
                    )}
                </div>
            </PanelBody>
        </PluginDocumentSettingPanel>
    );
};

export default EventBlockEditorSidebar;
