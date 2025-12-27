import { registerPlugin } from '@wordpress/plugins';
import { EventProvider } from '../components/providers/EventProvider';
import AnnouncementEditor from '../components/admin/AnnouncementEditor';

registerPlugin('mayo-announcement-details', {
    render: () => (
        <EventProvider>
            <AnnouncementEditor />
        </EventProvider>
    ),
    icon: 'megaphone'
});
