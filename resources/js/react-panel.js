import React from 'react';
import { createRoot } from 'react-dom/client';

import '../css/react-panel.css';
import ReactPanelApp from './react/panel';

const rootElement = document.getElementById('react-panel-root');

if (rootElement) {
    const payload = window.__PANEL_PAGE__ ?? {};
    createRoot(rootElement).render(
        React.createElement(
            React.StrictMode,
            null,
            React.createElement(ReactPanelApp, { payload }),
        ),
    );
}
