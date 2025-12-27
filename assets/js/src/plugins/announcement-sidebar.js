import { registerPlugin, getPlugins } from '@wordpress/plugins';
import { EventProvider } from '../components/providers/EventProvider';
import AnnouncementEditor from '../components/admin/AnnouncementEditor';

// Only register if not already registered
const existingPlugins = getPlugins();
if (!existingPlugins.some(plugin => plugin.name === 'mayo-announcement-details')) {
    registerPlugin('mayo-announcement-details', {
        render: () => (
            <EventProvider>
                <AnnouncementEditor />
            </EventProvider>
        ),
        icon: 'megaphone'
    });
}
