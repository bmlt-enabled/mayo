// Admin entry point
import { render } from '@wordpress/element';
import ShortcodesDocs from './components/admin/ShortcodesDocs';
import Settings from './components/admin/Settings';
import './plugins/event-sidebar';

document.addEventListener('DOMContentLoaded', () => {
    const shortcodesContainer = document.getElementById('mayo-shortcode-root');
    const settingsContainer = document.getElementById('mayo-settings-root');

    if (shortcodesContainer) {
        render(<ShortcodesDocs />, shortcodesContainer);
    }

    if (settingsContainer) {
        render(<Settings />, settingsContainer);
    }
}); 