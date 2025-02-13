// Public entry point
// Add any public-facing JavaScript here 

import { render } from '@wordpress/element';
import EventForm from './components/EventForm';
import EventList from './components/EventList';
import EventArchive from './components/EventArchive';
import EventDetails from './components/EventDetails';

document.addEventListener('DOMContentLoaded', () => {
    const formContainer = document.getElementById('mayo-event-form');
    const listContainer = document.getElementById('mayo-event-list');
    const archiveContainer = document.getElementById('mayo-archive-root');
    const detailsContainer = document.getElementById('mayo-details-root');

    if (formContainer) {
        render(<EventForm />, formContainer);
    }

    if (listContainer) {
        render(<EventList />, listContainer);
    }

    if (archiveContainer) {
        render(<EventArchive />, archiveContainer);
    }

    if (detailsContainer) {
        render(<EventDetails />, detailsContainer);
    }
}); 