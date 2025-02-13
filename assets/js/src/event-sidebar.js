const { registerPlugin } = wp.plugins;
const { useSelect, useDispatch } = wp.data;
const { __ } = wp.i18n;
const { TextControl, Button, PanelBody } = wp.components;
const { PluginDocumentSettingPanel } = wp.editPost;
const { MediaUpload, MediaUploadCheck } = wp.blockEditor;

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
            flyer_id: currentMeta.flyer_id || '',
            flyer_url: currentMeta.flyer_url || '',
            recurring_schedule: currentMeta.recurring_schedule || '',
            location_name: currentMeta.location_name || '',
            location_address: currentMeta.location_address || '',
            location_details: currentMeta.location_details || ''
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

    const onSelectImage = (media) => {
        updateMetaValue('flyer_id', media.id);
        updateMetaValue('flyer_url', media.url);
    };

    const removeImage = () => {
        updateMetaValue('flyer_id', '');
        updateMetaValue('flyer_url', '');
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
                    label={__('Recurring Schedule')}
                    value={meta.recurring_schedule}
                    onChange={(value) => updateMetaValue('recurring_schedule', value)}
                    __nextHasNoMarginBottom
                />
                
                <div className="editor-post-featured-image">
                    <MediaUploadCheck>
                        <MediaUpload
                            onSelect={onSelectImage}
                            allowedTypes={['image']}
                            value={meta.flyer_id}
                            render={({ open }) => (
                                <div>
                                    {meta.flyer_url ? (
                                        <div>
                                            <img 
                                                src={meta.flyer_url} 
                                                alt={__('Event Flyer')}
                                                style={{ maxWidth: '100%', marginBottom: '8px' }}
                                            />
                                            <div>
                                                <Button 
                                                    onClick={open}
                                                    isSecondary
                                                    style={{ marginRight: '8px' }}
                                                >
                                                    {__('Replace Flyer')}
                                                </Button>
                                                <Button 
                                                    onClick={removeImage}
                                                    isDestructive
                                                >
                                                    {__('Remove Flyer')}
                                                </Button>
                                            </div>
                                        </div>
                                    ) : (
                                        <Button
                                            onClick={open}
                                            isPrimary
                                        >
                                            {__('Upload Event Flyer')}
                                        </Button>
                                    )}
                                </div>
                            )}
                        />
                    </MediaUploadCheck>
                </div>

                <PanelBody
                    title={__('Location Details')}
                    initialOpen={true}
                >
                    <TextControl
                        label={__('Location Name')}
                        value={meta.location_name}
                        onChange={(value) => updateMetaValue('location_name', value)}
                        placeholder={__('e.g., Community Center')}
                        __nextHasNoMarginBottom
                    />
                    <TextControl
                        label={__('Address')}
                        value={meta.location_address}
                        onChange={(value) => updateMetaValue('location_address', value)}
                        placeholder={__('Full address')}
                        __nextHasNoMarginBottom
                    />
                    <TextControl
                        label={__('Location Details')}
                        value={meta.location_details}
                        onChange={(value) => updateMetaValue('location_details', value)}
                        placeholder={__('Parking info, entrance details, etc.')}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </div>
        </PluginDocumentSettingPanel>
    );
};

registerPlugin('mayo-event-details', {
    render: EventDetailsSidebar
}); 