import { useState, useEffect } from '@wordpress/element';
import { TextControl, Button, Panel, PanelBody, PanelRow, Spinner, Notice, ToggleControl } from '@wordpress/components';
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

const Settings = () => {
    const [settings, setSettings] = useState({
        bmlt_root_server: ''
    });
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [error, setError] = useState(null);
    const [successMessage, setSuccessMessage] = useState(null);
    const [externalSources, setExternalSources] = useState([]);
    const [isEditingSource, setIsEditingSource] = useState(null);
    const [currentSource, setCurrentSource] = useState(null);
    const [isAddingNew, setIsAddingNew] = useState(false);

    // Load settings when component mounts
    useEffect(() => {
        const loadSettings = async () => {
            try {
                setIsLoading(true);
                setError(null);
                const response = await apiFetch('/settings');
                setSettings({
                    bmlt_root_server: response.bmlt_root_server || ''
                });
                setExternalSources(response.external_sources || []);
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

            const newSources = [...externalSources];
            if (isEditingSource !== null) {
                newSources[isEditingSource] = currentSource;
            } else if (isAddingNew) {
                newSources.push(currentSource);
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
            
            const response = await apiFetch('/settings', {
                method: 'POST',
                body: JSON.stringify({
                    bmlt_root_server: settings.bmlt_root_server,
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
                        <Button 
                            isPrimary 
                            onClick={handleSave}
                            isBusy={isSaving}
                            disabled={isSaving || (settings.bmlt_root_server && !isValidHttpsUrl(settings.bmlt_root_server))}
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
                                            <strong>{source.url}</strong>
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
                            <Button
                                isPrimary
                                onClick={handleAddNewClick}
                                className="mayo-add-source-button"
                            >
                                Add New External Source
                            </Button>
                        </>
                    )}

                    {(isAddingNew || isEditingSource !== null) && currentSource && (
                        <div className="mayo-external-source-form">
                            <TextControl
                                label="Site URL"
                                value={currentSource.url}
                                onChange={(value) => setCurrentSource({...currentSource, url: value})}
                                help="Enter the URL of the WordPress site (e.g., https://example.com)"
                            />
                            <TextControl
                                label="Event Type"
                                value={currentSource.event_type}
                                onChange={(value) => setCurrentSource({...currentSource, event_type: value})}
                                help="Filter by event type (optional)"
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
                </PanelBody>
            </Panel>
        </div>
    );
};

export default Settings; 