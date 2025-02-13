// Admin entry point
import { render } from '@wordpress/element';
import ShortcodesDocs from './components/ShortcodesDocs';
import './event-sidebar';
import Settings from './components/Settings';

const App = () => {
    return (<></>)
};

document.addEventListener('DOMContentLoaded', () => {
    const adminContainer = document.getElementById('mayo-admin-root');
    const shortcodesContainer = document.getElementById('mayo-shortcode-root');
    const settingsContainer = document.getElementById('mayo-settings-root');

    console.log('Containers:', {
        adminContainer,
        shortcodesContainer,
        settingsContainer
    });

    if (adminContainer) {
        render(<App />, adminContainer);
    }

    if (shortcodesContainer) {
        console.log('Rendering ShortcodesDocs');
        render(<ShortcodesDocs />, shortcodesContainer);
    }

    if (settingsContainer) {
        render(<Settings />, settingsContainer);
    }
}); 