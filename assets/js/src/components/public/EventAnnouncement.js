import { useState, useEffect, useCallback } from '@wordpress/element';
import AnnouncementBanner from './AnnouncementBanner';
import AnnouncementModal from './AnnouncementModal';
import AnnouncementBellIcon from './AnnouncementBellIcon';
import { apiFetch } from '../../util';

const EventAnnouncement = ({ settings = {} }) => {
    const [events, setEvents] = useState([]);
    const [loading, setLoading] = useState(true);
    const [dismissed, setDismissed] = useState(false);
    const [minimized, setMinimized] = useState(false);
    const [currentIndex, setCurrentIndex] = useState(0);

    const mode = settings.mode || 'banner';
    const categories = settings.categories || '';
    const tags = settings.tags || '';
    const timeFormat = settings.timeFormat || '12hour';
    const backgroundColor = settings.backgroundColor || '';
    const textColor = settings.textColor || '';

    // Generate a unique dismissal key based on event IDs
    const getDismissalKey = useCallback((eventIds) => {
        const hash = eventIds.sort().join('-');
        return `mayo_announcement_dismissed_${hash}`;
    }, []);

    // Check if announcements have been dismissed within 24 hours
    const checkDismissed = useCallback((eventIds) => {
        if (eventIds.length === 0) return false;
        const key = getDismissalKey(eventIds);
        const dismissedTime = localStorage.getItem(key);
        if (!dismissedTime) return false;

        const timestamp = parseInt(dismissedTime, 10);
        const twentyFourHours = 24 * 60 * 60 * 1000;
        return Date.now() - timestamp < twentyFourHours;
    }, [getDismissalKey]);

    // Filter events by current date (between start_date and end_date)
    const filterByDateRange = useCallback((eventList) => {
        const now = new Date();
        now.setHours(0, 0, 0, 0);

        return eventList.filter(event => {
            if (!event.meta.event_start_date) return false;

            const startDate = new Date(event.meta.event_start_date + 'T00:00:00');
            startDate.setHours(0, 0, 0, 0);

            const endDateStr = event.meta.event_end_date || event.meta.event_start_date;
            const endDate = new Date(endDateStr + 'T00:00:00');
            endDate.setHours(23, 59, 59, 999);

            // Show if today is between start and end dates (inclusive)
            return now >= startDate && now <= endDate;
        });
    }, []);

    // Fetch announcements
    useEffect(() => {
        const fetchAnnouncements = async () => {
            setLoading(true);
            try {
                let endpoint = '/events?status=publish&per_page=20&archive=false';

                if (categories) {
                    endpoint += `&categories=${encodeURIComponent(categories)}`;
                }
                if (tags) {
                    endpoint += `&tags=${encodeURIComponent(tags)}`;
                }

                const data = await apiFetch(endpoint);
                const fetchedEvents = Array.isArray(data) ? data : (data.events || []);

                // Filter to only show events within their date range
                const activeEvents = filterByDateRange(fetchedEvents);

                setEvents(activeEvents);

                // Check if already dismissed
                const eventIds = activeEvents.map(e => e.id);
                if (checkDismissed(eventIds)) {
                    setDismissed(true);
                    setMinimized(true);
                }
            } catch (err) {
                console.error('Error fetching announcements:', err);
                setEvents([]);
            } finally {
                setLoading(false);
            }
        };

        fetchAnnouncements();
    }, [categories, tags, filterByDateRange, checkDismissed]);

    // Handle dismiss
    const handleDismiss = useCallback(() => {
        const eventIds = events.map(e => e.id);
        const key = getDismissalKey(eventIds);
        localStorage.setItem(key, Date.now().toString());
        setDismissed(true);
        setMinimized(true);
    }, [events, getDismissalKey]);

    // Handle re-open from bell icon
    const handleReopen = useCallback(() => {
        setDismissed(false);
        setMinimized(false);
        // Clear the dismissal from localStorage
        const eventIds = events.map(e => e.id);
        const key = getDismissalKey(eventIds);
        localStorage.removeItem(key);
    }, [events, getDismissalKey]);

    // Carousel navigation
    const handlePrev = useCallback(() => {
        setCurrentIndex(prev => {
            const newIndex = prev === 0 ? events.length - 1 : prev - 1;
            return newIndex;
        });
    }, [events.length]);

    const handleNext = useCallback(() => {
        setCurrentIndex(prev => {
            const newIndex = prev >= events.length - 1 ? 0 : prev + 1;
            return newIndex;
        });
    }, [events.length]);

    // Reset currentIndex if it's out of bounds when events change
    useEffect(() => {
        if (events.length > 0 && currentIndex >= events.length) {
            setCurrentIndex(0);
        }
    }, [events.length, currentIndex]);

    // Don't render anything if loading or no events
    if (loading || events.length === 0) {
        return null;
    }

    // Show bell icon when minimized
    if (minimized) {
        return (
            <AnnouncementBellIcon
                count={events.length}
                onClick={handleReopen}
                backgroundColor={backgroundColor}
                textColor={textColor}
            />
        );
    }

    // Show banner or modal based on mode
    if (mode === 'modal') {
        return (
            <AnnouncementModal
                events={events}
                timeFormat={timeFormat}
                onClose={handleDismiss}
                backgroundColor={backgroundColor}
                textColor={textColor}
            />
        );
    }

    return (
        <AnnouncementBanner
            events={events}
            currentIndex={currentIndex}
            timeFormat={timeFormat}
            onPrev={handlePrev}
            onNext={handleNext}
            onClose={handleDismiss}
            backgroundColor={backgroundColor}
            textColor={textColor}
        />
    );
};

export default EventAnnouncement;
