import { useState, useEffect } from '@wordpress/element';
import { TextControl, Button, Panel, PanelBody, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const Settings = () => {
    const [rootServer, setRootServer] = useState('');
    const [isSaving, setIsSaving] = useState(false);
    const [saveStatus, setSaveStatus] = useState(null);

    useEffect(() => {
        // Fetch initial settings
        apiFetch({ path: '/event-manager/v1/settings' }).then(response => {
            setRootServer(response.bmlt_root_server || '');
        });
    }, []);

    const handleSave = async () => {
        setIsSaving(true);
        setSaveStatus(null);

        try {
            await apiFetch({
                path: '/event-manager/v1/settings',
                method: 'POST',
                data: { bmlt_root_server: rootServer }
            });
            setSaveStatus({ type: 'success', message: 'Settings saved successfully!' });
        } catch (error) {
            console.error('Settings save error:', error);
            setSaveStatus({ type: 'error', message: 'Failed to save settings.' });
        } finally {
            setIsSaving(false);
        }
    };

    return (
        <div className="mayo-settings-wrapper">
            <h1>Settings</h1>
            
            {saveStatus && (
                <Notice 
                    status={saveStatus.type}
                    isDismissible={false}
                    className="mayo-settings-notice"
                >
                    {saveStatus.message}
                </Notice>
            )}

            <Panel>
                <PanelBody
                    title="BMLT Settings"
                    initialOpen={true}
                >
                    <TextControl
                        label="BMLT Root Server URL"
                        value={rootServer}
                        onChange={setRootServer}
                        help="Enter the full URL to your BMLT root server (e.g., https://bmlt.example.org/main_server)"
                        type="url"
                    />
                    <Button
                        isPrimary
                        onClick={handleSave}
                        isBusy={isSaving}
                        disabled={isSaving}
                    >
                        Save Settings
                    </Button>
                </PanelBody>
            </Panel>
        </div>
    );
};

export default Settings; 