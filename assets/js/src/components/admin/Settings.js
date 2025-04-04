import { useState, useEffect } from '@wordpress/element';
import { TextControl, Button, Panel, PanelBody, PanelRow, Spinner, Notice } from '@wordpress/components';
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

    // Save settings
    const handleSave = async () => {
        try {
            setIsSaving(true);
            setError(null);
            setSuccessMessage(null);

            // Validate HTTPS
            if (!isValidHttpsUrl(settings.bmlt_root_server)) {
                throw new Error('BMLT Root Server URL must use HTTPS protocol.');
            }
            
            const response = await apiFetch('/settings', {
                method: 'POST',
                body: JSON.stringify({
                    bmlt_root_server: settings.bmlt_root_server
                })
            });
            
            setSuccessMessage('Settings saved successfully!');
            setTimeout(() => setSuccessMessage(null), 3000);
        } catch (err) {
            setError(err.message || 'Failed to save settings. Please check your permissions and try again.');
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
        </div>
    );
};

export default Settings; 