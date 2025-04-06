// Admin entry point
import { render } from '@wordpress/element';
import ShortcodesDocs from './components/admin/ShortcodesDocs';
import Settings from './components/admin/Settings';
import CssClassesDocs from './components/admin/CssClassesDocs';
import './plugins/event-sidebar';

document.addEventListener('DOMContentLoaded', () => {
    const shortcodesContainer = document.getElementById('mayo-shortcode-root');
    const settingsContainer = document.getElementById('mayo-settings-root');
    const cssClassesContainer = document.getElementById('mayo-css-classes-root');

    if (shortcodesContainer) {
        render(<ShortcodesDocs />, shortcodesContainer);
    }

    if (settingsContainer) {
        render(<Settings />, settingsContainer);
    }

    if (cssClassesContainer) {
        render(<CssClassesDocs />, cssClassesContainer);
    }
})