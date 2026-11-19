// Trailhead frontend — vanilla JS, no build step, calls the PHP API directly.

const API_KEY_STORAGE = 'trailhead_api_key';

function getApiKey() {
    let key = localStorage.getItem(API_KEY_STORAGE);
    if (!key) {
        key = prompt('Enter your Trailhead API key (set once, stored on this device):');
        if (key) localStorage.setItem(API_KEY_STORAGE, key.trim());
    }
    return key;
}

async function api(path, options = {}) {
    const res = await fetch(`api/${path}`, {
        ...options,
        headers: {
            'Content-Type': 'application/json',
            'X-API-Key': getApiKey(),
            ...(options.headers || {}),
        },
    });
    if (res.status === 401) {
        localStorage.removeItem(API_KEY_STORAGE);
        alert('API key rejected. Reload and re-enter it.');
        throw new Error('Unauthorized');
    }
    if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        throw new Error(body.error || `Request failed: ${res.status}`);
    }
    return res.json();
}

function todayISO() {
    return new Date().toISOString().slice(0, 10);
}

function formatDateLabel(iso) {
    if (iso === todayISO()) return 'Today';
    return new Date(iso + 'T00:00:00').toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

function initials(name) {
    return name.split(' ').map(n => n[0]).join('');
}

function patrolColor(patrol) {
    if (!patrol) return '#78716c';
    const palette = ['#8B6F47', '#5C6B73', '#B5654A', '#6B7C8C', '#9C7A54'];
    let hash = 0;
    for (const ch of patrol) hash = (hash * 31 + ch.charCodeAt(0)) % palette.length;
    return palette[hash];
}

function el(html) {
    const t = document.createElement('template');
    t.innerHTML = html.trim();
    return t.content.firstElementChild;
}

// ---- App state --------------------------------------------------------

const state = {
    tab: 'signoff',        // 'signoff' | 'queue' | 'history'
    screen: { name: 'list' }, // signoff sub-navigation
    historyScreen: { name: 'list' }, // history sub-navigation: 'list' | 'detail'
    scouts: [],
    queueScouts: [],
    runs: [],
    loaded: { scouts: false, queue: false, history: false },
};

const root = document.getElementById('app');

async function ensureLoaded(key, loader) {
    if (!state.loaded[key]) {
        await loader();
        state.loaded[key] = true;
    }
}

// ---- Renderers ----------------------------------------------------------

async function render() {
    root.innerHTML = '';

    const body = el(`<div style="flex:1; overflow-y:auto; display:flex; flex-direction:column;"></div>`);
    root.appendChild(body);

    if (state.tab === 'signoff') {
        await ensureLoaded('scouts', loadScouts);
        renderSignoffTab(body);
    } else if (state.tab === 'queue') {
        await ensureLoaded('queue', loadQueue);
        renderQueueTab(body);
    } else if (state.tab === 'history') {
        await ensureLoaded('history', loadHistory);
        renderHistoryTab(body);
    }

    root.appendChild(renderTabBar());
}

function renderTabBar() {
    const bar = el(`<div class="tabbar"></div>`);
    [['signoff', 'Sign off'], ['queue', 'Queue'], ['history', 'History']].forEach(([key, label]) => {
        const btn = el(`<button class="${state.tab === key ? 'active' : ''}">${label}</button>`);
        btn.onclick = () => { state.tab = key; render(); };
        bar.appendChild(btn);
    });
    return bar;
}

// ---- Sign off tab (capture flow) -----------------------------------------

async function loadScouts() {
    const data = await api('scouts.php');
    state.scouts = data.scouts;
}

function renderSignoffTab(body) {
    if (state.screen.name === 'list') renderScoutList(body);
    else if (state.screen.name === 'detail') renderScoutDetail(body, state.screen.scout);
    else if (state.screen.name === 'confirm') renderConfirmScreen(body, state.screen.scout, state.screen.pending);
}

function renderScoutList(body) {
    body.appendChild(el(`
        <div class="header">
            <h1>Sign off</h1>
            <div class="sub">Troop 911 - ${state.scouts.length} scouts</div>
        </div>
    `));

    const search = el(`<div class="search-box"><input placeholder="Find a scout" autofocus></div>`);
    body.appendChild(search);

    const list = el(`<div style="flex:1; overflow-y:auto; padding-bottom:8px;"></div>`);
    body.appendChild(list);

    function renderList(filter = '') {
        list.innerHTML = '';
        const filtered = state.scouts.filter(s => s.name.toLowerCase().includes(filter.toLowerCase()));
        if (!filtered.length) {
            list.appendChild(el(`<p class="empty-state">No scout matches "${filter}"</p>`));
            return;
        }
        filtered.forEach(s => {
            const row = el(`
                <button class="scout-row">
                    <div class="scout-meta">
                        <div class="avatar" style="background:${patrolColor(s.patrol)}">${initials(s.name)}</div>
                        <div>
                            <div class="scout-name">${s.name}</div>
                            <div class="scout-sub">${s.lastRank || ''} - ${s.patrol || ''}</div>
                        </div>
                    </div>
                </button>
            `);
            row.onclick = () => { state.screen = { name: 'detail', scout: s }; render(); };
            list.appendChild(row);
        });
    }

    renderList();
    search.querySelector('input').addEventListener('input', e => renderList(e.target.value));
}

function renderScoutDetail(body, scout) {
    const header = el(`
        <div class="detail-header">
            <button class="back-btn">&larr;</button>
            <div>
                <div style="font-size:16px; font-weight:600;">${scout.name}</div>
                <div style="font-size:12px; color:var(--stone-400);">${scout.lastRank || ''} - ${scout.patrol || ''} Patrol</div>
            </div>
        </div>
    `);
    header.querySelector('.back-btn').onclick = () => { state.screen = { name: 'list' }; render(); };
    body.appendChild(header);

    const ranks = scout.inProgressRanks || [];
    const badges = scout.inProgressBadges || [];
    let activeTab = ranks.length ? `rank:${ranks[0].rank}` : 'badges';

    const tabs = el(`<div class="req-tabs"></div>`);
    const content = el(`<div style="flex:1; overflow-y:auto; padding:12px 16px;"></div>`);

    function renderTabs() {
        tabs.innerHTML = '';
        ranks.forEach(r => {
            const b = el(`<button class="${activeTab === 'rank:' + r.rank ? 'active' : ''}">${r.rank}</button>`);
            b.onclick = () => { activeTab = `rank:${r.rank}`; renderTabs(); renderContent(); };
            tabs.appendChild(b);
        });
        const bb = el(`<button class="${activeTab === 'badges' ? 'active' : ''}">Merit badges (${badges.length})</button>`);
        bb.onclick = () => { activeTab = 'badges'; renderTabs(); renderContent(); };
        tabs.appendChild(bb);
    }

    function reqRow(code, text, onTap) {
        const row = el(`
            <button class="req-row">
                <span class="req-code">${code}</span>
                <div style="flex:1;">
                    <span class="req-text">${text}</span>
                </div>
                <div class="check-circle"></div>
            </button>
        `);
        row.onclick = onTap;
        return row;
    }

    function renderContent() {
        content.innerHTML = '';

        if (activeTab.startsWith('rank:')) {
            const rankName = activeTab.slice(5);
            const rank = ranks.find(r => r.rank === rankName);
            content.appendChild(el(`<p style="font-size:11px; color:var(--stone-400); margin:0 0 8px 4px;">${rank.rank} v${rank.version}</p>`));
            rank.reqs.forEach(req => {
                content.appendChild(reqRow(req.code, req.text, () => stage('rank', rank.rank, rank.version, req.code, req.text, req.id)));
            });
        } else {
            if (!badges.length) {
                content.appendChild(el(`<p class="empty-state">No merit badges currently in progress</p>`));
            }
            badges.forEach(b => {
                const badgeHeader = el(`
                    <div style="display:flex; align-items:center; gap:6px; margin:16px 0 6px 4px;">
                        <span style="font-size:12px; font-weight:600; text-transform:uppercase; color:var(--stone-400);">${b.name}</span>
                        ${b.eagleRequired ? '<span class="pill eagle">EAGLE</span>' : ''}
                        <span style="font-size:10.5px; color:var(--stone-300);">v${b.version}</span>
                    </div>
                `);
                content.appendChild(badgeHeader);
                (b.groups || []).forEach(g => {
                    content.appendChild(el(`<p style="font-size:12px; color:var(--stone-500); margin:8px 0 4px 4px;"><b>${g.code}.</b> ${g.header}</p>`));
                    (g.items || []).forEach(item => {
                        content.appendChild(reqRow(item.code, item.text, () => stage('merit_badge', b.name, b.version, item.code, item.text, item.id)));
                    });
                });
            });
        }
    }

    function stage(type, name, version, code, text, reqId) {
        state.screen = {
            name: 'confirm', scout,
            pending: { type, name, version, code, text, reqId, date: todayISO(), comment: '' },
        };
        render();
    }

    renderTabs();
    renderContent();
    body.appendChild(tabs);
    body.appendChild(content);
}

function renderConfirmScreen(body, scout, pending) {
    const header = el(`
        <div class="detail-header">
            <button class="back-btn">&larr;</button>
            <div style="font-size:16px; font-weight:600;">Confirm sign-off</div>
        </div>
    `);
    header.querySelector('.back-btn').onclick = () => { state.screen = { name: 'detail', scout }; render(); };
    body.appendChild(header);

    const wrap = el(`<div class="confirm-body"></div>`);
    wrap.appendChild(el(`<p style="font-family:monospace; font-size:11px; color:var(--stone-400); margin:0 0 4px;">${pending.name} ${pending.code}</p>`));
    wrap.appendChild(el(`<p style="font-size:15px; font-weight:500; margin:0 0 24px;">${pending.text}</p>`));

    wrap.appendChild(el(`<label class="field-label">Completion date</label>`));
    const dateBox = el(`<div class="field-box"><input type="date" value="${pending.date}" max="${todayISO()}"></div>`);
    dateBox.querySelector('input').addEventListener('change', e => { pending.date = e.target.value; confirmBtn.textContent = `Sign off - ${formatDateLabel(pending.date)}`; });
    wrap.appendChild(dateBox);

    const commentHeader = el(`
        <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
            <label class="field-label" style="margin:0;">Comment <span style="text-transform:none; color:var(--stone-300);">(optional)</span></label>
            <span id="charcount" style="font-size:10.5px; color:var(--stone-300);">0/150</span>
        </div>
    `);
    wrap.appendChild(commentHeader);
    const commentBox = el(`<div class="field-box"><textarea rows="2" maxlength="150" placeholder="e.g. Completed on the Cedar Ridge campout"></textarea></div>`);
    commentBox.querySelector('textarea').addEventListener('input', e => {
        pending.comment = e.target.value;
        commentHeader.querySelector('#charcount').textContent = `${pending.comment.length}/150`;
    });
    wrap.appendChild(commentBox);
    wrap.appendChild(el(`<p style="font-size:11px; color:var(--stone-400);">Will be marked Approved</p>`));

    body.appendChild(wrap);

    const btnRow = el(`<div class="btn-row"></div>`);
    const cancelBtn = el(`<button class="btn btn-cancel">Cancel</button>`);
    cancelBtn.onclick = () => { state.screen = { name: 'detail', scout }; render(); };
    const confirmBtn = el(`<button class="btn btn-confirm">Sign off - ${formatDateLabel(pending.date)}</button>`);
    confirmBtn.onclick = async () => {
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Saving...';
        try {
            await api('queue.php', {
                method: 'POST',
                body: JSON.stringify({
                    scout_id: scout.id,
                    requirement_type: pending.type,
                    rank_requirement_id: pending.type === 'rank' ? pending.reqId : null,
                    merit_badge_requirement_id: pending.type === 'merit_badge' ? pending.reqId : null,
                    comment: pending.comment || null,
                    completion_date: pending.date,
                }),
            });
            state.loaded.queue = false; // force refresh next time Queue tab is opened
            showToast(scout, `${pending.name} ${pending.code}`, pending.date, pending.comment);
            state.screen = { name: 'detail', scout };
            render();
        } catch (err) {
            alert('Could not queue this signoff: ' + err.message);
            confirmBtn.disabled = false;
            confirmBtn.textContent = `Sign off - ${formatDateLabel(pending.date)}`;
        }
    };
    btnRow.appendChild(cancelBtn);
    btnRow.appendChild(confirmBtn);
    body.appendChild(btnRow);
}

function showToast(scout, label, date, comment) {
    const toast = el(`
        <div class="toast">
            <div style="width:28px; height:28px; border-radius:50%; background:var(--emerald-500); display:flex; align-items:center; justify-content:center; flex-shrink:0;"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7L5.5 10.5L12 3.5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
            <div>
                <div style="font-size:13px; font-weight:500;">Queued for ${scout.name}</div>
                <div style="font-size:11.5px; color:var(--stone-400);">${label} - ${formatDateLabel(date)}${comment ? ` - "${comment}"` : ''}</div>
            </div>
        </div>
    `);
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2600);
}

// ---- Queue tab ----------------------------------------------------------

async function loadQueue() {
    const data = await api('queue.php');
    state.queueScouts = data.scouts;
}

function renderQueueTab(body) {
    const totalItems = state.queueScouts.reduce((sum, s) => sum + s.items.length, 0);
    body.appendChild(el(`
        <div class="header">
            <h1>Queue</h1>
            <div class="sub">${state.queueScouts.length} scout${state.queueScouts.length !== 1 ? 's' : ''} - ${totalItems} item${totalItems !== 1 ? 's' : ''} pending</div>
        </div>
    `));

    if (!state.queueScouts.length) {
        body.appendChild(el(`<p class="empty-state">Nothing queued yet</p>`));
        return;
    }

    const list = el(`<div style="flex:1; overflow-y:auto; padding-top:4px;"></div>`);
    state.queueScouts.forEach(s => {
        const card = el(`
            <div class="queue-card">
                <div class="queue-card-header">
                    <div class="scout-meta">
                        <div class="avatar" style="background:${patrolColor(s.patrol)}">${initials(s.name)}</div>
                        <div>
                            <div class="scout-name">${s.name}</div>
                            <div class="scout-sub">${s.patrol || ''} - ${s.items.length} item${s.items.length !== 1 ? 's' : ''}</div>
                        </div>
                    </div>
                </div>
                <div class="items"></div>
            </div>
        `);
        const itemsWrap = card.querySelector('.items');
        s.items.forEach(item => {
            const row = el(`
                <div class="queue-item">
                    <div>
                        <span class="pill ${item.type === 'merit_badge' ? 'badge' : 'rank'}">${item.type === 'merit_badge' ? 'Badge' : 'Rank'}</span>
                        <div style="font-size:13.5px; font-weight:500; margin-top:4px;">${item.text}</div>
                        <div style="font-size:11.5px; color:var(--stone-400); margin-top:2px;">${formatDateLabel(item.completionDate)}${item.comment ? ` - "${item.comment}"` : ''}</div>
                    </div>
                    <button style="border:none; background:none; color:var(--stone-300); cursor:pointer; font-size:16px;">&times;</button>
                </div>
            `);
            row.querySelector('button').onclick = async () => {
                if (!confirm('Remove this from the queue? This cannot be undone.')) return;
                await api(`queue.php?id=${item.signoffId}`, { method: 'DELETE' });
                state.loaded.queue = false;
                render();
            };
            itemsWrap.appendChild(row);
        });
        list.appendChild(card);
    });
    body.appendChild(list);

    body.appendChild(el(`<p style="text-align:center; font-size:12px; color:var(--stone-400); padding:12px;">Syncs automatically Monday 10:00pm CT</p>`));
}

// ---- History tab ----------------------------------------------------------

async function loadHistory() {
    const data = await api('sync-runs.php?limit=20');
    state.runs = data.runs;
}

function renderHistoryTab(body) {
    if (state.historyScreen.name === 'list') renderHistoryList(body);
    else if (state.historyScreen.name === 'detail') renderRunDetail(body, state.historyScreen.run);
}

function runStatusVisuals(run) {
    const failed = run.status === 'failed';
    const partial = run.status === 'partial';
    const color = failed ? 'var(--red-400)' : (partial ? 'var(--amber-600)' : 'var(--emerald-500)');
    // Inline SVG instead of a Unicode symbol (?/?) — some mobile browser
    // font fallbacks don't include those glyphs and render a "?" instead.
    // SVG paths render identically everywhere, with no font dependency.
    let icon;
    if (failed) {
        icon = `<svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="vertical-align:-2px;"><path d="M2 2L12 12M12 2L2 12" stroke="${color}" stroke-width="2" stroke-linecap="round"/></svg>`;
    } else if (partial) {
        icon = `<svg width="4" height="14" viewBox="0 0 4 14" fill="none" style="vertical-align:-2px;"><rect x="0" y="0" width="4" height="9" rx="1" fill="${color}"/><rect x="0" y="11" width="4" height="3" rx="1.5" fill="${color}"/></svg>`;
    } else {
        icon = `<svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="vertical-align:-2px;"><path d="M2 7L5.5 10.5L12 3.5" stroke="${color}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
    }
    return { failed, icon, color };
}

function renderHistoryList(body) {
    body.appendChild(el(`
        <div class="header">
            <h1>Sync history</h1>
            <div class="sub">${state.runs.length} runs</div>
        </div>
    `));

    if (!state.runs.length) {
        body.appendChild(el(`<p class="empty-state">No syncs yet</p>`));
        return;
    }

    const list = el(`<div style="flex:1; overflow-y:auto;"></div>`);
    state.runs.forEach(run => {
        const { failed, icon, color } = runStatusVisuals(run);
        const row = el(`
            <button class="run-row" style="display:block; width:calc(100% - 32px); text-align:left; border:1px solid var(--stone-100); cursor:pointer;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span>${icon}</span>
                        <span style="font-size:13.5px; font-weight:500;">${new Date(run.started_at).toLocaleString(undefined, { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })}</span>
                    </div>
                    <span style="font-size:9.5px; font-weight:700; text-transform:uppercase; color:var(--stone-500); background:var(--stone-100); padding:2px 6px; border-radius:4px;">${run.run_type.replace('_', ' ')}</span>
                </div>
                ${failed
                    ? `<p style="font-size:12px; color:var(--red-400); margin:6px 0 0 24px;">${run.fail_reason || 'Run failed'}</p>`
                    : `<div style="font-size:12px; color:var(--stone-400); margin:6px 0 0 24px;">${run.push_confirmed_count} confirmed${run.push_flagged_count > 0 ? ` - <span style="color:var(--amber-600);">${run.push_flagged_count} flagged</span>` : ''} - ${run.pull_updated_count} roster updates</div>`
                }
            </button>
        `);
        row.onclick = () => { state.historyScreen = { name: 'detail', run: run.id }; render(); };
        list.appendChild(row);
    });
    body.appendChild(list);
}

async function renderRunDetail(body, runId) {
    const header = el(`
        <div class="detail-header">
            <button class="back-btn">&larr;</button>
            <div style="font-size:16px; font-weight:600;">Sync detail</div>
        </div>
    `);
    header.querySelector('.back-btn').onclick = () => { state.historyScreen = { name: 'list' }; render(); };
    body.appendChild(header);

    const content = el(`<div style="flex:1; overflow-y:auto; padding:0 16px;"><p class="empty-state">Loading...</p></div>`);
    body.appendChild(content);

    let data;
    try {
        data = await api(`sync-runs.php?id=${runId}`);
    } catch (err) {
        content.innerHTML = `<p class="empty-state">Couldn't load this run's detail: ${err.message}</p>`;
        return;
    }

    const run = data.run;
    const { failed, icon, color } = runStatusVisuals(run);
    const items = run.items || [];
    const confirmed = items.filter(i => i.category === 'confirmed');
    const flagged = items.filter(i => i.category === 'flagged');
    const rosterUpdates = items.filter(i => i.category === 'roster_update' || i.category === 'requirement_status_update');

    content.innerHTML = '';

    content.appendChild(el(`
        <div style="padding:12px 0; border-bottom:1px solid var(--stone-100); margin-bottom:12px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <span>${icon}</span>
                <span style="font-size:14px; font-weight:600;">${new Date(run.started_at).toLocaleString()}</span>
            </div>
            ${failed
                ? `<p style="font-size:12.5px; color:var(--red-400); margin:6px 0 0;">${run.fail_reason || 'Run failed'}</p>`
                : `<p style="font-size:12px; color:var(--stone-400); margin:6px 0 0;">${run.push_confirmed_count} confirmed - ${run.push_flagged_count} flagged - ${run.pull_updated_count} roster updates</p>`
            }
        </div>
    `));

    if (flagged.length) {
        content.appendChild(el(`<p style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--amber-700); margin:12px 0 6px;">Flagged (${flagged.length})</p>`));
        flagged.forEach(item => {
            content.appendChild(el(`
                <div style="background:var(--amber-50); border-radius:10px; padding:10px 12px; margin-bottom:6px;">
                    <div style="font-size:13px; font-weight:500;">${item.scout_name || 'Unknown scout'} - ${item.description}</div>
                    ${item.detail ? `<div style="font-size:11.5px; color:var(--amber-700); margin-top:2px;">${item.detail}</div>` : ''}
                </div>
            `));
        });
    }

    if (confirmed.length) {
        content.appendChild(el(`<p style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--stone-400); margin:12px 0 6px;">Confirmed (${confirmed.length})</p>`));
        confirmed.forEach(item => {
            content.appendChild(el(`
                <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--stone-50);">
                    <span style="font-size:13px;">${item.scout_name || 'Unknown scout'}</span>
                    <span style="font-size:12px; color:var(--stone-400);">${item.description}</span>
                </div>
            `));
        });
    }

    if (rosterUpdates.length) {
        content.appendChild(el(`<p style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--stone-400); margin:12px 0 6px;">Roster &amp; requirement updates (${rosterUpdates.length})</p>`));
        rosterUpdates.forEach(item => {
            content.appendChild(el(`
                <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--stone-50);">
                    <span style="font-size:13px;">${item.scout_name || item.description}</span>
                    <span style="font-size:12px; color:var(--stone-400);">${item.scout_name ? item.description : ''}</span>
                </div>
            `));
        });
    }

    if (!items.length) {
        content.appendChild(el(`<p class="empty-state">No item-level detail was logged for this run (likely an older run from before detail logging was added).</p>`));
    }
}

// ---- Boot -----------------------------------------------------------------

render();