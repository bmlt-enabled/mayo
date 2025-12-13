const AnnouncementBellIcon = ({ count, onClick }) => {
    return (
        <button
            className="mayo-announcement-bell"
            onClick={onClick}
            title={`${count} announcement${count !== 1 ? 's' : ''} - Click to view`}
        >
            <span className="dashicons dashicons-bell"></span>
            {count > 0 && (
                <span className="mayo-announcement-bell-badge">
                    {count > 9 ? '9+' : count}
                </span>
            )}
        </button>
    );
};

export default AnnouncementBellIcon;
