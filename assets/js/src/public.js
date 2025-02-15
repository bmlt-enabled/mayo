// Public entry point
// Add any public-facing JavaScript here 

import { render } from '@wordpress/element';
import EventForm from './components/public/EventForm';
import EventList from './components/public/EventList';
import EventArchive from './components/public/EventArchive';
import EventDetails from './components/public/EventDetails';


document.addEventListener('DOMContentLoaded', () => {
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
        render(<EventDetails />, detailsContainer);
    } else {
    }

    if (archiveContainer) {
        render(<EventArchive />, archiveContainer);
    }
});