import { render } from '@wordpress/element';
import EventForm from './components/public/EventForm';
import EventList from './components/public/EventList';
import EventArchive from './components/public/EventArchive';
import EventDetails from './components/public/EventDetails';
import { EventProvider } from './components/providers/EventProvider';

document.addEventListener('DOMContentLoaded', () => {
    const formContainer = document.getElementById('mayo-event-form');
    const listContainers = document.querySelectorAll('#mayo-event-list');
    const detailsContainer = document.getElementById('mayo-details-root');
    const archiveContainer = document.getElementById('mayo-archive-root');

    const renderWithProvider = (Component, container) => {
        if (container) {
            render(<EventProvider><Component /></EventProvider>, container);
        }
    };

    listContainers.forEach(container => {
        renderWithProvider(EventList, container);
    });

    renderWithProvider(EventForm, formContainer);
    renderWithProvider(EventDetails, detailsContainer);
    renderWithProvider(EventArchive, archiveContainer);
});