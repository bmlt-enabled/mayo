import { render } from '@wordpress/element';
import EventForm from './components/public/EventForm';
import EventList from './components/public/EventList';
import EventArchive from './components/public/EventArchive';
import EventDetails from './components/public/EventDetails';
import EventAnnouncement from './components/public/EventAnnouncement';
import { EventProvider } from './components/providers/EventProvider';

document.addEventListener('DOMContentLoaded', () => {
    const formContainer = document.getElementById('mayo-event-form');
    const listContainers = document.querySelectorAll('[id^="mayo-event-list-"]');
    const detailsContainer = document.getElementById('mayo-details-root');
    const archiveContainer = document.getElementById('mayo-archive-root');

    const renderWithProvider = (Component, container) => {
        if (!container) return;
        
        const instance = container.dataset.instance;
        const settings = window[`mayoEventSettings_${instance}`] || {};
        
        if (container.classList.contains('mayo-widget-list')) {
            render(<EventProvider><Component widget={true} settings={settings} /></EventProvider>, container);
        } else {
            render(<EventProvider><Component settings={settings} /></EventProvider>, container);
        }
    };

    listContainers.forEach(container => {
        renderWithProvider(EventList, container);
    });

    renderWithProvider(EventForm, formContainer);
    renderWithProvider(EventDetails, detailsContainer);
    renderWithProvider(EventArchive, archiveContainer);

    // Initialize announcement containers (shortcode and widget)
    const announcementContainers = document.querySelectorAll('.mayo-announcement-container');
    announcementContainers.forEach(container => {
        const instanceAttr = container.dataset.instance;
        // Handle both shortcode (numeric) and widget (widget_X) instances
        const settingsKey = `mayoAnnouncementSettings_${instanceAttr}`;
        const settings = window[settingsKey] || {};
        render(<EventProvider><EventAnnouncement settings={settings} /></EventProvider>, container);
    });
});