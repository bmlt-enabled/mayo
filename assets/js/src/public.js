// Public entry point
// Add any public-facing JavaScript here 

import { render } from '@wordpress/element';
import EventForm from './components/EventForm';
import EventList from './components/EventList';

document.addEventListener('DOMContentLoaded', () => {
    const formContainer = document.getElementById('mayo-event-form');
    if (formContainer) {
        render(<EventForm />, formContainer);
    }

    const listContainer = document.getElementById('mayo-event-list');
    if (listContainer) {
        render(<EventList />, listContainer);
    }
}); 