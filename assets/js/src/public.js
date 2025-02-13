// Public entry point
// Add any public-facing JavaScript here 

import { render } from '@wordpress/element';
import EventForm from './components/EventForm';
import EventList from './components/EventList';
import EventArchive from './components/EventArchive';
import EventDetails from './components/EventDetails';

console.log('Script loaded');

document.addEventListener('DOMContentLoaded', () => {
    console.log('Public scripts loaded');
    const formContainer = document.getElementById('mayo-event-form');
    const listContainer = document.getElementById('mayo-event-list');
    const detailsContainer = document.getElementById('mayo-details-root');
    const archiveContainer = document.getElementById('mayo-archive-root');

    if (formContainer) {
        render(<EventForm />, formContainer);
    }

    if (listContainer) {
        render(<EventList />, listContainer);
    }

    if (detailsContainer) {
        console.log('Rendering EventDetails component');
        render(<EventDetails />, detailsContainer);
    } else {
        console.error('mayo-details-root not found');
    }

    if (archiveContainer) {
        render(<EventArchive />, archiveContainer);
    }
});