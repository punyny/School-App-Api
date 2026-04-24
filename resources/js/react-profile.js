import React from 'react';
import { createRoot } from 'react-dom/client';

import '../css/react-profile.css';
import ReactProfilePage from './react/profile';

const rootElement = document.getElementById('react-profile-root');

if (rootElement) {
    const payload = window.__PROFILE_PAGE__ ?? {};

    createRoot(rootElement).render(
        React.createElement(
            React.StrictMode,
            null,
            React.createElement(ReactProfilePage, { payload }),
        ),
    );
}
