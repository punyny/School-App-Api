import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';

import '../css/react-web-shell.css';

function WebShell({ legacyId, shellType }) {
    const legacySlotRef = useRef(null);
    const [shellTitle, setShellTitle] = useState('');
    const [shellSubtitle, setShellSubtitle] = useState('');
    const [headerLinks, setHeaderLinks] = useState([]);
    const pageTitle = useMemo(() => document.title || 'School Platform', []);
    const badgeText = shellType === 'guest' ? 'Public Access' : 'Workspace';

    useEffect(() => {
        const legacyNode = document.getElementById(legacyId);
        const slot = legacySlotRef.current;
        if (!legacyNode || !slot) {
            return;
        }

        if (legacyNode.parentElement !== slot) {
            slot.appendChild(legacyNode);
        }

        legacyNode.style.display = 'block';

        const topbar = legacyNode.querySelector('.topbar');
        const titleNode = legacyNode.querySelector('.title, h1');
        const subtitleNode = legacyNode.querySelector('.subtitle');
        const nextTitle = (titleNode?.textContent || '').trim();
        const nextSubtitle = (subtitleNode?.textContent || '').trim();

        const links = topbar
            ? Array.from(topbar.querySelectorAll('.mini-actions a'))
                .slice(0, 6)
                .map((link) => ({
                    href: (link.getAttribute('href') || '#').trim() || '#',
                    label: (link.textContent || 'Open').trim() || 'Open',
                }))
            : [];

        if (topbar) {
            topbar.setAttribute('data-react-shell-hidden', 'true');
        } else {
            if (shellType === 'auth' && titleNode) {
                titleNode.setAttribute('data-react-shell-hidden', 'true');
            }

            if (shellType === 'auth' && subtitleNode) {
                subtitleNode.setAttribute('data-react-shell-hidden', 'true');
            }
        }

        setShellTitle(nextTitle);
        setShellSubtitle(nextSubtitle);
        setHeaderLinks(links);
    }, [legacyId, shellType]);

    return (
        <section className="react-web-shell">
            <header className="react-web-shell-header">
                <div className="react-web-shell-header-copy">
                    <h2>{shellTitle || pageTitle}</h2>
                    {shellSubtitle ? <p>{shellSubtitle}</p> : null}
                </div>
                <div className="react-web-shell-header-right">
                    {headerLinks.length > 0 ? (
                        <div className="react-web-shell-actions">
                            {headerLinks.map((link, index) => (
                                <a href={link.href} key={`shell-link-${index}`}>
                                    {link.label}
                                </a>
                            ))}
                        </div>
                    ) : null}
                    <span>{badgeText}</span>
                </div>
            </header>
            <div className="react-web-shell-body" ref={legacySlotRef} />
        </section>
    );
}

const rootElement = document.getElementById('web-react-shell-root');

if (rootElement) {
    const shellType = rootElement.getAttribute('data-shell') || 'auth';
    createRoot(rootElement).render(
        <React.StrictMode>
            <WebShell legacyId="web-legacy-content" shellType={shellType} />
        </React.StrictMode>,
    );
}
