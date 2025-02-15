// Admin entry point
import { render } from '@wordpress/element';
import ShortcodesDocs from './components/admin/ShortcodesDocs';
import './components/admin/EventBlockEditorSidebar';
import Settings from './components/admin/Settings';

const App = () => {
    return (<></>)
};

document.addEventListener('DOMContentLoaded', () => {
    const adminContainer = document.getElementById('mayo-admin-root');
    const shortcodesContainer = document.getElementById('mayo-shortcode-root');
    const settingsContainer = document.getElementById('mayo-settings-root');

    if (adminContainer) {
        render(<App />, adminContainer);
    }

    if (shortcodesContainer) {
        render(<ShortcodesDocs />, shortcodesContainer);
    }

    if (settingsContainer) {
        render(<Settings />, settingsContainer);
    }
}); 