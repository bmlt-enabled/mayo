import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TextControl, Button, Panel, PanelBody, PanelRow, Spinner, Notice, ToggleControl, SelectControl, CheckboxControl, RadioControl } from '@wordpress/components';
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

// Build hierarchical tree structure from service bodies
const buildServiceBodyTree = (serviceBodies) => {
    // Sort alphabetically first
    const sorted = [...serviceBodies].sort((a, b) =>
        a.name.localeCompare(b.name)
    );

    // Build parent-child map
    const childrenMap = {};
    const roots = [];

    sorted.forEach(sb => {
        childrenMap[sb.id] = [];
    });

    sorted.forEach(sb => {
        const parentId = String(sb.parent_id);
        if (parentId === '0' || !childrenMap[parentId]) {
            roots.push(sb);
        } else {
            childrenMap[parentId].push(sb);
        }
    });

    // Flatten tree with depth info
    const flatten = (items, depth = 0) => {
        let result = [];
        items.forEach(item => {
            result.push({ ...item, depth });
            if (childrenMap[item.id]?.length > 0) {
                result = result.concat(flatten(childrenMap[item.id], depth + 1));
            }
        });
        return result;
    };

    return flatten(roots);
};

const Settings = () => {
    const [settings, setSettings] = useState({
        bmlt_root_server: '',
        notification_email: '', // Add notification email setting
        default_service_bodies: '' // Add default service bodies setting
    });
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [isTesting, setIsTesting] = useState(false);
    const [error, setError] = useState(null);
    const [successMessage, setSuccessMessage] = useState(null);
    const [externalSources, setExternalSources] = useState([]);
    const [isEditingSource, setIsEditingSource] = useState(null);
    const [currentSource, setCurrentSource] = useState(null);
    const [isAddingNew, setIsAddingNew] = useState(false);

    // Subscription settings
    const [subscriptionSettings, setSubscriptionSettings] = useState({
        subscription_categories: [],
        subscription_tags: [],
        subscription_service_bodies: [],
        subscription_new_option_behavior: 'auto_include'
    });
    const [allCategories, setAllCategories] = useState([]);
    const [allTags, setAllTags] = useState([]);
    const [allServiceBodies, setAllServiceBodies] = useState([]);

    // Load settings when component mounts
    useEffect(() => {
        const loadSettings = async () => {
            try {
                setIsLoading(true);
                setError(null);
                const response = await apiFetch('/settings');
                setSettings({
                    bmlt_root_server: response.bmlt_root_server || '',
                    notification_email: response.notification_email || '',
                    default_service_bodies: response.default_service_bodies || '',
                    server_info: response.server_info || null
                });
                setExternalSources(Array.isArray(response.external_sources) ? response.external_sources : []);

                // Load subscription settings
                setSubscriptionSettings({
                    subscription_categories: response.subscription_categories || [],
                    subscription_tags: response.subscription_tags || [],
                    subscription_service_bodies: response.subscription_service_bodies || [],
                    subscription_new_option_behavior: response.subscription_new_option_behavior || 'opt_in'
                });

                // Fetch all categories
                try {
                    const catResponse = await fetch('/wp-json/wp/v2/categories?per_page=100');
                    if (catResponse.ok) {
                        const cats = await catResponse.json();
                        setAllCategories(cats.map(c => ({ id: c.id, name: c.name })));
                    }
                } catch (e) {
                    console.error('Failed to load categories:', e);
                }

                // Fetch all tags
                try {
                    const tagResponse = await fetch('/wp-json/wp/v2/tags?per_page=100');
                    if (tagResponse.ok) {
                        const tags = await tagResponse.json();
                        setAllTags(tags.map(t => ({ id: t.id, name: t.name })));
                    }
                } catch (e) {
                    console.error('Failed to load tags:', e);
                }

                // Fetch service bodies from BMLT
                if (response.bmlt_root_server) {
                    try {
                        const sbResponse = await fetch(response.bmlt_root_server + '/client_interface/json/?switcher=GetServiceBodies');
                        if (sbResponse.ok) {
                            const bodies = await sbResponse.json();
                            setAllServiceBodies(bodies.map(b => ({
                                id: b.id,
                                name: b.name,
                                parent_id: b.parent_id || '0'
                            })));
                        }
                    } catch (e) {
                        console.error('Failed to load service bodies:', e);
                    }
                }
            } catch (err) {
                setError(__('Failed to load settings. Please refresh the page and try again.', 'mayo-events-manager'));
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
                throw new Error(__('External source URL must use HTTPS protocol.', 'mayo-events-manager'));
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
            setSuccessMessage(__('External source saved successfully!', 'mayo-events-manager'));
            setTimeout(() => setSuccessMessage(null), 3000);
        } catch (err) {
            setError(err.message || __('Failed to save external source.', 'mayo-events-manager'));
        } finally {
            setIsSaving(false);
        }
    };

    const handleCopyId = async (id) => {
        try {
            await navigator.clipboard.writeText(id);
        } catch (err) {
            const textArea = document.createElement('textarea');
            textArea.value = id;
            document.body.appendChild(textArea);
            textArea.select();
            try { document.execCommand('copy'); } catch (e) {}
            document.body.removeChild(textArea);
        }
    };

    const handleDeleteSource = async (index) => {
        if (!confirm(__('Are you sure you want to delete this external source?', 'mayo-events-manager'))) return;

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
            setSuccessMessage(__('External source deleted successfully!', 'mayo-events-manager'));
            setTimeout(() => setSuccessMessage(null), 3000);
        } catch (err) {
            setError(__('Failed to delete external source.', 'mayo-events-manager'));
        } finally {
            setIsSaving(false);
        }
    };

    // Handle subscription setting changes
    const handleSubscriptionChange = (field, value) => {
        setSubscriptionSettings(prev => ({
            ...prev,
            [field]: value
        }));
    };

    const toggleSubscriptionOption = (field, id) => {
        setSubscriptionSettings(prev => {
            const current = prev[field] || [];
            const exists = current.includes(id);
            return {
                ...prev,
                [field]: exists
                    ? current.filter(item => item !== id)
                    : [...current, id]
            };
        });
    };

    const handleSave = async () => {
        try {
            setIsSaving(true);
            setError(null);

            // Validate HTTPS for BMLT root server
            if (!isValidHttpsUrl(settings.bmlt_root_server)) {
                throw new Error(__('BMLT Root Server URL must use HTTPS protocol.', 'mayo-events-manager'));
            }

            // Validate notification email if provided
            if (settings.notification_email && !isValidEmailList(settings.notification_email)) {
                throw new Error(__('Please enter valid email addresses for notifications. Multiple emails can be separated by commas or semicolons.', 'mayo-events-manager'));
            }

            const response = await apiFetch('/settings', {
                method: 'POST',
                body: JSON.stringify({
                    bmlt_root_server: settings.bmlt_root_server,
                    notification_email: settings.notification_email,
                    default_service_bodies: settings.default_service_bodies,
                    external_sources: externalSources,
                    subscription_categories: subscriptionSettings.subscription_categories,
                    subscription_tags: subscriptionSettings.subscription_tags,
                    subscription_service_bodies: subscriptionSettings.subscription_service_bodies,
                    subscription_new_option_behavior: subscriptionSettings.subscription_new_option_behavior
                })
            });

            setSuccessMessage(__('Settings saved successfully!', 'mayo-events-manager'));
            setTimeout(() => setSuccessMessage(null), 3000);
        } catch (err) {
            setError(err.message || __('Failed to save settings.', 'mayo-events-manager'));
        } finally {
            setIsSaving(false);
        }
    };

    const handleTestConnection = async () => {
        try {
            setIsTesting(true);
            setError(null);
            setSuccessMessage(null);

            if (!isValidHttpsUrl(settings.bmlt_root_server)) {
                throw new Error(__('BMLT Root Server URL must use HTTPS protocol.', 'mayo-events-manager'));
            }

            await apiFetch('/validate-root-server', {
                method: 'POST',
                body: JSON.stringify({ bmlt_root_server: settings.bmlt_root_server })
            });

            setSuccessMessage(__('Successfully connected to the BMLT root server.', 'mayo-events-manager'));
            setTimeout(() => setSuccessMessage(null), 3000);
        } catch (err) {
            setError(err.message || __('Could not reach the BMLT root server.', 'mayo-events-manager'));
        } finally {
            setIsTesting(false);
        }
    };

    if (isLoading) {
        return (
            <div className="mayo-settings-loading">
                <Spinner /> {__('Loading settings...', 'mayo-events-manager')}
            </div>
        );
    }

    return (
        <div className="mayo-settings-page">
            <h1>{__('Mayo Events Manager Settings', 'mayo-events-manager')}</h1>
            
            <Notice status="warning" isDismissible={false}>
                <p><strong>Important:</strong> This plugin requires Pretty Permalinks to be enabled for the REST API to function correctly. If you're experiencing 404 errors when accessing external source or settings  BMLT server, please ensure your WordPress site is using Pretty Permalinks (Settings → Permalinks) and not the "Plain" setting.</p>
            </Notice>

            {settings.server_info && !settings.server_info.curl_available && (
                <Notice status="warning" isDismissible={false}>
                    <p><strong>Performance Warning:</strong> The PHP curl extension is not installed. External source requests will be significantly slower. Ask your hosting provider to install the php-curl extension for PHP {settings.server_info.php_version}.</p>
                </Notice>
            )}

            {settings.server_info && settings.server_info.curl_available && (
                <Notice status="success" isDismissible={false}>
                    <p>PHP {settings.server_info.php_version} with curl {settings.server_info.curl_version} detected.</p>
                </Notice>
            )}
            
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
                <PanelBody title={__('BMLT Settings', 'mayo-events-manager')} initialOpen={true}>
                    <PanelRow>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', width: '100%' }}>
                            <div style={{ flex: 1 }}>
                                <TextControl
                                    label={__('BMLT Root Server URL', 'mayo-events-manager')}
                                    value={settings.bmlt_root_server}
                                    onChange={(value) => handleChange('bmlt_root_server', value)}
                                    help={
                                        settings.bmlt_root_server && !isValidHttpsUrl(settings.bmlt_root_server)
                                            ? __("URL must start with 'https://'", 'mayo-events-manager')
                                            : __('Enter the URL of your BMLT root server (e.g., https://bmlt.example.org/main_server)', 'mayo-events-manager')
                                    }
                                    className={
                                        settings.bmlt_root_server && !isValidHttpsUrl(settings.bmlt_root_server)
                                            ? 'mayo-invalid-url'
                                            : ''
                                    }
                                    __next40pxDefaultSize={true}
                                />
                            </div>
                            <Button
                                isSecondary
                                onClick={handleTestConnection}
                                isBusy={isTesting}
                                disabled={isTesting || !isValidHttpsUrl(settings.bmlt_root_server)}
                            >
                                {isTesting ? __('Testing…', 'mayo-events-manager') : __('Test connection', 'mayo-events-manager')}
                            </Button>
                        </div>
                    </PanelRow>

                    <PanelRow>
                        <TextControl
                            label={__('Notification Email', 'mayo-events-manager')}
                            value={settings.notification_email}
                            onChange={(value) => handleChange('notification_email', value)}
                            help={
                                settings.notification_email && !isValidEmailList(settings.notification_email)
                                    ? __('Please enter valid email addresses. Multiple emails can be separated by commas or semicolons.', 'mayo-events-manager')
                                    : __('Email addresses to receive event submission notifications. Multiple emails can be separated by commas or semicolons. Leave empty to send to all admins.', 'mayo-events-manager')
                            }
                            className={
                                settings.notification_email && !isValidEmailList(settings.notification_email)
                                    ? 'mayo-invalid-email'
                                    : ''
                            }
                            __next40pxDefaultSize={true}
                        />
                    </PanelRow>
                </PanelBody>

                <PanelBody title={__('Service Body Configuration', 'mayo-events-manager')} initialOpen={true}>
                    <PanelRow>
                        <TextControl
                            label={__('Restricted Service Bodies', 'mayo-events-manager')}
                            value={settings.default_service_bodies}
                            onChange={(value) => handleChange('default_service_bodies', value)}
                            help={__('Comma-separated list of service body IDs (e.g., 1,2,3). When specified, only these service bodies will be available for event submission. Leave empty to allow all service bodies.', 'mayo-events-manager')}
                            placeholder={__('e.g., 1,2,3,0', 'mayo-events-manager')}
                            __next40pxDefaultSize={true}
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
                            {isSaving ? __('Saving...', 'mayo-events-manager') : __('Save Settings', 'mayo-events-manager')}
                        </Button>
                    </PanelRow>
                </PanelBody>
            </Panel>
            
            <Panel>
                <PanelBody title={__('External Event Sources', 'mayo-events-manager')} initialOpen={true}>
                    <div className="mayo-external-sources-list">
                        {externalSources.map((source, index) => (
                            <div key={source.id} className="mayo-external-source-item">
                                <div className="mayo-external-source-info">
                                    <strong>{source.name || source.url}</strong>
                                    <div className="mayo-external-source-details">
                                        <div className="mayo-source-id-row">
                                            <code className="mayo-source-id">{source.id}</code>
                                            <button
                                                className="mayo-copy-id"
                                                onClick={() => handleCopyId(source.id)}
                                                title={__('Copy ID', 'mayo-events-manager')}
                                            >
                                                {__('Copy ID to clipboard', 'mayo-events-manager')}
                                            </button>
                                        </div>
                                        <div className="mayo-source-meta">
                                            <span>{__('Type:', 'mayo-events-manager')} {source.event_type || __('All', 'mayo-events-manager')}</span>
                                            {source.service_body && <span>{__('Service Body:', 'mayo-events-manager')} {source.service_body}</span>}
                                            <span className={`mayo-source-status ${source.enabled ? 'enabled' : 'disabled'}`}>
                                                {source.enabled ? __('Enabled', 'mayo-events-manager') : __('Disabled', 'mayo-events-manager')}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div className="mayo-external-source-actions">
                                    <Button
                                        isSecondary
                                        onClick={() => handleEditSource(source, index)}
                                    >
                                        {__('Edit', 'mayo-events-manager')}
                                    </Button>
                                    <Button
                                        isDestructive
                                        onClick={() => handleDeleteSource(index)}
                                    >
                                        {__('Delete', 'mayo-events-manager')}
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
                            disabled={isAddingNew || isEditingSource !== null}
                        >
                            {__('Add New External Source', 'mayo-events-manager')}
                        </Button>
                    </div>
                    
                    {(isAddingNew || isEditingSource !== null) && currentSource && (
                        <div className="mayo-external-source-form">
                            <TextControl
                                label={__('Site URL', 'mayo-events-manager')}
                                value={currentSource.url}
                                onChange={(value) => setCurrentSource({...currentSource, url: value})}
                                help={__('Enter the URL of the WordPress site (e.g., https://example.com)', 'mayo-events-manager')}
                                __next40pxDefaultSize={true}
                            />
                            <TextControl
                                label={__('Source Name', 'mayo-events-manager')}
                                value={currentSource.name}
                                onChange={(value) => setCurrentSource({...currentSource, name: value})}
                                help={__('Enter a friendly name for this source (e.g., District 5 Website)', 'mayo-events-manager')}
                                __next40pxDefaultSize={true}
                            />
                            <SelectControl
                                label={__('Event Type', 'mayo-events-manager')}
                                value={currentSource.event_type || ''}
                                options={[
                                    { label: __('All Event Types', 'mayo-events-manager'), value: '' },
                                    { label: __('Activity', 'mayo-events-manager'), value: 'Activity' },
                                    { label: __('Service', 'mayo-events-manager'), value: 'Service' },
                                    { label: __('Celebration', 'mayo-events-manager'), value: 'Celebration' }
                                ]}
                                onChange={(value) => {
                                    setCurrentSource({...currentSource, event_type: value});
                                }}
                                help={__('Select the event type', 'mayo-events-manager')}
                                __next40pxDefaultSize={true}
                            />
                            <TextControl
                                label={__('Service Body', 'mayo-events-manager')}
                                value={currentSource.service_body}
                                onChange={(value) => setCurrentSource({...currentSource, service_body: value})}
                                help={__('Filter by service body (optional)', 'mayo-events-manager')}
                                __next40pxDefaultSize={true}
                            />
                            <TextControl
                                label={__('Categories', 'mayo-events-manager')}
                                value={currentSource.categories}
                                onChange={(value) => setCurrentSource({...currentSource, categories: value})}
                                help={__('Filter by categories (comma-separated)', 'mayo-events-manager')}
                                __next40pxDefaultSize={true}
                            />
                            <TextControl
                                label={__('Tags', 'mayo-events-manager')}
                                value={currentSource.tags}
                                onChange={(value) => setCurrentSource({...currentSource, tags: value})}
                                help={__('Filter by tags (comma-separated)', 'mayo-events-manager')}
                                __next40pxDefaultSize={true}
                            />
                            <ToggleControl
                                label={__('Enable Source', 'mayo-events-manager')}
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
                                    {__('Cancel', 'mayo-events-manager')}
                                </Button>
                            </div>
                        </div>
                    )}
                </PanelBody>
            </Panel>

            <Panel>
                <PanelBody title={__('Subscription Preferences', 'mayo-events-manager')} initialOpen={true}>
                    <p className="mayo-settings-description">
                        {__('Configure which categories, tags, and service bodies are available for subscribers to choose from when signing up for announcement notifications.', 'mayo-events-manager')}
                    </p>

                    {allCategories.length > 0 && (
                        <div className="mayo-subscription-section">
                            <h4>{__('Categories available for subscription:', 'mayo-events-manager')}</h4>
                            <div className="mayo-checkbox-list" style={{ border: '1px solid #ddd', borderRadius: '4px', background: '#fff', padding: '12px 16px', maxHeight: '300px', overflowY: 'auto' }}>
                                {allCategories.map(cat => (
                                    <CheckboxControl
                                        key={cat.id}
                                        label={cat.name}
                                        checked={subscriptionSettings.subscription_categories.includes(cat.id)}
                                        onChange={() => toggleSubscriptionOption('subscription_categories', cat.id)}
                                    />
                                ))}
                            </div>
                        </div>
                    )}

                    {allTags.length > 0 && (
                        <div className="mayo-subscription-section">
                            <h4>{__('Tags available for subscription:', 'mayo-events-manager')}</h4>
                            <div className="mayo-checkbox-list" style={{ border: '1px solid #ddd', borderRadius: '4px', background: '#fff', padding: '12px 16px', maxHeight: '300px', overflowY: 'auto' }}>
                                {allTags.map(tag => (
                                    <CheckboxControl
                                        key={tag.id}
                                        label={tag.name}
                                        checked={subscriptionSettings.subscription_tags.includes(tag.id)}
                                        onChange={() => toggleSubscriptionOption('subscription_tags', tag.id)}
                                    />
                                ))}
                            </div>
                        </div>
                    )}

                    {allServiceBodies.length > 0 && (
                        <div className="mayo-subscription-section">
                            <h4>{__('Service Bodies available for subscription:', 'mayo-events-manager')}</h4>
                            <div className="mayo-service-body-tree">
                                {buildServiceBodyTree(allServiceBodies).map(sb => (
                                    <div
                                        key={sb.id}
                                        className="mayo-tree-item"
                                        style={{ paddingLeft: `${sb.depth * 24}px` }}
                                    >
                                        <CheckboxControl
                                            label={`${sb.name} (${sb.id})`}
                                            checked={subscriptionSettings.subscription_service_bodies.includes(sb.id)}
                                            onChange={() => toggleSubscriptionOption('subscription_service_bodies', sb.id)}
                                        />
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="mayo-subscription-section">
                        <h4>{__('When new options are added:', 'mayo-events-manager')}</h4>
                        <RadioControl
                            selected={subscriptionSettings.subscription_new_option_behavior}
                            options={[
                                { label: __('Auto-include: Automatically add to existing subscribers', 'mayo-events-manager'), value: 'auto_include' },
                                { label: __('Opt-in: Existing subscribers must manually add new options', 'mayo-events-manager'), value: 'opt_in' }
                            ]}
                            onChange={(value) => handleSubscriptionChange('subscription_new_option_behavior', value)}
                        />
                    </div>

                    <PanelRow>
                        <Button
                            isPrimary
                            onClick={handleSave}
                            isBusy={isSaving}
                            disabled={isSaving ||
                                (settings.bmlt_root_server && !isValidHttpsUrl(settings.bmlt_root_server)) ||
                                (settings.notification_email && !isValidEmailList(settings.notification_email))}
                        >
                            {isSaving ? __('Saving...', 'mayo-events-manager') : __('Save Settings', 'mayo-events-manager')}
                        </Button>
                    </PanelRow>
                </PanelBody>
            </Panel>
        </div>
    );
};

export default Settings; 