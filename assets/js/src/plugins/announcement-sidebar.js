import { registerPlugin } from '@wordpress/plugins';
import AnnouncementEditor from '../components/admin/AnnouncementEditor';

registerPlugin('mayo-announcement-details', {
    render: () => <AnnouncementEditor />,
    icon: 'megaphone'
});
