import { registerPlugin, getPlugins } from '@wordpress/plugins';
import { EventProvider } from '../components/providers/EventProvider';
import EventBlockEditorSidebar from '../components/admin/EventBlockEditorSidebar';

// Only register if not already registered
const existingPlugins = getPlugins();
if (!existingPlugins.some(plugin => plugin.name === 'mayo-event-details')) {
    registerPlugin('mayo-event-details', {
        render: () => (
            <EventProvider>
                <EventBlockEditorSidebar />
            </EventProvider>
        ),
        icon: 'calendar'
    });
} 