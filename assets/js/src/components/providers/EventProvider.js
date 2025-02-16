import { createContext, useState, useEffect, useContext } from 'react';
import apiFetch from '@wordpress/api-fetch';

export const EventContext = createContext()
export const useEventProvider = () => useContext(EventContext);
export const EventProvider = ({ children }) => {
    const [serviceBodies, setServiceBodies] = useState([])
    const [loading, setLoading] = useState(true)

    useEffect(() => {
        const fetchServiceBodies = async () => {
            try {
                setLoading(true)
                const settings = await apiFetch({ path: '/wp-json/event-manager/v1/settings' });
                const bmltRootServer = settings.bmlt_root_server;
                if (!bmltRootServer) {
                    throw new Error('BMLT root server URL not set');
                }

                const response = await fetch(`${bmltRootServer}/client_interface/json/?switcher=GetServiceBodies`);
                const data = await response.json();
                const sortedServiceBodies = data.sort((a, b) => a.name.localeCompare(b.name));
                setServiceBodies(sortedServiceBodies);
            } catch (err) {
                console.error('Error fetching service bodies:', err);
                setError('Failed to load service bodies');
            } finally {
                setLoading(false);
            }
        };
        fetchServiceBodies();
    }, [])

    const getServiceBodyName = (id) => {
        return serviceBodies.find(body => body.id === id)?.name || 'Unknown';
    }
    
    return (
        <EventContext.Provider value={{ serviceBodies, getServiceBodyName }}>
            {loading ? <div>Loading...</div> : children}
        </EventContext.Provider>
    )
}
