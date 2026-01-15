const AnnouncementBellIcon = ({ count, onClick, backgroundColor, textColor }) => {
    // Build custom styles if colors are provided
    const customStyle = {};
    if (backgroundColor) customStyle.background = backgroundColor;
    if (textColor) customStyle.color = textColor;

    return (
        <button
            className="mayo-announcement-bell"
            onClick={onClick}
            title={`${count} announcement${count !== 1 ? 's' : ''} - Click to view`}
            style={customStyle}
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
