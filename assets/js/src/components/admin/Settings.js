import { useState, useEffect } from '@wordpress/element';
import { TextControl, Button, Panel, PanelBody, PanelRow, Spinner, Notice, ToggleControl, SelectControl } from '@wordpress/components';
import { apiFetch } from '../../util';

// Add URL validation function
const isValidHttpsUrl = (url) => {
    if (!url) return false;
    try {
        const urlObj = new URL(url);
        return urlObj.protocol === 'https:';
    } catch {
        return false;
    }
};

// Add email validation function
const isValidEmail = (email) => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
};

// Add function to validate multiple emails
const isValidEmailList = (emailList) => {
    if (!emailList) return true; // Empty is valid (will use admin email)
    
    // Split by comma or semicolon
    const emails = emailList.split(/[,;]/).map(email => email.trim()).filter(email => email);
    
    // Check if all emails are valid
    return emails.every(email => isValidEmail(email));
};

const Settings = () => {
    const [settings, setSettings] = useState({
        bmlt_root_server: '',
        cache_duration: 60, // Default 60 seconds (1 minute)
        notification_email: '' // Add notification email setting
    });
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [isPurgingCache, setIsPurgingCache] = useState(false);
    const [error, setError] = useState(null);
    const [successMessage, setSuccessMessage] = useState(null);
    const [externalSources, setExternalSources] = useState([]);
    const [isEditingSource, setIsEditingSource] = useState(null);
    const [currentSource, setCurrentSource] = useState(null);
    const [isAddingNew, setIsAddingNew] = useState(false);
    const [cacheStatus, setCacheStatus] = useState([]);

    // Load settings when component mounts
    useEffect(() => {
        const loadSettings = async () => {
            try {
                setIsLoading(true);
                setError(null);
                const response = await apiFetch('/settings');
                setSettings({
                    bmlt_root_server: response.bmlt_root_server || '',
                    cache_duration: response.cache_duration || 60, // Default 60 seconds if not set
                    notification_email: response.notification_email || '' // Add notification email
                });
                setExternalSources(Array.isArray(response.external_sources) ? response.external_sources : []);
                
                // Load cache status
                const cacheResponse = await apiFetch('/cache');
                setCacheStatus(cacheResponse);
            } catch (err) {
                setError('Failed to load settings. Please refresh the page and try again.');
            } finally {
                setIsLoading(false);
            }
        };
        
        loadSettings();
    }, []);

    // Handle settings changes
    const handleChange = (field, value) => {
        setSettings(prev => ({
            ...prev,
            [field]: value
        }));
    };

    const handleAddNewClick = () => {
        setCurrentSource({
            id: '', // Will be generated on the server
            name: '', // Add name field
            url: '',
            event_type: '',
            service_body: '',
            categories: '',
            tags: '',
            enabled: true
        });
        setIsAddingNew(true);
    };

    const handleEditSource = (source, index) => {
        setCurrentSource(source);
        setIsEditingSource(index);
    };

    const handleSaveSource = async () => {
        try {
            setIsSaving(true);
            setError(null);

            if (!isValidHttpsUrl(currentSource.url)) {
                throw new Error('External source URL must use HTTPS protocol.');
            }

            // Ensure event_type is set
            const sourceToSave = {
                ...currentSource,
                event_type: currentSource.event_type || ''
            };
            
            const newSources = [...externalSources];
            if (isEditingSource !== null) {
                newSources[isEditingSource] = sourceToSave;
            } else if (isAddingNew) {
                newSources.push(sourceToSave);
            }

            const response = await apiFetch('/settings', {
                method: 'POST',
                body: JSON.stringify({
                    ...settings,
                    external_sources: newSources
                })
            });

            // Update sources with server response to get new IDs
            setExternalSources(response.settings.external_sources);
            setCurrentSource(null);
            setIsEditingSource(null);
            setIsAddingNew(false);
            setSuccessMessage('External source saved successfully!');
            setTimeout(() => setSuccessMessage(null), 3000);
        } catch (err) {
            setError(err.message || 'Failed to save external source.');
        } finally {
            setIsSaving(false);
        }
    };

    const handleDeleteSource = async (index) => {
        if (!confirm('Are you sure you want to delete this external source?')) return;

        try {
            setIsSaving(true);
            const newSources = externalSources.filter((_, i) => i !== index);
            
            await apiFetch('/settings', {
                method: 'POST',
                body: JSON.stringify({
                    ...settings,
                    external_sources: newSources
                })
            });

            setExternalSources(newSources);
            setCurrentSource(null);
            setIsEditingSource(null);
            setIsAddingNew(false);
            setSuccessMessage('External source deleted successfully!');
            setTimeout(() => setSuccessMessage(null), 3000);
        } catch (err) {
            setError('Failed to delete external source.');
        } finally {
            setIsSaving(false);
        }
    };

    const handleSave = async () => {
        try {
            setIsSaving(true);
            setError(null);
            
            // Validate HTTPS for BMLT root server
            if (!isValidHttpsUrl(settings.bmlt_root_server)) {
                throw new Error('BMLT Root Server URL must use HTTPS protocol.');
            }
            
            // Validate cache duration is a positive number
            if (isNaN(settings.cache_duration) || settings.cache_duration < 0) {
                throw new Error('Cache duration must be a positive number.');
            }
            
            // Validate notification email if provided
            if (settings.notification_email && !isValidEmailList(settings.notification_email)) {
                throw new Error('Please enter valid email addresses for notifications. Multiple emails can be separated by commas or semicolons.');
            }
            
            const response = await apiFetch('/settings', {
                method: 'POST',
                body: JSON.stringify({
                    bmlt_root_server: settings.bmlt_root_server,
                    cache_duration: parseInt(settings.cache_duration, 10),
                    notification_email: settings.notification_email,
                    external_sources: externalSources
                })
            });
            
            setSuccessMessage('Settings saved successfully!');
            setTimeout(() => setSuccessMessage(null), 3000);
        } catch (err) {
            setError(err.message || 'Failed to save settings.');
        } finally {
            setIsSaving(false);
        }
    };

    const handlePurgeCache = async () => {
        try {
            setIsPurgingCache(true);
            setError(null);
            
            await apiFetch('/cache', {
                method: 'POST'
            });
            
            // Refresh cache status after purging
            const cacheResponse = await apiFetch('/cache');
            setCacheStatus(cacheResponse);
            
            setSuccessMessage('External events cache purged successfully!');
            setTimeout(() => setSuccessMessage(null), 3000);
        } catch (err) {
            setError('Failed to purge cache. Please try again.');
        } finally {
            setIsPurgingCache(false);
        }
    };

    if (isLoading) {
        return (
            <div className="mayo-settings-loading">
                <Spinner /> Loading settings...
            </div>
        );
    }

    return (
        <div className="mayo-settings-page">
            <h1>Mayo Events Manager Settings</h1>
            
            {error && (
                <Notice status="error" isDismissible={true} onRemove={() => setError(null)}>
                    {error}
                </Notice>
            )}
            
            {successMessage && (
                <Notice status="success" isDismissible={true} onRemove={() => setSuccessMessage(null)}>
                    {successMessage}
                </Notice>
            )}
            
            <Panel>
                <PanelBody title="BMLT Settings" initialOpen={true}>
                    <PanelRow>
                        <TextControl
                            label="BMLT Root Server URL"
                            value={settings.bmlt_root_server}
                            onChange={(value) => handleChange('bmlt_root_server', value)}
                            help={
                                settings.bmlt_root_server && !isValidHttpsUrl(settings.bmlt_root_server)
                                    ? "URL must start with 'https://'"
                                    : "Enter the URL of your BMLT root server (e.g., https://bmlt.example.org/main_server)"
                            }
                            className={
                                settings.bmlt_root_server && !isValidHttpsUrl(settings.bmlt_root_server)
                                    ? 'mayo-invalid-url'
                                    : ''
                            }
                        />
                    </PanelRow>
                    
                    <PanelRow>
                        <TextControl
                            label="Notification Email"
                            value={settings.notification_email}
                            onChange={(value) => handleChange('notification_email', value)}
                            help={
                                settings.notification_email && !isValidEmailList(settings.notification_email)
                                    ? "Please enter valid email addresses. Multiple emails can be separated by commas or semicolons."
                                    : "Email addresses to receive event submission notifications. Multiple emails can be separated by commas or semicolons. Leave empty to send to all admins."
                            }
                            className={
                                settings.notification_email && !isValidEmailList(settings.notification_email)
                                    ? 'mayo-invalid-email'
                                    : ''
                            }
                        />
                    </PanelRow>
                    
                    <PanelRow>
                        <Button 
                            isPrimary 
                            onClick={handleSave}
                            isBusy={isSaving}
                            disabled={isSaving || 
                                (settings.bmlt_root_server && !isValidHttpsUrl(settings.bmlt_root_server)) ||
                                (settings.notification_email && !isValidEmailList(settings.notification_email))}
                        >
                            {isSaving ? 'Saving...' : 'Save Settings'}
                        </Button>
                    </PanelRow>
                </PanelBody>
            </Panel>
            
            <Panel>
                <PanelBody title="External Event Sources" initialOpen={true}>
                    {!isAddingNew && !isEditingSource && (
                        <>
                            <div className="mayo-external-sources-list">
                                {externalSources.map((source, index) => (
                                    <div key={source.id} className="mayo-external-source-item">
                                        <div className="mayo-external-source-info">
                                            <strong>{source.name || source.url}</strong>
                                            <div className="mayo-external-source-details">
                                                <span className="mayo-source-id">ID: {source.id}</span>
                                                {source.event_type && <span>Type: {source.event_type}</span>}
                                                {source.service_body && <span>Service Body: {source.service_body}</span>}
                                                <span className={`mayo-source-status ${source.enabled ? 'enabled' : 'disabled'}`}>
                                                    {source.enabled ? 'Enabled' : 'Disabled'}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="mayo-external-source-actions">
                                            <Button
                                                isSecondary
                                                onClick={() => handleEditSource(source, index)}
                                            >
                                                Edit
                                            </Button>
                                            <Button
                                                isDestructive
                                                onClick={() => handleDeleteSource(index)}
                                            >
                                                Delete
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <div className="mayo-external-sources-actions">
                                <Button
                                    isPrimary
                                    onClick={handleAddNewClick}
                                    className="mayo-add-source-button"
                                >
                                    Add New External Source
                                </Button>
                            </div>
                            
                            {(isAddingNew || isEditingSource !== null) && currentSource && (
                                <div className="mayo-external-source-form">
                                    <TextControl
                                        label="Site URL"
                                        value={currentSource.url}
                                        onChange={(value) => setCurrentSource({...currentSource, url: value})}
                                        help="Enter the URL of the WordPress site (e.g., https://example.com)"
                                    />
                                    <TextControl
                                        label="Source Name"
                                        value={currentSource.name}
                                        onChange={(value) => setCurrentSource({...currentSource, name: value})}
                                        help="Enter a friendly name for this source (e.g., District 5 Website)"
                                    />
                                    <SelectControl
                                        label="Event Type"
                                        value={currentSource.event_type || ''}
                                        options={[
                                            { label: 'Select an event type', value: '' },
                                            { label: 'Activity', value: 'Activity' },
                                            { label: 'Service', value: 'Service' }
                                        ]}
                                        onChange={(value) => {
                                            setCurrentSource({...currentSource, event_type: value});
                                        }}
                                        help="Select the event type"
                                    />
                                    <TextControl
                                        label="Service Body"
                                        value={currentSource.service_body}
                                        onChange={(value) => setCurrentSource({...currentSource, service_body: value})}
                                        help="Filter by service body (optional)"
                                    />
                                    <TextControl
                                        label="Categories"
                                        value={currentSource.categories}
                                        onChange={(value) => setCurrentSource({...currentSource, categories: value})}
                                        help="Filter by categories (comma-separated)"
                                    />
                                    <TextControl
                                        label="Tags"
                                        value={currentSource.tags}
                                        onChange={(value) => setCurrentSource({...currentSource, tags: value})}
                                        help="Filter by tags (comma-separated)"
                                    />
                                    <ToggleControl
                                        label="Enable Source"
                                        checked={currentSource.enabled}
                                        onChange={(value) => setCurrentSource({...currentSource, enabled: value})}
                                    />
                                    <div className="mayo-form-actions">
                                        <Button
                                            isPrimary
                                            onClick={handleSaveSource}
                                            isBusy={isSaving}
                                        >
                                            {isSaving ? 'Saving...' : 'Save Source'}
                                        </Button>
                                        <Button
                                            isSecondary
                                            onClick={() => {
                                                setCurrentSource(null);
                                                setIsEditingSource(null);
                                                setIsAddingNew(false);
                                            }}
                                        >
                                            Cancel
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </PanelBody>
            </Panel>
            
            <Panel>
                <PanelBody title="Cache Settings" initialOpen={true}>
                    <TextControl
                        type="number"
                        label="External Events Cache Duration (seconds)"
                        value={settings.cache_duration}
                        onChange={(value) => handleChange('cache_duration', value)}
                        help="How long to cache external events before fetching fresh data. Default is 60 seconds (1 minute)."
                        min="0"
                    />
                    <div className="mayo-cache-actions">
                        <Button 
                            isPrimary 
                            onClick={handleSave}
                            isBusy={isSaving}
                            disabled={isSaving}
                        >
                            {isSaving ? 'Saving...' : 'Save Cache Settings'}
                        </Button>
                        <Button
                            isSecondary
                            onClick={handlePurgeCache}
                            isBusy={isPurgingCache}
                            className="mayo-purge-cache-button"
                        >
                            {isPurgingCache ? 'Purging...' : 'Purge External Events Cache'}
                        </Button>
                    </div>
                </PanelBody>
            </Panel>
        </div>
    );
};

export default Settings; 