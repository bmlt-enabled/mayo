import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import { useSelect, useDispatch } from '@wordpress/data';
import { TextControl, SelectControl, Button, PanelBody } from '@wordpress/components';
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

            <TextControl
                label="Date"
                type="date"
                value={meta.event_date}
                onChange={value => updateMetaValue('event_date', value)}
            />

            <TextControl
                label="Start Time"
                type="time"
                value={meta.event_start_time}
                onChange={value => updateMetaValue('event_start_time', value)}
            />

            <TextControl
                label="End Time"
                type="time"
                value={meta.event_end_time}
                onChange={value => updateMetaValue('event_end_time', value)}
            />

            <TextControl
                label="Recurring Schedule"
                value={meta.recurring_schedule}
                onChange={(value) => updateMetaValue('recurring_schedule', value)}
            />
            
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