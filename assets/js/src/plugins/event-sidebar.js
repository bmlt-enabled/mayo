import { registerPlugin } from '@wordpress/plugins';
import { EventProvider } from '../components/providers/EventProvider';
import EventBlockEditorSidebar from '../components/admin/EventBlockEditorSidebar';

registerPlugin('mayo-event-details', {
    render: () => (
        <EventProvider>
            <EventBlockEditorSidebar />
        </EventProvider>
    ),
    icon: 'calendar'
}); 