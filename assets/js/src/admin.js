// Admin entry point
import { render } from '@wordpress/element';
import { HashRouter, Routes, Route, Navigate } from 'react-router-dom';
import ShortcodesDocs from './components/ShortcodesDocs';
import './event-sidebar';

const App = () => {
    // Get the current page from the URL
    const isShortcodesPage = window.location.href.includes('mayo-shortcodes');

    return (
        <HashRouter>
            <Routes>
                {/* Redirect based on the current admin page */}
                <Route path="/" element={
                    isShortcodesPage ? 
                        <Navigate to="/shortcodes" replace /> : 
                        <div>Mayo Events Dashboard</div>
                } />
                <Route path="/shortcodes" element={<ShortcodesDocs />} />
                {/* Add other routes as needed */}
            </Routes>
        </HashRouter>
    );
};

document.addEventListener('DOMContentLoaded', () => {
    const adminContainer = document.getElementById('mayo-admin');
    if (adminContainer) {
        render(<App />, adminContainer);
    }
}); 