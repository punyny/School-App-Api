import React from 'react';

const roleLabelMap = {
    'super-admin': 'Super Admin',
    admin: 'Admin',
    teacher: 'Teacher',
    student: 'Student',
    parent: 'Parent',
};

function normalizeArray(value) {
    return Array.isArray(value) ? value : [];
}

function normalizeText(value) {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value);
}

function normalizePath(value) {
    const raw = normalizeText(value).trim();
    if (raw === '') {
        return '/';
    }

    const withoutQuery = raw.split('?')[0].split('#')[0];
    const withSlash = withoutQuery.startsWith('/') ? withoutQuery : `/${withoutQuery}`;
    const compact = withSlash.replace(/\/{2,}/g, '/');

    if (compact.length > 1) {
        return compact.replace(/\/+$/, '');
    }

    return compact;
}

function extractCellText(cell) {
    if (cell && typeof cell === 'object') {
        const label = normalizeText(cell.label ?? '');
        if (label !== '') {
            return label;
        }

        return normalizeText(cell.value ?? '');
    }

    return normalizeText(cell);
}

function toFiniteNumber(value) {
    const raw = extractCellText(value).replace(/,/g, '').replace(/[^0-9.-]/g, '');
    if (raw === '') {
        return 0;
    }

    const parsed = Number(raw);
    if (!Number.isFinite(parsed)) {
        return 0;
    }

    return parsed;
}

function clamp(value, min, max) {
    if (value < min) {
        return min;
    }

    if (value > max) {
        return max;
    }

    return value;
}

function buildSeed(input, offset = 0) {
    const source = normalizeText(input);
    let seed = Math.max(17 + offset, 17);

    for (let index = 0; index < source.length; index += 1) {
        seed = (seed * 31 + source.charCodeAt(index) + index) % 9973;
    }

    return seed;
}

function buildSyntheticSeries(seed, length, min = 20, max = 90) {
    const span = Math.max(max - min, 1);

    return Array.from({ length }).map((_, index) => {
        const waveA = Math.sin((seed + index * 4) / 6);
        const waveB = Math.cos((seed + index * 3) / 7);
        const normalized = (waveA + waveB + 2) / 4;

        return Math.round(min + normalized * span);
    });
}

function normalizeSeries(source, length, seed, min = 20, max = 90) {
    const cleanSource = normalizeArray(source)
        .map((value) => toFiniteNumber(value))
        .filter((value) => Number.isFinite(value) && value > 0);

    if (cleanSource.length === 0) {
        return buildSyntheticSeries(seed, length, min, max);
    }

    const sourceMax = Math.max(...cleanSource, 1);
    const scaledSource = cleanSource.map((value) => {
        const ratio = value / sourceMax;
        return Math.round(min + ratio * (max - min));
    });

    if (scaledSource.length >= length) {
        return scaledSource.slice(0, length);
    }

    const fallback = buildSyntheticSeries(seed, length, min, max);

    return Array.from({ length }).map((_, index) => {
        return scaledSource[index] ?? fallback[index];
    });
}

function buildChartPoints(series, width, height, padding = 6) {
    const safeSeries = normalizeArray(series).map((value) => toFiniteNumber(value));
    if (safeSeries.length === 0) {
        return [];
    }

    const minValue = Math.min(...safeSeries);
    const maxValue = Math.max(...safeSeries);
    const span = Math.max(maxValue - minValue, 1);
    const plotWidth = Math.max(width - padding * 2, 1);
    const plotHeight = Math.max(height - padding * 2, 1);
    const step = safeSeries.length > 1 ? plotWidth / (safeSeries.length - 1) : 0;

    return safeSeries.map((value, index) => {
        const x = padding + step * index;
        const normalized = (value - minValue) / span;
        const y = padding + (1 - normalized) * plotHeight;

        return { x, y };
    });
}

function pointsToPolyline(points) {
    return points.map((point) => `${point.x.toFixed(2)},${point.y.toFixed(2)}`).join(' ');
}

function pointsToPath(points) {
    if (points.length === 0) {
        return '';
    }

    return points
        .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(2)} ${point.y.toFixed(2)}`)
        .join(' ');
}

function pointsToAreaPath(points, height, padding = 6) {
    if (points.length === 0) {
        return '';
    }

    const first = points[0];
    const last = points[points.length - 1];
    const baseline = height - padding;
    const path = pointsToPath(points);

    return `${path} L ${last.x.toFixed(2)} ${baseline.toFixed(2)} L ${first.x.toFixed(2)} ${baseline.toFixed(2)} Z`;
}

function formatPercentDelta(value) {
    const rounded = Math.round(value * 10) / 10;
    const prefix = rounded > 0 ? '+' : '';
    return `${prefix}${rounded.toFixed(1)}%`;
}

function toInitials(value) {
    const words = normalizeText(value)
        .split(/\s+/)
        .filter((word) => word !== '');

    if (words.length === 0) {
        return 'SA';
    }

    return words
        .slice(0, 2)
        .map((word) => normalizeText(word[0] ?? '').toUpperCase())
        .join('');
}

function normalizeRowCells(row) {
    if (Array.isArray(row)) {
        return row.map((cell) => normalizeCell(cell));
    }

    if (row && typeof row === 'object') {
        return Object.values(row).map((cell) => normalizeCell(cell));
    }

    return [normalizeCell(row)];
}

function normalizeCell(cell) {
    if (cell && typeof cell === 'object') {
        const url = normalizeText(cell.url ?? '');
        if (url !== '') {
            return {
                type: 'link',
                url,
                label: normalizeText(cell.label ?? 'Open'),
            };
        }

        const labelOnly = normalizeText(cell.label ?? '');
        if (labelOnly !== '') {
            return labelOnly;
        }
    }

    return normalizeText(cell);
}

function renderCellContent(cell) {
    if (cell && typeof cell === 'object' && cell.type === 'link') {
        return (
            <a className="react-panel-cell-link" href={cell.url}>
                {normalizeText(cell.label || 'Open')}
            </a>
        );
    }

    return normalizeText(cell);
}

function RoleBadge({ panel }) {
    const role = normalizeText(panel).trim().toLowerCase();
    const label = roleLabelMap[role] ?? 'Dashboard';

    return <span className="react-panel-role">{label}</span>;
}

function getKeyword(label) {
    return normalizeText(label).trim().toLowerCase();
}

function PanelIcon({ label, index }) {
    const keyword = getKeyword(label);
    let icon = (
        <path d="M4 4h7v7H4V4Zm9 0h7v4h-7V4Zm0 6h7v10h-7V10Zm-9 3h7v7H4v-7Z" />
    );

    if (keyword.includes('user') || keyword.includes('profile') || keyword.includes('student')) {
        icon = (
            <path d="M12 12a4.5 4.5 0 1 0-4.5-4.5A4.5 4.5 0 0 0 12 12Zm0 2c-4.1 0-7.5 2.35-7.5 5.25V21h15v-1.75C19.5 16.35 16.1 14 12 14Z" />
        );
    } else if (keyword.includes('class') || keyword.includes('module') || keyword.includes('course')) {
        icon = (
            <path d="M12 3 3 8l9 5 7-3.9V15h2V8L12 3Zm-5 10.6V17a1.4 1.4 0 0 0 1.03 1.35L12 19.5l3.97-1.15A1.4 1.4 0 0 0 17 17v-3.4l-5 2.8-5-2.8Z" />
        );
    } else if (
        keyword.includes('message') ||
        keyword.includes('notice') ||
        keyword.includes('notification')
    ) {
        icon = (
            <path d="M4 5.75A2.75 2.75 0 0 1 6.75 3h10.5A2.75 2.75 0 0 1 20 5.75v7.5A2.75 2.75 0 0 1 17.25 16h-4.43l-3.78 3.3a.9.9 0 0 1-1.48-.68V16H6.75A2.75 2.75 0 0 1 4 13.25v-7.5Z" />
        );
    } else if (keyword.includes('score') || keyword.includes('report') || keyword.includes('result')) {
        icon = (
            <path d="M6 3.5A2.5 2.5 0 0 0 3.5 6v12A2.5 2.5 0 0 0 6 20.5h12a2.5 2.5 0 0 0 2.5-2.5V9.2L14.8 3.5H6Zm7 1.9 4.6 4.6H13V5.4Zm-6 7.6h10v1.8H7V13Zm0-3.7h5.3v1.8H7V9.3Zm0 7.4h10v1.8H7v-1.8Z" />
        );
    } else if (
        keyword.includes('attendance') ||
        keyword.includes('time') ||
        keyword.includes('schedule')
    ) {
        icon = (
            <path d="M7 3v2H5.8A2.8 2.8 0 0 0 3 7.8v10.4A2.8 2.8 0 0 0 5.8 21h12.4a2.8 2.8 0 0 0 2.8-2.8V7.8A2.8 2.8 0 0 0 18.2 5H17V3h-2v2H9V3H7Zm11.2 6.3V18a.8.8 0 0 1-.8.8H6.6a.8.8 0 0 1-.8-.8V9.3h12.4Zm-5.2 2.2h-2v3.1l2.5 1.5 1-1.6-1.5-.9v-2.1Z" />
        );
    } else if (
        keyword.includes('school') ||
        keyword.includes('dashboard') ||
        keyword.includes('overview')
    ) {
        icon = (
            <path d="M12 3 2.5 8.2 12 13l7.6-3.84V16h1.9V8.2L12 3Zm-5.6 9.7V17a1.5 1.5 0 0 0 1.08 1.44L12 20l4.52-1.56A1.5 1.5 0 0 0 17.6 17v-4.3L12 15.4l-5.6-2.7Z" />
        );
    } else if (index % 3 === 1) {
        icon = (
            <path d="M4.5 4.5h15v3h-15v-3Zm0 6h10v3h-10v-3Zm0 6h15v3h-15v-3Z" />
        );
    } else if (index % 3 === 2) {
        icon = (
            <path d="M12 3.2 20.8 8v8L12 20.8 3.2 16V8L12 3.2Zm0 2.3L5.2 8.8v6.4l6.8 3.3 6.8-3.3V8.8L12 5.5Zm-1 2.7h2v5h-2v-5Zm0 6.4h2v2h-2v-2Z" />
        );
    }

    return (
        <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
            {icon}
        </svg>
    );
}

function MiniTrendChart({ series }) {
    const width = 250;
    const height = 70;
    const points = buildChartPoints(series, width, height, 6);
    const line = pointsToPolyline(points);
    const area = pointsToAreaPath(points, height, 6);

    return (
        <svg className="react-template-mini-chart" viewBox={`0 0 ${width} ${height}`} preserveAspectRatio="none" aria-hidden="true">
            <path className="react-template-mini-area" d={area} />
            <polyline className="react-template-mini-line" points={line} />
        </svg>
    );
}

function RevenueAreaChart({ primarySeries, secondarySeries }) {
    const width = 700;
    const height = 260;
    const primaryPoints = buildChartPoints(primarySeries, width, height, 16);
    const secondaryPoints = buildChartPoints(secondarySeries, width, height, 16);
    const primaryLine = pointsToPath(primaryPoints);
    const secondaryLine = pointsToPath(secondaryPoints);
    const primaryArea = pointsToAreaPath(primaryPoints, height, 16);
    const secondaryArea = pointsToAreaPath(secondaryPoints, height, 16);

    return (
        <svg className="react-template-revenue-chart" viewBox={`0 0 ${width} ${height}`} preserveAspectRatio="none" aria-hidden="true">
            {[0.2, 0.4, 0.6, 0.8].map((ratio, index) => (
                <line
                    key={`grid-line-${index}`}
                    x1="16"
                    y1={(height * ratio).toFixed(2)}
                    x2={(width - 16).toFixed(2)}
                    y2={(height * ratio).toFixed(2)}
                    className="react-template-grid-line"
                />
            ))}
            <path className="react-template-revenue-area-secondary" d={secondaryArea} />
            <path className="react-template-revenue-area-primary" d={primaryArea} />
            <path className="react-template-revenue-line-secondary" d={secondaryLine} />
            <path className="react-template-revenue-line-primary" d={primaryLine} />
        </svg>
    );
}

function SplitBarChart({ positiveSeries, negativeSeries }) {
    const width = 700;
    const height = 260;
    const baseline = 175;
    const length = Math.max(positiveSeries.length, negativeSeries.length, 1);
    const innerWidth = width - 24;
    const slotWidth = innerWidth / length;
    const barWidth = Math.max(6, slotWidth * 0.45);
    const positiveMax = Math.max(...positiveSeries, 1);
    const negativeMax = Math.max(...negativeSeries, 1);

    return (
        <svg className="react-template-bars-chart" viewBox={`0 0 ${width} ${height}`} preserveAspectRatio="none" aria-hidden="true">
            {[0, 1, 2, 3].map((lineIndex) => {
                const y = 44 + lineIndex * 42;
                return (
                    <line
                        key={`bar-grid-${lineIndex}`}
                        x1="12"
                        y1={y.toFixed(2)}
                        x2={(width - 12).toFixed(2)}
                        y2={y.toFixed(2)}
                        className="react-template-grid-line"
                    />
                );
            })}
            <line x1="12" y1={baseline.toFixed(2)} x2={(width - 12).toFixed(2)} y2={baseline.toFixed(2)} className="react-template-bar-baseline" />
            {Array.from({ length }).map((_, index) => {
                const x = 12 + slotWidth * index + (slotWidth - barWidth) / 2;
                const positiveValue = positiveSeries[index] ?? 0;
                const negativeValue = negativeSeries[index] ?? 0;
                const positiveHeight = (positiveValue / positiveMax) * 130;
                const negativeHeight = (negativeValue / negativeMax) * 72;

                return (
                    <g key={`bar-item-${index}`}>
                        <rect
                            x={x.toFixed(2)}
                            y={(baseline - positiveHeight).toFixed(2)}
                            width={barWidth.toFixed(2)}
                            height={positiveHeight.toFixed(2)}
                            className="react-template-bar-positive"
                            rx="3"
                        />
                        <rect
                            x={x.toFixed(2)}
                            y={baseline.toFixed(2)}
                            width={barWidth.toFixed(2)}
                            height={negativeHeight.toFixed(2)}
                            className="react-template-bar-negative"
                            rx="3"
                        />
                    </g>
                );
            })}
        </svg>
    );
}

function TemplateDashboard({ data }) {
    const stats = normalizeArray(data?.stats);
    const rows = normalizeArray(data?.rows).map((row) => normalizeRowCells(row));
    const navLinks = normalizeArray(data?.navigationLinks).slice(0, 5);
    const viewerName = normalizeText(data?.viewerName || 'Super Admin');

    const topBadgeValues = [0, 1, 2].map((index) => {
        const fallback = [12, 5, 2][index];
        const raw = toFiniteNumber(stats[index]?.value ?? fallback);
        return clamp(Math.round(raw % 37) + (index === 0 ? 8 : 3), 1, 99);
    });

    const templateStats = stats.slice(0, 4).map((item, index) => {
        const label = normalizeText(item?.label || `Metric ${index + 1}`);
        const value = normalizeText(item?.value || '-');
        const seed = buildSeed(`${label}${value}`, index * 11 + 17);
        const delta = ((seed % 15) - 7) / 2;
        const trend = buildSyntheticSeries(seed + 5, 20, 18, 92);

        return {
            label,
            value,
            delta,
            trend,
        };
    });

    const tableNumericRows = rows.map((row) => row.map((cell) => toFiniteNumber(cell)));
    const rowTotals = tableNumericRows
        .map((values) => values.reduce((sum, value) => sum + value, 0))
        .filter((value) => value > 0);

    const revenueSeriesPrimary = normalizeSeries(
        rowTotals,
        7,
        buildSeed(`${viewerName}-revenue-primary`, 101),
        32,
        116,
    );
    const revenueSeriesSecondary = normalizeSeries(
        rowTotals.map((value) => value * 0.7),
        7,
        buildSeed(`${viewerName}-revenue-secondary`, 303),
        18,
        84,
    );

    const positiveRaw = tableNumericRows.map((values) => values[5] || values[4] || values[3] || values[0]);
    const negativeRaw = tableNumericRows.map((values) => values[3] || values[6] || values[4] || values[0]);
    const positiveSeries = normalizeSeries(positiveRaw, 20, buildSeed(viewerName, 707), 18, 100);
    const negativeSeries = normalizeSeries(negativeRaw, 20, buildSeed(viewerName, 809), 9, 72);

    const revenueSummaryA = templateStats[0]?.value ?? '-';
    const revenueSummaryB = templateStats[1]?.value ?? '-';
    const monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'];
    const avatarInitials = toInitials(viewerName);

    return (
        <section className="react-template-dashboard">
            <header className="react-template-topbar">
                <div className="react-template-topbar-main">
                    <button type="button" className="react-template-menu" aria-label="Dashboard menu">
                        <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                            <path d="M4 7h16M4 12h16M4 17h16" />
                        </svg>
                    </button>
                    <h2>{normalizeText(data?.title || 'Dashboard')}</h2>
                </div>
                <div className="react-template-topbar-side">
                    <div className="react-template-notifications" role="group" aria-label="Notifications">
                        {[
                            { label: 'Alerts', badge: topBadgeValues[0] },
                            { label: 'Messages', badge: topBadgeValues[1] },
                            { label: 'Tasks', badge: topBadgeValues[2] },
                        ].map((item, index) => (
                            <span key={`notify-${index}`} className="react-template-notify-item" title={item.label}>
                                <span className="react-template-notify-icon">
                                    <PanelIcon label={item.label} index={index} />
                                </span>
                                <span className="react-template-notify-badge">{item.badge}</span>
                            </span>
                        ))}
                    </div>
                    <div className="react-template-user-chip">
                        <span className="react-template-user-greeting">Good Morning</span>
                        <strong>{viewerName}</strong>
                        <span className="react-template-user-avatar">{avatarInitials}</span>
                    </div>
                </div>
            </header>

            {navLinks.length > 0 ? (
                <nav className="react-template-shortcuts" aria-label="Dashboard shortcuts">
                    {navLinks.map((link, index) => (
                        <a href={normalizeText(link?.url || '#')} key={`template-shortcut-${index}`}>
                            {normalizeText(link?.label || 'Open')}
                        </a>
                    ))}
                </nav>
            ) : null}

            <section className="react-template-kpi-grid">
                {templateStats.map((item, index) => {
                    const isUp = item.delta >= 0;
                    return (
                        <article className="react-template-kpi-card" key={`template-stat-${index}`}>
                            <div className="react-template-kpi-head">
                                <div className="react-template-kpi-value-block">
                                    <p className="react-template-kpi-value">{item.value}</p>
                                    <p className={isUp ? 'react-template-kpi-change up' : 'react-template-kpi-change down'}>
                                        {formatPercentDelta(item.delta)}
                                    </p>
                                </div>
                                <span className="react-template-kpi-icon">
                                    <PanelIcon label={item.label} index={index} />
                                </span>
                            </div>
                            <p className="react-template-kpi-label">{item.label}</p>
                            <MiniTrendChart series={item.trend} />
                        </article>
                    );
                })}
            </section>

            <section className="react-template-chart-grid">
                <article className="react-template-chart-card">
                    <div className="react-template-chart-head">
                        <div>
                            <h3>Revenue</h3>
                            <p>Live performance across your schools.</p>
                        </div>
                        <span className="react-template-period-pill">Monthly</span>
                    </div>
                    <div className="react-template-revenue-meta">
                        <div>
                            <span>Income</span>
                            <strong>{revenueSummaryA}</strong>
                        </div>
                        <div>
                            <span>Expense</span>
                            <strong>{revenueSummaryB}</strong>
                        </div>
                    </div>
                    <RevenueAreaChart primarySeries={revenueSeriesPrimary} secondarySeries={revenueSeriesSecondary} />
                    <div className="react-template-axis-labels">
                        {monthLabels.map((label) => (
                            <span key={`month-${label}`}>{label}</span>
                        ))}
                    </div>
                </article>

                <article className="react-template-chart-card">
                    <div className="react-template-chart-head">
                        <div>
                            <h3>School Activity</h3>
                            <p>Enrollment and operational balance.</p>
                        </div>
                        <div className="react-template-tab-pills">
                            <span className="active">Monthly</span>
                            <span>Weekly</span>
                            <span>Today</span>
                        </div>
                    </div>
                    <SplitBarChart positiveSeries={positiveSeries} negativeSeries={negativeSeries} />
                    <div className="react-template-axis-days">
                        {Array.from({ length: 20 }).map((_, index) => (
                            <span key={`axis-day-${index}`}>{String(index + 1).padStart(2, '0')}</span>
                        ))}
                    </div>
                </article>
            </section>
        </section>
    );
}

function NavigationStrip({ links, currentPath }) {
    const navLinks = normalizeArray(links).filter((link) => link && typeof link === 'object');
    if (navLinks.length === 0) {
        return null;
    }

    const safePath = normalizePath(currentPath);

    return (
        <section className="react-panel-nav-strip">
            {navLinks.map((link, index) => {
                const label = normalizeText(link?.label || 'Open');
                const url = normalizeText(link?.url || '#');
                const linkPath = normalizePath(url);
                const isActive =
                    safePath === linkPath || (linkPath !== '/' && safePath.startsWith(`${linkPath}/`));

                return (
                    <a
                        href={url}
                        key={`nav-link-${index}`}
                        className={isActive ? 'react-panel-nav-pill active' : 'react-panel-nav-pill'}
                    >
                        <span className="react-panel-nav-pill-icon">
                            <PanelIcon label={label} index={index} />
                        </span>
                        <span>{label}</span>
                    </a>
                );
            })}
        </section>
    );
}

function StatsGrid({ stats }) {
    const items = normalizeArray(stats);
    if (items.length === 0) {
        return null;
    }

    return (
        <section className="react-panel-stats">
            {items.map((item, index) => (
                <article className="react-panel-stat-card" key={`stat-${index}`}>
                    <div className="react-panel-stat-head">
                        <span className="react-panel-stat-icon">
                            <PanelIcon label={item?.label} index={index} />
                        </span>
                        <p className="react-panel-stat-label">{normalizeText(item?.label)}</p>
                    </div>
                    <p className="react-panel-stat-value">{normalizeText(item?.value || '-')}</p>
                </article>
            ))}
        </section>
    );
}

function ChildFilter({ currentPath, selectedChildId, childOptions }) {
    const children = normalizeArray(childOptions);
    if (children.length === 0) {
        return null;
    }

    return (
        <section className="react-panel-child-filter">
            <div>
                <h3>Student view</h3>
                <p>Choose which student profile to display.</p>
            </div>
            <form method="GET" action={normalizePath(currentPath)}>
                <select
                    className="react-panel-select"
                    name="student_id"
                    defaultValue={normalizeText(selectedChildId)}
                    onChange={(event) => event.currentTarget.form?.submit()}
                >
                    {children.map((child) => (
                        <option value={normalizeText(child?.id)} key={`child-${normalizeText(child?.id)}`}>
                            {normalizeText(child?.label)}
                        </option>
                    ))}
                </select>
            </form>
        </section>
    );
}

function TeacherModulesIcon() {
    return (
        <span className="react-panel-modules-title-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false">
                <path d="M12 3 3 8l9 5 7-3.89V15h2V8L12 3Zm-6.86 7L12 13.81 18.86 10 12 6.19 5.14 10Z" />
                <path d="M7 13.7V17c0 .66.43 1.23 1.07 1.42l3.93 1.18 3.93-1.18A1.49 1.49 0 0 0 17 17v-3.3l-5 2.78-5-2.78Z" />
            </svg>
        </span>
    );
}

function formatModuleNumber(value, index) {
    const raw = normalizeText(value).trim();
    if (raw === '') {
        return `Module ${String(index + 1).padStart(2, '0')}`;
    }

    if (/^\d+$/.test(raw)) {
        return `Module ${raw.padStart(2, '0')}`;
    }

    return raw;
}

function ModulesGrid({ modules, panel }) {
    const items = normalizeArray(modules);
    if (items.length === 0) {
        return null;
    }

    const isTeacherPanel = normalizeText(panel).trim().toLowerCase() === 'teacher';
    const moduleWrapClassName = isTeacherPanel
        ? 'react-panel-modules-wrap react-panel-modules-wrap-teacher'
        : 'react-panel-modules-wrap';
    const moduleGridClassName = isTeacherPanel
        ? 'react-panel-modules react-panel-modules-teacher'
        : 'react-panel-modules';

    return (
        <section className={moduleWrapClassName}>
            <div className="react-panel-modules-head">
                <div>
                    <h3 className="react-panel-modules-title">
                        {isTeacherPanel ? <TeacherModulesIcon /> : null}
                        <span>Quick Modules</span>
                    </h3>
                    {isTeacherPanel ? (
                        <p className="react-panel-modules-subtitle">
                            Classroom tools to run lessons, follow up requests, and track student progress.
                        </p>
                    ) : null}
                </div>
                <span className="react-panel-modules-count">
                    {items.length} module{items.length === 1 ? '' : 's'}
                </span>
            </div>
            <div className={moduleGridClassName}>
                {items.map((module, index) => {
                    const metricLabel = normalizeText(module?.metric_label).trim();
                    const metricValue = normalizeText(module?.metric_value).trim();
                    const moduleTitle = normalizeText(module?.title).trim();
                    const moduleDescription = normalizeText(module?.description).trim();
                    const moduleLinks = normalizeArray(module?.links);
                    const moduleDisplayTitle = moduleTitle || formatModuleNumber(module?.number, index);
                    const moduleMetricA11yLabel = `${metricLabel || 'Progress'}: ${metricValue || '-'}`;
                    const moduleCardClassName = isTeacherPanel
                        ? 'react-panel-module-card react-panel-module-card-teacher'
                        : 'react-panel-module-card';
                    const moduleLinksClassName = isTeacherPanel
                        ? 'react-panel-module-links react-panel-module-links-teacher'
                        : 'react-panel-module-links';

                    return (
                        <article className={moduleCardClassName} key={`module-${index}`}>
                            {isTeacherPanel ? (
                                <>
                                    <div className="react-panel-module-top">
                                        <div className="react-panel-module-meta">
                                            <span className="react-panel-module-number">{formatModuleNumber(module?.number, index)}</span>
                                            {metricLabel !== '' ? (
                                                <span className="react-panel-module-metric-label">{metricLabel}</span>
                                            ) : null}
                                        </div>
                                        <div
                                            className="react-panel-module-metric"
                                            title={moduleMetricA11yLabel}
                                            aria-label={moduleMetricA11yLabel}
                                        >
                                            <strong>{metricValue || '-'}</strong>
                                        </div>
                                    </div>
                                    <div className="react-panel-module-heading">
                                        <span className="react-panel-module-icon" aria-hidden="true">
                                            <PanelIcon label={moduleDisplayTitle} index={index} />
                                        </span>
                                        <h4 title={moduleDescription || moduleDisplayTitle}>{moduleDisplayTitle}</h4>
                                    </div>
                                    {moduleDescription !== '' ? (
                                        <p className="react-panel-module-description">{moduleDescription}</p>
                                    ) : null}
                                </>
                            ) : (
                                <div className="react-panel-module-top">
                                    <div className="react-panel-module-heading">
                                        <span className="react-panel-module-icon" aria-hidden="true">
                                            <PanelIcon label={moduleDisplayTitle} index={index} />
                                        </span>
                                        <h4 title={moduleDescription || moduleDisplayTitle}>{moduleDisplayTitle}</h4>
                                    </div>
                                    <div
                                        className="react-panel-module-metric"
                                        title={moduleMetricA11yLabel}
                                        aria-label={moduleMetricA11yLabel}
                                    >
                                        <strong>{metricValue || '-'}</strong>
                                    </div>
                                </div>
                            )}
                            {moduleLinks.length > 0 ? (
                                <div className={moduleLinksClassName} role="group" aria-label={`${moduleDisplayTitle} actions`}>
                                    {moduleLinks.map((link, linkIndex) => {
                                        const label = normalizeText(link?.label || 'Open');
                                        const moduleLinkClassName = isTeacherPanel
                                            ? 'react-panel-module-link react-panel-module-link-teacher'
                                            : 'react-panel-module-link';

                                        return (
                                            <a
                                                className={moduleLinkClassName}
                                                href={normalizeText(link?.url || '#')}
                                                key={`module-link-${index}-${linkIndex}`}
                                                title={label}
                                                aria-label={label}
                                            >
                                                {isTeacherPanel ? (
                                                    <>
                                                        <span className="react-panel-module-link-label">{label}</span>
                                                        <span className="react-panel-module-link-arrow" aria-hidden="true">
                                                            <svg viewBox="0 0 24 24" focusable="false">
                                                                <path d="M5 12h12" />
                                                                <path d="m13 6 6 6-6 6" />
                                                            </svg>
                                                        </span>
                                                    </>
                                                ) : (
                                                    <span className="react-panel-module-link-icon" aria-hidden="true">
                                                        <PanelIcon label={label} index={linkIndex} />
                                                    </span>
                                                )}
                                            </a>
                                        );
                                    })}
                                </div>
                            ) : null}
                        </article>
                    );
                })}
            </div>
        </section>
    );
}

function SchoolCardsSection({ schools, panel, csrfToken, schoolImageAccept, schoolImageMaxMb }) {
    const items = normalizeArray(schools).filter((school) => school && typeof school === 'object');
    if (items.length === 0) {
        return null;
    }

    const isSuperAdminPanel = normalizeText(panel).trim().toLowerCase() === 'super-admin';
    const sectionTitle = isSuperAdminPanel ? 'School Overview' : 'Directory';
    const safeCsrfToken = normalizeText(csrfToken).trim();
    const safeAccept = normalizeText(schoolImageAccept).trim();
    const maxImageMb = normalizeText(schoolImageMaxMb).trim();

    return (
        <section className="react-panel-school-wrap">
            <div className="react-panel-school-head">
                <div>
                    <h3>{sectionTitle}</h3>
                    <p>Click a school card to open that school dashboard.</p>
                </div>
                <span className="react-panel-school-count">
                    {items.length} school{items.length === 1 ? '' : 's'}
                </span>
            </div>
            <div className="react-panel-school-grid">
                {items.map((school, index) => {
                    const schoolName = normalizeText(school?.name || `School ${index + 1}`);
                    const schoolCode = normalizeText(school?.school_code || '').trim();
                    const schoolLocation = normalizeText(school?.location || '').trim();
                    const leadAdmin = normalizeText(school?.lead_admin_name || '').trim();
                    const manageUrl = normalizeText(school?.manage_url || '#');
                    const uploadUrl = normalizeText(school?.update_image_url || '').trim();
                    const imageUrl = normalizeText(school?.image_url || '').trim();
                    const canUploadImage = isSuperAdminPanel && uploadUrl !== '' && safeCsrfToken !== '';
                    const schoolInitials = toInitials(schoolName);

                    const metrics = [
                        { label: 'Admins', value: normalizeText(school?.admin_count ?? 0) },
                        { label: 'Teachers', value: normalizeText(school?.teacher_count ?? 0) },
                        { label: 'Students', value: normalizeText(school?.student_count ?? 0) },
                        { label: 'Parents', value: normalizeText(school?.parent_count ?? 0) },
                        { label: 'Classes', value: normalizeText(school?.class_count ?? 0) },
                        { label: 'Subjects', value: normalizeText(school?.subject_count ?? 0) },
                    ];

                    return (
                        <article className="react-panel-school-card" key={`school-card-${index}`}>
                            <a className="react-panel-school-card-link" href={manageUrl}>
                                <div className="react-panel-school-bg-layer">
                                    {imageUrl !== '' ? (
                                        <>
                                            <img
                                                src={imageUrl}
                                                alt={`${schoolName} image`}
                                                className="react-panel-school-bg-image"
                                                onError={(event) => {
                                                    const imageElement = event.currentTarget;
                                                    imageElement.style.display = 'none';
                                                    const fallback = imageElement.nextElementSibling;
                                                    if (fallback instanceof HTMLElement) {
                                                        fallback.style.display = 'inline-flex';
                                                    }
                                                }}
                                            />
                                            <div className="react-panel-school-bg-fallback">{schoolInitials}</div>
                                        </>
                                    ) : (
                                        <div className="react-panel-school-bg-fallback active">{schoolInitials}</div>
                                    )}
                                </div>
                                <div className="react-panel-school-bg-scrim" />
                                <div className="react-panel-school-content">
                                    <header className="react-panel-school-card-head">
                                        <div className="react-panel-school-card-title-block">
                                            <div>
                                                <h4>{schoolName}</h4>
                                                <p>Code: {schoolCode !== '' ? schoolCode : 'N/A'}</p>
                                            </div>
                                        </div>
                                        <span className="react-panel-school-open">Open</span>
                                    </header>
                                    <p className="react-panel-school-location">
                                        {schoolLocation !== '' ? schoolLocation : 'Campus not set'}
                                    </p>
                                    <div className="react-panel-school-metrics">
                                        {metrics.map((metric, metricIndex) => (
                                            <div className="react-panel-school-metric" key={`school-${index}-metric-${metricIndex}`}>
                                                <span>{metric.label}</span>
                                                <strong>{metric.value}</strong>
                                            </div>
                                        ))}
                                    </div>
                                    <p className="react-panel-school-admin">
                                        Lead admin: <strong>{leadAdmin !== '' ? leadAdmin : 'Assign admin now'}</strong>
                                    </p>
                                </div>
                            </a>
                            {canUploadImage ? (
                                <form
                                    className="react-panel-school-upload-form"
                                    method="POST"
                                    action={uploadUrl}
                                    encType="multipart/form-data"
                                >
                                    <input type="hidden" name="_token" value={safeCsrfToken} />
                                    <div className="react-panel-school-upload-controls">
                                        <label className="react-panel-school-upload-picker">
                                            <span>Choose School Image</span>
                                            <input
                                                type="file"
                                                name="school_image"
                                                accept={safeAccept !== '' ? safeAccept : 'image/*'}
                                                required
                                            />
                                        </label>
                                        <button type="submit" className="react-panel-school-upload-btn">Upload</button>
                                    </div>
                                    {maxImageMb !== '' ? (
                                        <p className="react-panel-school-upload-note">Max image size: {maxImageMb} MB</p>
                                    ) : null}
                                </form>
                            ) : null}
                        </article>
                    );
                })}
            </div>
        </section>
    );
}

function OverviewTable({ tableTitle, columns, rows }) {
    const tableColumns = normalizeArray(columns).map((column) => normalizeText(column));
    const tableRows = normalizeArray(rows).map((row) => normalizeRowCells(row));

    return (
        <section className="react-panel-table-panel">
            <div className="react-panel-table-head">
                <h3>{normalizeText(tableTitle || 'Overview')}</h3>
                <p>Data synced from the current server route.</p>
            </div>

            {tableRows.length === 0 ? (
                <div className="react-panel-empty">No data available.</div>
            ) : (
                <div className="react-panel-table-wrap">
                    <table className="react-panel-table">
                        <thead>
                            <tr>
                                {tableColumns.map((column, index) => (
                                    <th key={`column-${index}`}>{column}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {tableRows.map((rowCells, rowIndex) => (
                                <tr key={`row-${rowIndex}`}>
                                    {tableColumns.map((_, columnIndex) => (
                                        <td key={`row-${rowIndex}-col-${columnIndex}`}>
                                            {renderCellContent(rowCells[columnIndex] ?? '-')}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}

export default function ReactPanelApp({ payload }) {
    const data = payload && typeof payload === 'object' ? payload : {};
    const modules = normalizeArray(data.modules).length > 0
        ? normalizeArray(data.modules)
        : normalizeArray(data.teacherDashboardModules);
    const currentPath = normalizePath(data.currentPath || window.location.pathname);
    const panelRole = normalizeText(data.panel).trim().toLowerCase();
    const isTemplateDashboard = (
        (panelRole === 'super-admin' && currentPath === '/super-admin/dashboard')
        || (panelRole === 'admin' && currentPath === '/admin/dashboard')
    );

    if (isTemplateDashboard) {
        return (
            <div className="react-panel-page">
                <main className="react-panel-main">
                    <TemplateDashboard data={data} />
                    <SchoolCardsSection
                        schools={data.schoolCards}
                        panel={data.panel}
                        csrfToken={data.csrfToken}
                        schoolImageAccept={data.schoolImageAccept}
                        schoolImageMaxMb={data.schoolImageMaxMb}
                    />
                    <ModulesGrid modules={modules} panel={data.panel} />
                    <OverviewTable
                        tableTitle={data.tableTitle}
                        columns={data.columns}
                        rows={data.rows}
                    />
                </main>
            </div>
        );
    }

    return (
        <div className="react-panel-page">
            <main className="react-panel-main">
                <section className="react-panel-hero">
                    <div>
                        <h1>{normalizeText(data.title || 'Dashboard')}</h1>
                        <p>{normalizeText(data.subtitle || 'A unified overview of your school operations.')}</p>
                    </div>
                    <RoleBadge panel={data.panel} />
                </section>

                <NavigationStrip links={data.navigationLinks} currentPath={currentPath} />

                <ChildFilter
                    currentPath={currentPath}
                    selectedChildId={data.selectedChildId}
                    childOptions={data.childOptions}
                />

                <StatsGrid stats={data.stats} />
                <SchoolCardsSection
                    schools={data.schoolCards}
                    panel={data.panel}
                    csrfToken={data.csrfToken}
                    schoolImageAccept={data.schoolImageAccept}
                    schoolImageMaxMb={data.schoolImageMaxMb}
                />
                <ModulesGrid modules={modules} panel={data.panel} />
                <OverviewTable
                    tableTitle={data.tableTitle}
                    columns={data.columns}
                    rows={data.rows}
                />
            </main>
        </div>
    );
}
