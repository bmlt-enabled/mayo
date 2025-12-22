// Admin entry point
import { render } from '@wordpress/element';
import ShortcodesDocs from './components/admin/ShortcodesDocs';
import Settings from './components/admin/Settings';
import CssClassesDocs from './components/admin/CssClassesDocs';
import ApiDocs from './components/admin/ApiDocs';
import './plugins/event-sidebar';
import './plugins/announcement-sidebar';

document.addEventListener('DOMContentLoaded', () => {
    const shortcodesContainer = document.getElementById('mayo-shortcode-root');
    const settingsContainer = document.getElementById('mayo-settings-root');
    const cssClassesContainer = document.getElementById('mayo-css-classes-root');
    const apiDocsContainer = document.getElementById('mayo-api-docs-root');

    if (shortcodesContainer) {
        render(<ShortcodesDocs />, shortcodesContainer);
    }

    if (settingsContainer) {
        render(<Settings />, settingsContainer);
    }

    if (cssClassesContainer) {
        render(<CssClassesDocs />, cssClassesContainer);
    }

    if (apiDocsContainer) {
        render(<ApiDocs />, apiDocsContainer);
    }
})