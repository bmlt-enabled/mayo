import { useEventProvider } from '../providers/EventProvider';

const LocationAddress = ({ address, className = "mayo-location-address" }) => {
    if (!address) return null;

    // Check if the address contains a URL
    const urlRegex = /(https?:\/\/[^\s]+)/g;
    const urls = address.match(urlRegex);
    
    if (urls && urls.length > 0) {
        // If URL is found, link directly to it
        return (
            <a 
                href={urls[0]}
                target="_blank"
                rel="noopener noreferrer"
                className={className}
                onClick={(e) => e.stopPropagation()}
            >
                {address}
            </a>
        );
    } else {
        // If no URL, use Google Maps
        return (
            <a 
                href={`https://maps.google.com?q=${encodeURIComponent(address)}`}
                target="_blank"
                rel="noopener noreferrer"
                className={className}
                onClick={(e) => e.stopPropagation()}
            >
                {address}
            </a>
        );
    }
};

export default LocationAddress; 