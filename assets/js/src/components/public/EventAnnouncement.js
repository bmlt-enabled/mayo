import { useState, useEffect, useCallback } from '@wordpress/element';
import AnnouncementBanner from './AnnouncementBanner';
import AnnouncementModal from './AnnouncementModal';
import AnnouncementBellIcon from './AnnouncementBellIcon';
import { apiFetch } from '../../util';

const EventAnnouncement = ({ settings = {} }) => {
    const [announcements, setAnnouncements] = useState([]);
    const [loading, setLoading] = useState(true);
    const [dismissed, setDismissed] = useState(false);
    const [minimized, setMinimized] = useState(false);
    const [currentIndex, setCurrentIndex] = useState(0);

    const mode = settings.mode || 'banner';
    const categories = settings.categories || '';
    const tags = settings.tags || '';
    const priority = settings.priority || '';
    const timeFormat = settings.timeFormat || '12hour';
    const backgroundColor = settings.backgroundColor || '';
    const textColor = settings.textColor || '';

    // Generate a unique dismissal key based on announcement IDs
    const getDismissalKey = useCallback((announcementIds) => {
        const hash = announcementIds.sort().join('-');
        return `mayo_announcement_dismissed_${hash}`;
    }, []);

    // Check if announcements have been dismissed within 24 hours
    const checkDismissed = useCallback((announcementIds) => {
        if (announcementIds.length === 0) return false;
        const key = getDismissalKey(announcementIds);
        const dismissedTime = localStorage.getItem(key);
        if (!dismissedTime) return false;

        const timestamp = parseInt(dismissedTime, 10);
        const twentyFourHours = 24 * 60 * 60 * 1000;
        return Date.now() - timestamp < twentyFourHours;
    }, [getDismissalKey]);

    // Fetch announcements from the new announcements API
    useEffect(() => {
        const fetchAnnouncements = async () => {
            setLoading(true);
            try {
                // Use the new announcements endpoint which handles date filtering server-side
                let endpoint = '/announcements?per_page=20';

                if (categories) {
                    endpoint += `&categories=${encodeURIComponent(categories)}`;
                }
                if (tags) {
                    endpoint += `&tags=${encodeURIComponent(tags)}`;
                }
                if (priority) {
                    endpoint += `&priority=${encodeURIComponent(priority)}`;
                }

                const data = await apiFetch(endpoint);
                const fetchedAnnouncements = Array.isArray(data) ? data : (data.announcements || []);

                setAnnouncements(fetchedAnnouncements);

                // Check if already dismissed
                const announcementIds = fetchedAnnouncements.map(a => a.id);
                if (checkDismissed(announcementIds)) {
                    setDismissed(true);
                    setMinimized(true);
                }
            } catch (err) {
                console.error('Error fetching announcements:', err);
                setAnnouncements([]);
            } finally {
                setLoading(false);
            }
        };

        fetchAnnouncements();
    }, [categories, tags, priority, checkDismissed]);

    // Handle dismiss
    const handleDismiss = useCallback(() => {
        const announcementIds = announcements.map(a => a.id);
        const key = getDismissalKey(announcementIds);
        localStorage.setItem(key, Date.now().toString());
        setDismissed(true);
        setMinimized(true);
    }, [announcements, getDismissalKey]);

    // Handle re-open from bell icon
    const handleReopen = useCallback(() => {
        setDismissed(false);
        setMinimized(false);
        // Clear the dismissal from localStorage
        const announcementIds = announcements.map(a => a.id);
        const key = getDismissalKey(announcementIds);
        localStorage.removeItem(key);
    }, [announcements, getDismissalKey]);

    // Carousel navigation
    const handlePrev = useCallback(() => {
        setCurrentIndex(prev => {
            const newIndex = prev === 0 ? announcements.length - 1 : prev - 1;
            return newIndex;
        });
    }, [announcements.length]);

    const handleNext = useCallback(() => {
        setCurrentIndex(prev => {
            const newIndex = prev >= announcements.length - 1 ? 0 : prev + 1;
            return newIndex;
        });
    }, [announcements.length]);

    // Reset currentIndex if it's out of bounds when announcements change
    useEffect(() => {
        if (announcements.length > 0 && currentIndex >= announcements.length) {
            setCurrentIndex(0);
        }
    }, [announcements.length, currentIndex]);

    // Don't render anything if loading or no announcements
    if (loading || announcements.length === 0) {
        return null;
    }

    // Show bell icon when minimized
    if (minimized) {
        return (
            <AnnouncementBellIcon
                count={announcements.length}
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
                announcements={announcements}
                timeFormat={timeFormat}
                onClose={handleDismiss}
                backgroundColor={backgroundColor}
                textColor={textColor}
            />
        );
    }

    return (
        <AnnouncementBanner
            announcements={announcements}
            currentIndex={currentIndex}
            onPrev={handlePrev}
            onNext={handleNext}
            onClose={handleDismiss}
            backgroundColor={backgroundColor}
            textColor={textColor}
        />
    );
};

export default EventAnnouncement;
