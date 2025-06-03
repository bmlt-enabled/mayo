import { createContext, useState, useEffect, useContext } from 'react';
import { apiFetch } from '../../util';

export const EventContext = createContext()
export const useEventProvider = () => useContext(EventContext);
export const EventProvider = ({ children }) => {
    const [serviceBodies, setServiceBodies] = useState([])
    const [externalServiceBodies, setExternalServiceBodies] = useState({})
    const [loading, setLoading] = useState(true)

    useEffect(() => {
        const fetchServiceBodies = async () => {
            try {
                setLoading(true)
                const settings = await apiFetch('/settings');
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

    const getServiceBodyName = (id, sourceId = 'local') => {
        // If it's a local service body
        if (sourceId === 'local') {
            const serviceBody = serviceBodies.find(body => body.id === id);
            return serviceBody?.name || 'Unknown';
        }
        
        // If it's an external service body
        if (externalServiceBodies[sourceId]) {
            const serviceBody = externalServiceBodies[sourceId].find(body => body.id === id);
            return serviceBody?.name || 'Unknown';
        }
        
        return 'Unknown';
    }
    
    const updateExternalServiceBodies = (sourceId, bodies) => {
        if (!bodies || !Array.isArray(bodies) || bodies.length === 0) return;
        
        setExternalServiceBodies(prev => ({
            ...prev,
            [sourceId]: bodies
        }));
    }
    
    return (
        <EventContext.Provider value={{ 
            serviceBodies, 
            getServiceBodyName, 
            updateExternalServiceBodies 
        }}>
            {loading ? <div>Loading...</div> : children}
        </EventContext.Provider>
    )
}
