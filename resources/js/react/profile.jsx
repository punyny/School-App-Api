import React from 'react';

const roleLabelMap = {
    'super-admin': 'Super Admin',
    admin: 'Admin',
    teacher: 'Teacher',
    student: 'Student',
    parent: 'Parent',
    guardian: 'Parent',
};

function text(value) {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value).trim();
}

function toArray(value) {
    return Array.isArray(value) ? value : [];
}

function numberValue(value) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
}

function firstErrorMessage(errors) {
    if (!errors || typeof errors !== 'object') {
        return '';
    }

    const values = Object.values(errors);
    for (const entry of values) {
        if (Array.isArray(entry) && entry.length > 0) {
            const message = text(entry[0]);
            if (message) {
                return message;
            }
        }

        const message = text(entry);
        if (message) {
            return message;
        }
    }

    return '';
}

function Avatar({ imageUrl, name }) {
    const initial = text(name).slice(0, 1).toUpperCase() || 'U';
    const src = text(imageUrl);

    return (
        <div className="react-profile-avatar">
            {src ? <img src={src} alt="Profile" /> : <span>{initial}</span>}
        </div>
    );
}

function RoleStats({ stats }) {
    const items = toArray(stats);
    if (items.length === 0) {
        return null;
    }

    return (
        <div className="react-profile-stats">
            {items.map((item, index) => (
                <article className="react-profile-stat-card" key={`profile-stat-${index}`}>
                    <p>{text(item?.label) || 'Metric'}</p>
                    <strong>{text(item?.value) || '0'}</strong>
                </article>
            ))}
        </div>
    );
}

function TeacherInsights({ insights }) {
    const classes = toArray(insights?.classes);
    const timetable = toArray(insights?.timetable);

    return (
        <section className="react-profile-surface">
            <h3>Teaching Overview</h3>
            <div className="react-profile-grid-2">
                <div className="react-profile-surface-soft">
                    <h4>My Classes</h4>
                    {classes.length === 0 ? (
                        <p className="react-profile-empty">No class assigned yet.</p>
                    ) : (
                        <ul className="react-profile-list">
                            {classes.slice(0, 12).map((item, index) => (
                                <li key={`class-${index}`}>
                                    <strong>{text(item?.name) || '-'}</strong>
                                    <span>
                                        Grade: {text(item?.grade_level) || '-'} | Room: {text(item?.room) || '-'}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
                <div className="react-profile-surface-soft">
                    <h4>My Timetable</h4>
                    {timetable.length === 0 ? (
                        <p className="react-profile-empty">No timetable rows.</p>
                    ) : (
                        <ul className="react-profile-list">
                            {timetable.slice(0, 12).map((item, index) => (
                                <li key={`tt-${index}`}>
                                    <strong>
                                        {text(item?.day) || '-'} {text(item?.time_start) || '--:--'} - {text(item?.time_end) || '--:--'}
                                    </strong>
                                    <span>
                                        {text(item?.subject_name) || '-'} | {text(item?.class_name) || '-'}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </section>
    );
}

function StudentInsights({ insights }) {
    const parents = toArray(insights?.parents);
    const subjects = toArray(insights?.subjects);
    const scores = toArray(insights?.recent_scores);
    const classData = insights?.class_data && typeof insights.class_data === 'object'
        ? insights.class_data
        : {};

    return (
        <section className="react-profile-surface">
            <h3>Student Overview</h3>
            <div className="react-profile-grid-2">
                <div className="react-profile-surface-soft">
                    <h4>Class Info</h4>
                    <p className="react-profile-inline">
                        {text(classData?.name) || '-'} | Grade: {text(classData?.grade_level) || '-'} | Room: {text(classData?.room) || '-'}
                    </p>
                    <h4>Parents</h4>
                    {parents.length === 0 ? (
                        <p className="react-profile-empty">No parent linked.</p>
                    ) : (
                        <ul className="react-profile-list">
                            {parents.map((item, index) => (
                                <li key={`parent-${index}`}>
                                    <strong>{text(item?.name) || '-'}</strong>
                                    <span>{text(item?.phone) || '-'}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
                <div className="react-profile-surface-soft">
                    <h4>Subjects</h4>
                    {subjects.length === 0 ? (
                        <p className="react-profile-empty">No subject data.</p>
                    ) : (
                        <ul className="react-profile-list">
                            {subjects.slice(0, 10).map((item, index) => (
                                <li key={`subject-${index}`}>
                                    <strong>{text(item?.name) || '-'}</strong>
                                    <span>Full score: {numberValue(item?.full_score)}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
            <div className="react-profile-surface-soft">
                <h4>Recent Scores</h4>
                {scores.length === 0 ? (
                    <p className="react-profile-empty">No score rows.</p>
                ) : (
                    <div className="react-profile-table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Type</th>
                                    <th>Score</th>
                                    <th>Grade</th>
                                    <th>Rank</th>
                                </tr>
                            </thead>
                            <tbody>
                                {scores.slice(0, 12).map((item, index) => (
                                    <tr key={`score-${index}`}>
                                        <td>{text(item?.subject_name) || '-'}</td>
                                        <td>{text(item?.assessment_type) || '-'}</td>
                                        <td>{numberValue(item?.total_score)}</td>
                                        <td>{text(item?.grade) || '-'}</td>
                                        <td>{text(item?.rank_in_class) || '-'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </section>
    );
}

function ParentInsights({ insights }) {
    const children = toArray(insights?.children);

    return (
        <section className="react-profile-surface">
            <h3>Children Overview</h3>
            {children.length === 0 ? (
                <p className="react-profile-empty">No child linked to this account.</p>
            ) : (
                <div className="react-profile-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Class</th>
                                <th>Grade</th>
                                <th>Average</th>
                                <th>Latest Rank</th>
                            </tr>
                        </thead>
                        <tbody>
                            {children.map((item, index) => (
                                <tr key={`child-${index}`}>
                                    <td>{text(item?.name) || '-'}</td>
                                    <td>{text(item?.class_name) || '-'}</td>
                                    <td>{text(item?.grade) || '-'}</td>
                                    <td>{text(item?.score_average ?? '-')}</td>
                                    <td>{text(item?.latest_rank ?? '-')}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}

function TelegramLinkPanel({ payload }) {
    const endpoints = payload?.endpoints && typeof payload.endpoints === 'object'
        ? payload.endpoints
        : {};
    const user = payload?.user && typeof payload.user === 'object' ? payload.user : {};
    const csrfToken = text(payload?.csrfToken);
    const [loading, setLoading] = React.useState(false);
    const [error, setError] = React.useState('');
    const [success, setSuccess] = React.useState('');
    const [command, setCommand] = React.useState('');
    const [expiresAt, setExpiresAt] = React.useState('');
    const [copied, setCopied] = React.useState(false);
    const endpoint = text(endpoints?.telegramLinkCode);
    const linkedChatId = text(user?.telegram_chat_id);

    async function generateCode() {
        if (!endpoint || !csrfToken || loading) {
            return;
        }

        setLoading(true);
        setError('');
        setSuccess('');
        setCopied(false);

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({}),
                credentials: 'same-origin',
            });

            const json = await response.json().catch(() => ({}));
            if (!response.ok || !json?.ok) {
                const message = text(json?.message) || firstErrorMessage(json?.errors) || 'Unable to generate code.';
                setError(message);
                setCommand('');
                setExpiresAt('');

                return;
            }

            const commandText = text(json?.data?.command);
            const expiresText = text(json?.data?.expires_at);

            setSuccess(text(json?.message) || 'Telegram link code generated.');
            setCommand(commandText);
            setExpiresAt(expiresText);
        } catch (_error) {
            setError('Network error. Please try again.');
            setCommand('');
            setExpiresAt('');
        } finally {
            setLoading(false);
        }
    }

    async function copyCommand() {
        if (!command) {
            return;
        }

        try {
            if (navigator?.clipboard?.writeText) {
                await navigator.clipboard.writeText(command);
                setCopied(true);

                return;
            }
        } catch (_error) {
            // Fallback below.
        }

        const area = document.createElement('textarea');
        area.value = command;
        area.setAttribute('readonly', 'readonly');
        area.style.position = 'absolute';
        area.style.left = '-9999px';
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        document.body.removeChild(area);
        setCopied(true);
    }

    return (
        <section className="react-profile-surface">
            <h3>Telegram Link</h3>
            <p className="react-profile-inline">
                Current Chat ID: {linkedChatId || 'Not linked yet'}
            </p>
            <div className="react-profile-telegram-actions">
                <button type="button" onClick={generateCode} disabled={loading || !endpoint} className="react-profile-button-secondary">
                    {loading ? 'Generating...' : 'Generate Telegram Link Code'}
                </button>
            </div>
            {error ? <p className="flash-error">{error}</p> : null}
            {success ? <p className="flash-success">{success}</p> : null}
            {command ? (
                <div className="react-profile-telegram-code">
                    <p>Send this command to your Telegram bot:</p>
                    <code>{command}</code>
                    <div className="react-profile-telegram-actions">
                        <button type="button" onClick={copyCommand} className="react-profile-button-secondary">
                            {copied ? 'Copied' : 'Copy Command'}
                        </button>
                    </div>
                    {expiresAt ? <small>Expires at: {expiresAt}</small> : null}
                </div>
            ) : null}
        </section>
    );
}

function ProfileForm({ payload }) {
    const profileForm = payload?.profileForm && typeof payload.profileForm === 'object'
        ? payload.profileForm
        : {};
    const endpoints = payload?.endpoints && typeof payload.endpoints === 'object'
        ? payload.endpoints
        : {};
    const csrfToken = text(payload?.csrfToken);

    return (
        <section className="react-profile-grid-2">
            <article className="react-profile-surface">
                <h3>Update Profile</h3>
                <form method="POST" action={text(endpoints?.update) || '#'} encType="multipart/form-data" className="react-profile-form">
                    <input type="hidden" name="_token" value={csrfToken} />
                    <input type="hidden" name="_method" value="PATCH" />

                    <label>
                        <span>Name</span>
                        <input type="text" name="name" defaultValue={text(profileForm?.name)} />
                    </label>
                    <label>
                        <span>Phone</span>
                        <input type="text" name="phone" defaultValue={text(profileForm?.phone)} />
                    </label>
                    <label>
                        <span>Address</span>
                        <input type="text" name="address" defaultValue={text(profileForm?.address)} />
                    </label>
                    <label>
                        <span>Bio</span>
                        <textarea rows="3" name="bio" defaultValue={text(profileForm?.bio)} />
                    </label>
                    <label>
                        <span>Image URL</span>
                        <input type="text" name="image_url" defaultValue={text(profileForm?.image_url)} />
                    </label>
                    <label>
                        <span>Upload Image</span>
                        <input type="file" name="image" accept="image/*" />
                    </label>
                    <label className="react-profile-check">
                        <input type="checkbox" name="remove_image" value="1" defaultChecked={Boolean(profileForm?.remove_image)} />
                        <span>Remove current image</span>
                    </label>

                    <button type="submit">Save Profile</button>
                </form>
            </article>
            <article className="react-profile-surface">
                <h3>Change Password</h3>
                <form method="POST" action={text(endpoints?.changePassword) || '#'} className="react-profile-form">
                    <input type="hidden" name="_token" value={csrfToken} />
                    <label>
                        <span>New Password</span>
                        <input type="password" name="new_password" autoComplete="new-password" />
                    </label>
                    <label>
                        <span>Confirm Password</span>
                        <input type="password" name="new_password_confirmation" autoComplete="new-password" />
                    </label>
                    <button type="submit">Update Password</button>
                </form>
            </article>
        </section>
    );
}

export default function ReactProfilePage({ payload }) {
    const data = payload && typeof payload === 'object' ? payload : {};
    const user = data.user && typeof data.user === 'object' ? data.user : {};
    const role = text(data.role).toLowerCase();
    const roleLabel = roleLabelMap[role] || 'User';
    const flashes = data.flashes && typeof data.flashes === 'object' ? data.flashes : {};
    const roleStats = toArray(data.roleStats);

    return (
        <div className="react-profile-page">
            {text(flashes.success) ? <p className="flash-success">{text(flashes.success)}</p> : null}
            {text(flashes.error) ? <p className="flash-error">{text(flashes.error)}</p> : null}

            <section className="react-profile-hero">
                <div className="react-profile-hero-main">
                    <Avatar imageUrl={user.image_url} name={user.name} />
                    <div>
                        <h2>{text(user.name) || '-'}</h2>
                        <p>{text(user.email) || '-'}</p>
                        <div className="react-profile-tags">
                            <span>{roleLabel}</span>
                            <span>Phone: {text(user.phone) || '-'}</span>
                            <span>Address: {text(user.address) || '-'}</span>
                        </div>
                    </div>
                </div>
                <RoleStats stats={roleStats} />
            </section>

            {role === 'teacher' ? <TeacherInsights insights={data.insights} /> : null}
            {role === 'student' ? <StudentInsights insights={data.insights} /> : null}
            {role === 'parent' ? <ParentInsights insights={data.insights} /> : null}

            <TelegramLinkPanel payload={data} />
            <ProfileForm payload={data} />
        </div>
    );
}
