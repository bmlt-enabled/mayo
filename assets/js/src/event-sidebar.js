const { registerPlugin } = wp.plugins;
const { useSelect, useDispatch } = wp.data;
const { __ } = wp.i18n;
const { TextControl, DatePicker, PanelBody } = wp.components;
const { PluginDocumentSettingPanel } = wp.editPost;

const EventDetailsSidebar = () => {
    const postType = useSelect(select => select('core/editor').getCurrentPostType());
    
    if (postType !== 'mayo_event') return null;

    const meta = useSelect(select => {
        const currentMeta = select('core/editor').getEditedPostAttribute('meta') || {};
        return {
            event_type: currentMeta.event_type || '',
            event_date: currentMeta.event_date || '',
            event_start_time: currentMeta.event_start_time || '',
            event_end_time: currentMeta.event_end_time || '',
            flyer_url: currentMeta.flyer_url || '',
            recurring_schedule: currentMeta.recurring_schedule || ''
        };
    });

    const { editPost } = useDispatch('core/editor');

    const updateMetaValue = (key, value) => {
        editPost({ 
            meta: { 
                ...meta, 
                [key]: value 
            } 
        });
    };

    return (
        <PluginDocumentSettingPanel
            name="event-details"
            title={__('Event Details')}
            className="mayo-event-details"
            initialOpen={true}
        >
            <div style={{ padding: '8px 0' }}>
                <TextControl
                    label={__('Event Type')}
                    value={meta.event_type}
                    onChange={(value) => updateMetaValue('event_type', value)}
                    __nextHasNoMarginBottom
                />
                <TextControl
                    label={__('Event Date')}
                    type="date"
                    value={meta.event_date}
                    onChange={(value) => updateMetaValue('event_date', value)}
                    __nextHasNoMarginBottom
                />
                <TextControl
                    label={__('Start Time')}
                    type="time"
                    value={meta.event_start_time}
                    onChange={(value) => updateMetaValue('event_start_time', value)}
                    __nextHasNoMarginBottom
                />
                <TextControl
                    label={__('End Time')}
                    type="time"
                    value={meta.event_end_time}
                    onChange={(value) => updateMetaValue('event_end_time', value)}
                    __nextHasNoMarginBottom
                />
                <TextControl
                    label={__('Flyer URL')}
                    type="url"
                    value={meta.flyer_url}
                    onChange={(value) => updateMetaValue('flyer_url', value)}
                    __nextHasNoMarginBottom
                />
                <TextControl
                    label={__('Recurring Schedule')}
                    value={meta.recurring_schedule}
                    onChange={(value) => updateMetaValue('recurring_schedule', value)}
                    __nextHasNoMarginBottom
                />
            </div>
        </PluginDocumentSettingPanel>
    );
};

registerPlugin('mayo-event-details', {
    render: EventDetailsSidebar
}); 