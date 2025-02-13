import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import { useSelect, useDispatch } from '@wordpress/data';
import { 
    TextControl, 
    SelectControl, 
    Button, 
    PanelBody,
    CheckboxControl,
    __experimentalNumberControl as NumberControl,
    RadioControl
} from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';

const EventDetails = () => {
    const postType = useSelect(select => 
        select('core/editor').getCurrentPostType()
    );

    const meta = useSelect(select => 
        select('core/editor').getEditedPostAttribute('meta') || {}
    );

    const { editPost } = useDispatch('core/editor');

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
            />

            <PanelBody title="Event Date & Time" initialOpen={true}>
                <div className="mayo-sidebar-datetime">
                    <p className="mayo-sidebar-label">Start Date/Time</p>
                    <div className="mayo-sidebar-datetime-inputs">
                        <TextControl
                            type="date"
                            value={meta.event_start_date}
                            onChange={value => updateMetaValue('event_start_date', value)}
                        />
                        <TextControl
                            type="time"
                            value={meta.event_start_time}
                            onChange={value => updateMetaValue('event_start_time', value)}
                        />
                    </div>

                    <p className="mayo-sidebar-label">End Date/Time</p>
                    <div className="mayo-sidebar-datetime-inputs">
                        <TextControl
                            type="date"
                            value={meta.event_end_date}
                            onChange={value => updateMetaValue('event_end_date', value)}
                        />
                        <TextControl
                            type="time"
                            value={meta.event_end_time}
                            onChange={value => updateMetaValue('event_end_time', value)}
                        />
                    </div>
                </div>
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
                                />
                            </div>
                        )}
                    </div>
                )}
            </PanelBody>

            <div className="editor-post-featured-image">
                {meta.flyer_url && (
                    <div>
                        <img 
                            src={meta.flyer_url} 
                            alt="Event Flyer"
                            style={{ maxWidth: '100%', marginBottom: '8px' }}
                        />
                        <div>
                            <Button 
                                onClick={() => updateMetaValue('flyer_id', '')}
                                isDestructive
                            >
                                Remove Flyer
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            <PanelBody
                title="Location Details"
                initialOpen={true}
            >
                <TextControl
                    label="Location Name"
                    value={meta.location_name}
                    onChange={(value) => updateMetaValue('location_name', value)}
                    placeholder="e.g., Community Center"
                />
                <TextControl
                    label="Address"
                    value={meta.location_address}
                    onChange={(value) => updateMetaValue('location_address', value)}
                    placeholder="Full address"
                />
                <TextControl
                    label="Location Details"
                    value={meta.location_details}
                    onChange={(value) => updateMetaValue('location_details', value)}
                    placeholder="Parking info, entrance details, etc."
                />
            </PanelBody>
        </PluginDocumentSettingPanel>
    );
};

registerPlugin('mayo-event-details', {
    render: EventDetails,
    icon: 'calendar'
}); 