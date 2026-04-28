/* Trailhead Meeting Mode — client-side logic */

const RANK = {
  tf1a:'Tenderfoot 1a', tf1b:'Tenderfoot 1b', tf1c:'Tenderfoot 1c',
  tf1d:'Tenderfoot 1d', tf1e:'Tenderfoot 1e',
  tf2a:'Tenderfoot 2a', tf2b:'Tenderfoot 2b', tf2c:'Tenderfoot 2c',
  tf3a:'Tenderfoot 3a', tf3b:'Tenderfoot 3b', tf3c:'Tenderfoot 3c', tf3d:'Tenderfoot 3d',
  tf4a:'Tenderfoot 4a', tf4b:'Tenderfoot 4b',
  sc1a:'Second Class 1a', sc1b:'Second Class 1b', sc1c:'Second Class 1c',
  sc2a:'Second Class 2a', sc2b:'Second Class 2b', sc2c:'Second Class 2c',
  sc3a:'Second Class 3a', sc3b:'Second Class 3b', sc3c:'Second Class 3c',
  fc1a:'First Class 1a', fc1b:'First Class 1b', fc1c:'First Class 1c',
  fc2a:'First Class 2a', fc2b:'First Class 2b',
  scout1a:'Scout 1a', scout1b:'Scout 1b', scout1c:'Scout 1c',
  scout1d:'Scout 1d', scout1e:'Scout 1e', scout1f:'Scout 1f',
  star1:'Star 1', star2:'Star 2', life1:'Life 1', life2:'Life 2'
};

const MONTHS = {
  january:1, jan:1, february:2, feb:2, march:3, mar:3,
  april:4, apr:4, may:5, june:6, jun:6,
  july:7, jul:7, august:8, aug:8, september:9, sept:9, sep:9,
  october:10, oct:10, november:11, nov:11, december:12, dec:12
};

/**
 * normalizeDictation(line)
 */
function normalizeDictation(line) {
  const NUM_WORDS = {
    'zero':'0','one':'1','won':'1','two':'2','to':'2','too':'2',
    'three':'3','tree':'3','four':'4','for':'4','fore':'4',
    'five':'5','six':'6','seven':'7','eight':'8','ate':'8','nine':'9'
  };
  const LETTER_WORDS = {
    'a':'a','b':'b','be':'b','bee':'b','c':'c','see':'c','sea':'c',
    'd':'d','dee':'d','e':'e','f':'f','ef':'f'
  };
  const numPattern  = Object.keys(NUM_WORDS).join('|');
  const letPattern  = Object.keys(LETTER_WORDS).join('|');
  const spokenReqRe = new RegExp(
    '\\b(' + numPattern + ')\\s+(' + letPattern + ')\\b', 'gi'
  );
  let result = line.replace(spokenReqRe, (match, numWord, letWord) => {
    const digit  = NUM_WORDS[numWord.toLowerCase()];
    const letter = LETTER_WORDS[letWord.toLowerCase()];
    if (digit && letter) return digit + letter;
    return match;
  });
  result = result.replace(/\b(\d)\s+([a-fA-F])\b/g, (m, d, l) => d + l.toLowerCase());
  result = result.replace(/\b(\d)([A-F])\b/g, (m, d, l) => d + l.toLowerCase());
  return result;
}

/**
 * normalizeDate(str) → "M/D/YYYY" or null
 */
function normalizeDate(str) {
  if (!str) return null;
  const s = str.trim();
  const slash = s.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/);
  if (slash) {
    const y = slash[3].length === 2 ? '20' + slash[3] : slash[3];
    return parseInt(slash[1]) + '/' + parseInt(slash[2]) + '/' + y;
  }
  const iso = s.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (iso) return parseInt(iso[2]) + '/' + parseInt(iso[3]) + '/' + iso[1];
  const clean = s.replace(/(\d+)(?:st|nd|rd|th)/gi, '$1').replace(/,/g, '').trim();
  const words = clean.toLowerCase().split(/\s+/);
  let month = null, day = null, year = null;
  words.forEach(w => {
    if (MONTHS[w]) {
      month = MONTHS[w];
    } else if (/^\d+$/.test(w)) {
      const n = parseInt(w);
      if (n >= 2000)           year = n;
      else if (!day && n >= 1 && n <= 31) day = n;
    }
  });
  if (month && day && year) return month + '/' + day + '/' + year;
  if (month && day)         return month + '/' + day + '/' + new Date().getFullYear();
  return null;
}

/**
 * extractDate(line) → { date: "M/D/YYYY", cleaned: lineWithoutDate }
 */
function extractDate(line) {
  const today = todayStr();
  const numericRe = /\b(\d{4}-\d{2}-\d{2}|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\b/;
  const nm = line.match(numericRe);
  if (nm) {
    const normalized = normalizeDate(nm[1]);
    if (normalized) return { date: normalized, cleaned: line.replace(nm[0], '').trim() };
  }
  const spokenMDY = /\b(january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sept?|oct|nov|dec)\s+(\d{1,2})(?:st|nd|rd|th)?[,]?\s+(\d{4})\b/i;
  const m1 = line.match(spokenMDY);
  if (m1) {
    const mo = MONTHS[m1[1].toLowerCase()];
    const dy = parseInt(m1[2]);
    const yr = parseInt(m1[3]);
    if (mo && dy && yr) return { date: mo + '/' + dy + '/' + yr, cleaned: line.replace(m1[0], '').trim() };
  }
  const spokenMD = /\b(january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sept?|oct|nov|dec)\s+(\d{1,2})(?:st|nd|rd|th)?\b/i;
  const m2 = line.match(spokenMD);
  if (m2) {
    const mo = MONTHS[m2[1].toLowerCase()];
    const dy = parseInt(m2[2]);
    if (mo && dy) return { date: mo + '/' + dy + '/' + new Date().getFullYear(), cleaned: line.replace(m2[0], '').trim() };
  }
  const spokenDMY = /\b(\d{1,2})(?:st|nd|rd|th)?\s+(?:of\s+)?(january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sept?|oct|nov|dec)(?:\s+(\d{4}))?\b/i;
  const m3 = line.match(spokenDMY);
  if (m3) {
    const dy = parseInt(m3[1]);
    const mo = MONTHS[m3[2].toLowerCase()];
    const yr = m3[3] ? parseInt(m3[3]) : new Date().getFullYear();
    if (mo && dy) return { date: mo + '/' + dy + '/' + yr, cleaned: line.replace(m3[0], '').trim() };
  }
  return { date: today, cleaned: line };
}

let parsedRows = [], queueRows = [], lastParsedResults = [], currentLogText = '';

/* ── Server-side queue persistence ── */
function saveQueue() {
  const fd = new FormData();
  fd.append('action', 'save_queue');
  fd.append('queue_json', JSON.stringify(queueRows));
  fetch(window.location.href, { method: 'POST', body: fd }).catch(() => {});
  try { localStorage.setItem('trailhead_queue', JSON.stringify(queueRows)); } catch(e) {}
}

function loadQueue() {
  const fd = new FormData();
  fd.append('action', 'load_queue');
  fetch(window.location.href, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.ok && Array.isArray(data.queue) && data.queue.length) {
        queueRows = data.queue;
        renderQueue();
        showRestoreBanner(queueRows.length);
      }
    })
    .catch(() => {
      try {
        const raw = localStorage.getItem('trailhead_queue');
        if (raw) {
          const saved = JSON.parse(raw);
          if (Array.isArray(saved) && saved.length) {
            queueRows = saved;
            renderQueue();
            showRestoreBanner(queueRows.length);
          }
        }
      } catch(e) {}
    });
}

function showRestoreBanner(count) {
  const banner = document.getElementById('restoreBanner');
  if (!banner) return;
  banner.textContent = '\u21ba Queue restored (' + count + ' item' + (count === 1 ? '' : 's') + ') from your last session.';
  banner.style.display = 'block';
  setTimeout(() => { banner.style.display = 'none'; }, 6000);
}

function clearSavedQueue() {
  const fd = new FormData();
  fd.append('action', 'save_queue');
  fd.append('queue_json', '[]');
  fetch(window.location.href, { method: 'POST', body: fd }).catch(() => {});
  try { localStorage.removeItem('trailhead_queue'); } catch(e) {}
}

function todayStr() {
  const d = document.getElementById('defaultDate').value;
  if (d) {
    const [y, m, day] = d.split('-');
    return parseInt(m) + '/' + parseInt(day) + '/' + y;
  }
  const n = new Date();
  return (n.getMonth()+1) + '/' + n.getDate() + '/' + n.getFullYear();
}

function typeBadge(t) {
  return t === 'mb'
    ? '<span class="badge b-mb">Merit Badge</span>'
    : '<span class="badge b-rank">Rank</span>';
}

function esc(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

function resolveRankItem(rankName, reqNum) {
  const rawItem  = rankName + ' ' + reqNum;
  const aliasKey = rawItem.toLowerCase()
    .replace(/\s+/g, '')
    .replace('secondclass', 'sc')
    .replace('firstclass', 'fc')
    .replace('tenderfoot', 'tf');
  return RANK[aliasKey] || rawItem;
}

function parseNotes(raw) {
  return raw.trim().split('\n').filter(l => l.trim()).flatMap(line => {
    line = normalizeDictation(line);
    const cm = line.match(/comment:\s*(.+)$/i);
    const comment = cm ? cm[1].trim() : '';
    let clean = line.replace(/comment:.+$/i, '').trim();
    const { date, cleaned: noDate } = extractDate(clean);

    const nlMB = noDate.match(
      /^(.+?)\s+(?:completed|passed|finished|earned|did|got)\s+(.+?)\s+(?:merit\s*badge|MB)\s+req(?:uirement)?s?\s*([\d\w]+(?:[,\s]+[\d\w]+)*)$/i
    );
    if (nlMB) {
      const name  = nlMB[1].trim();
      const badge = nlMB[2].trim();
      const reqs  = nlMB[3].split(/[,\s]+/).map(r => r.replace(/[^\w]/g, '')).filter(Boolean);
      return reqs.map(req => ({ name, type:'mb', badge, item: badge + ' \u2014 Req ' + req, req, date, comment }));
    }

    const shMB = noDate.match(/^(.+?)\s+mb:(.+?)\s+((?:req[\d\w]+\s*)+)$/i);
    if (shMB) {
      const name  = shMB[1].trim();
      const badge = shMB[2].trim();
      const reqs  = shMB[3].trim().split(/\s+/).filter(Boolean);
      return reqs.map(req => ({
        name, type:'mb', badge,
        item: badge + ' \u2014 ' + req.replace(/req/i, 'Req '),
        req: req.replace(/req/i, '').trim(),
        date, comment
      }));
    }

    const nlRank = noDate.match(
      /^(.+?)\s+(?:completed|passed|finished|earned|achieved|got|received)\s+(Scout|Tenderfoot|Second\s+Class|First\s+Class|Star|Life|Eagle)(?:\s+rank)?$/i
    );
    if (nlRank) {
      const name = nlRank[1].trim();
      const rank = nlRank[2].replace(/\s+/g, ' ').trim();
      return [{ name, type:'rank', badge:null, item: rank + ' (Full Rank)', req:null, date, comment }];
    }

    const nlRankReqVerb = noDate.match(
      /^(.+?)\s+(?:completed|passed|finished|earned|did|got)\s+(?:requirement\s+)?((?:Tenderfoot|Second\s+Class|First\s+Class|Scout|Star|Life)(?:\s+rank)?)\s+([\da-z]+)$/i
    );
    if (nlRankReqVerb) {
      const name     = nlRankReqVerb[1].trim();
      const rankName = nlRankReqVerb[2].replace(/\s+rank$/i, '').replace(/\s+/g, ' ').trim();
      const reqNum   = nlRankReqVerb[3].trim();
      return [{ name, type:'rank', badge:null, item: resolveRankItem(rankName, reqNum), req:null, date, comment }];
    }

    const nlRankReqNoVerb = noDate.match(
      /^(.+?)\s+((?:Tenderfoot|Second\s+Class|First\s+Class|Scout|Star|Life)(?:\s+rank)?)\s+([\da-z]+)$/i
    );
    if (nlRankReqNoVerb) {
      const name     = nlRankReqNoVerb[1].trim();
      const rankName = nlRankReqNoVerb[2].replace(/\s+rank$/i, '').replace(/\s+/g, ' ').trim();
      const reqNum   = nlRankReqNoVerb[3].trim();
      return [{ name, type:'rank', badge:null, item: resolveRankItem(rankName, reqNum), req:null, date, comment }];
    }

    const tokens = noDate.split(/\s+/).filter(Boolean);
    const nameT = [], itemT = [];
    tokens.forEach(t => {
      if (RANK[t.toLowerCase()]) itemT.push(t.toLowerCase());
      else nameT.push(t);
    });
    const name = nameT.join(' ').trim();
    if (name && itemT.length) {
      return itemT.map(item => ({ name, type:'rank', badge:null, item: RANK[item], req:null, date, comment }));
    }

    if (noDate.trim()) {
      return [{ name: noDate.trim(), type:'rank', badge:null, item:'(could not parse)', req:null, date, comment, unparsed:true }];
    }
    return [];
  });
}

function renderPreview() {
  const b = document.getElementById('previewBody');
  document.getElementById('previewCard').style.display = 'block';
  if (!parsedRows.length) {
    b.innerHTML = '<tr><td colspan="4" class="empty">No items parsed. Check your note format.</td></tr>';
    return;
  }
  b.innerHTML = parsedRows.map(r =>
    '<tr' + (r.unparsed ? ' style="background:#fff7ed"' : '') + '>'
    + '<td>' + esc(r.name) + '</td>'
    + '<td>' + typeBadge(r.type) + '</td>'
    + '<td>' + esc(r.item) + (r.unparsed ? ' <span style="color:#b45309;font-size:0.75rem">\u26a0\ufe0f could not parse</span>' : '') + '</td>'
    + '<td>' + esc(r.date) + '</td>'
    + '</tr>'
  ).join('');
}

function renderQueue() {
  const b = document.getElementById('queueBody');
  if (!queueRows.length) {
    document.getElementById('queueCard').style.display = 'none';
    return;
  }
  document.getElementById('queueCard').style.display = 'block';
  document.getElementById('queueCount').textContent = '(' + queueRows.length + ' items)';
  b.innerHTML = queueRows.map((r, i) =>
    '<tr' + (r.unparsed ? ' style="background:#fff7ed"' : '') + '>'
    + '<td>' + (i+1) + '</td>'
    + '<td>' + esc(r.name) + '</td>'
    + '<td>' + typeBadge(r.type) + '</td>'
    + '<td>' + esc(r.item) + (r.unparsed ? ' <span style="color:#b45309;font-size:0.75rem">\u26a0\ufe0f</span>' : '') + '</td>'
    + '<td>' + esc(r.date) + '</td>'
    + '<td><button class="btn btn-red btn-sm" onclick="removeFromQueue(' + i + ')">\u2715</button></td>'
    + '</tr>'
  ).join('');
  document.getElementById('sendCard').style.display = 'block';
  document.getElementById('promptBox').textContent = buildPrompt(queueRows);
}

function updateStatus() {
  const total    = parsedRows.length;
  const scouts   = new Set(parsedRows.map(r => r.name)).size;
  const mb       = parsedRows.filter(r => r.type === 'mb').length;
  const rank     = parsedRows.filter(r => r.type === 'rank').length;
  const unparsed = parsedRows.filter(r => r.unparsed).length;
  const bar      = document.getElementById('statusBar');
  if (!total) { bar.textContent = 'Nothing parsed yet.'; return; }
  bar.innerHTML = '<span class="pill">' + total + ' items</span> &middot; '
    + scouts + ' scouts &middot; '
    + rank + ' rank &middot; '
    + mb + ' MB'
    + (unparsed ? ' &middot; <span style="color:#b45309">' + unparsed + ' could not parse</span>' : '');
}

function removeFromQueue(i) {
  queueRows.splice(i, 1);
  saveQueue();
  renderQueue();
}

function doParse() {
  try {
    const notes = document.getElementById('notes').value;
    if (!notes.trim()) { alert('Enter some notes first.'); return; }
    parsedRows = parseNotes(notes);
    renderPreview();
    updateStatus();
    document.getElementById('previewCard').scrollIntoView({ behavior:'smooth', block:'nearest' });
  } catch(e) {
    alert('Parse error: ' + e.message);
    console.error(e);
  }
}

function doAddToQueue() {
  if (!parsedRows.length) { alert('Parse your notes first.'); return; }
  queueRows = queueRows.concat(parsedRows);
  saveQueue();
  parsedRows = [];
  document.getElementById('notes').value = '';
  document.getElementById('previewCard').style.display = 'none';
  document.getElementById('statusBar').textContent = 'Nothing parsed yet.';
  renderQueue();
  document.getElementById('queueCard').scrollIntoView({ behavior:'smooth', block:'nearest' });
}

function clearAll() {
  document.getElementById('notes').value = '';
  parsedRows = []; queueRows = [];
  clearSavedQueue();
  document.getElementById('previewCard').style.display = 'none';
  document.getElementById('queueCard').style.display   = 'none';
  document.getElementById('sendCard').style.display    = 'none';
  updateStatus();
}

function insertChip(txt) {
  const ta = document.getElementById('notes');
  const v  = ta.value;
  ta.value = v + (v && !v.endsWith('\n') ? '\n' : '') + txt + '\n';
  ta.focus();
}

function buildAction(r, i) {
  const idx   = i + 1;
  const arrow = ' \u2192 ';
  if (r.type === 'mb') {
    let s = '  ' + idx + '. Scout: ' + r.name
      + '\n     Type: Merit Badge requirement'
      + '\n     Badge: ' + r.badge
      + '\n     Requirement: ' + r.req
      + '\n     Date: ' + r.date
      + '\n     Navigation: Find scout' + arrow + 'scroll to Merit Badges' + arrow
      + 'View All' + arrow + 'search "' + r.badge + '"' + arrow
      + 'open badge' + arrow + 'find Req ' + r.req + arrow + 'approve';
    if (r.comment) s += '\n     Comment: ' + r.comment;
    return s;
  }
  let s = '  ' + idx + '. Scout: ' + r.name
    + '\n     Type: Rank requirement'
    + '\n     Item: ' + r.item
    + '\n     Date: ' + r.date
    + '\n     Navigation: Find scout' + arrow + 'open rank card' + arrow
    + 'View More' + arrow + 'find ' + r.item + arrow + 'approve';
  if (r.comment) s += '\n     Comment: ' + r.comment;
  return s;
}

function buildPrompt(rows) {
  if (!rows.length) return '\u2190 Parse your notes and add items to the queue first.';
  const actions = rows.map((r, i) => buildAction(r, i)).join('\n\n');
  return 'Use Scoutbook Plus to enter rank advancements and merit badge requirements.\n\n'
    + 'ACCOUNT: chrispowell6203\n'
    + 'UNIT CONTEXT: Scouts BSA Troop 911 Boys \u2014 Position: Scoutmaster\n'
    + 'SITE: https://advancements.scouting.org\n\n'
    + 'LOGIN STEPS (do these first):\n'
    + '- Navigate to https://advancements.scouting.org\n'
    + '- Wait for the page to fully load.\n'
    + '- LastPass will auto-fill username and password \u2014 confirm both fields are populated.\n'
    + '- Click the "I\'m not a robot" reCAPTCHA v2 checkbox and wait for the green checkmark.\n'
    + '- If an image challenge appears instead, STOP and notify me \u2014 I will complete it manually.\n'
    + '- Click the Login button and wait for the dashboard to load.\n'
    + '- Dismiss any startup popup.\n'
    + '- Verify account is chrispowell6203 and context is Troop 911 Scoutmaster before proceeding.\n\n'
    + 'GLOBAL RULES:\n'
    + '- Use the search field to find each scout by name.\n'
    + '- Process all items independently; continue the full queue even if one item fails.\n'
    + '- Confirm the requirement is NOT already Approved before making any change.\n'
    + '- If already approved: report Needs review \u2014 Already approved. Do not re-enter.\n'
    + '- If the scout cannot be found confidently, multiple scouts match, the requirement cannot be found,\n'
    + '  the merit badge cannot be matched exactly, or the page state is unexpected:\n'
    + '  do not guess \u2014 add to FAILED or NEEDS REVIEW with reason, then continue.\n'
    + '- Mark items Approved, set the date exactly as given, add comment if listed.\n'
    + '- Verify the requirement shows Approved with the correct date after saving.\n'
    + '- Only stop the entire run for a blocking login/session failure.\n\n'
    + 'NAVIGATION PATHS:\n'
    + '- Rank requirements: search scout \u2192 open rank card \u2192 View More \u2192 find requirement \u2192 approve\n'
    + '- Merit badge requirements: search scout \u2192 scroll to Merit Badges \u2192 View All \u2192\n'
    + '  search badge name \u2192 open badge \u2192 find requirement \u2192 approve\n'
    + '  (Use "View All" \u2014 do NOT rely on pending/started status)\n\n'
    + 'ACTIONS (process in order):\n\n'
    + actions
    + '\n\nREQUIRED OUTPUT FORMAT (after all actions are complete):\n\n'
    + 'SUCCESS\n'
    + 'Scout: [name] | Item: [item] | Date: [date] | Result: Success\n\n'
    + 'FAILED\n'
    + 'Scout: [name] | Item: [item] | Date: [date] | Result: Failed | Reason: [brief reason]\n\n'
    + 'NEEDS REVIEW\n'
    + 'Scout: [name] | Item: [item] | Date: [date] | Result: Needs review | Reason: [Already approved / Multiple scouts matched / Requirement not found / Merit badge not found / Unexpected page state / CAPTCHA image challenge]\n\n'
    + 'Then: "Done. X succeeded, Y failed, Z need review."';
}

function togglePrompt() {
  const w = document.getElementById('promptWrap');
  w.style.display = w.style.display === 'none' ? 'block' : 'none';
}

/**
 * doSend() — saves queue to server, then opens /computer in a new tab.
 * Comet can open that tab and the full prompt is right there, ready to read.
 */
function doSend() {
  if (!queueRows.length) { alert('Add items to the queue first.'); return; }
  const btn = document.getElementById('sendBtn');
  btn.disabled = true;
  btn.textContent = '\u23f3 Saving\u2026';

  const fd = new FormData();
  fd.append('action', 'save_queue');
  fd.append('queue_json', JSON.stringify(queueRows));

  fetch(window.location.href, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(() => {
      btn.disabled = false;
      btn.textContent = '\u2705 Saved! Opening Computer tab\u2026';
      window.open('/computer', '_blank');
      setTimeout(() => { btn.textContent = '\uD83D\uDDA5\uFE0F Send to Computer'; }, 3000);
    })
    .catch(() => {
      btn.disabled = false;
      btn.textContent = '\uD83D\uDDA5\uFE0F Send to Computer';
      // Fallback: open /computer anyway (queue may already be saved from last render)
      window.open('/computer', '_blank');
    });
}

function doCopyOnly() {
  navigator.clipboard.writeText(document.getElementById('promptBox').textContent).then(() => {
    const btn = document.getElementById('copyOnlyBtn');
    btn.textContent = '\u2705 Copied!';
    setTimeout(() => { btn.textContent = '\uD83D\uDCCB Copy prompt only'; }, 2000);
  });
}

function parseResults(raw) {
  let section = '';
  return raw.trim().split('\n').reduce((acc, line) => {
    const t = line.trim();
    if (!t) return acc;
    if (/^SUCCESS$/i.test(t))      { section = 'SUCCESS';      return acc; }
    if (/^FAILED$/i.test(t))       { section = 'FAILED';       return acc; }
    if (/^NEEDS REVIEW$/i.test(t)) { section = 'NEEDS REVIEW'; return acc; }
    const m = t.match(
      /Scout:\s*(.+?)\s*\|\s*Item:\s*(.+?)\s*\|\s*Date:\s*(.+?)\s*\|\s*Result:\s*(Success|Failed|Needs review)(?:\s*\|\s*Reason:\s*(.+))?/i
    );
    if (m) acc.push({
      name:   m[1].trim(),
      item:   m[2].trim(),
      date:   m[3].trim(),
      result: m[4].trim(),
      reason: (m[5] || '').trim(),
      section
    });
    return acc;
  }, []);
}

function doParseResults() {
  const raw = document.getElementById('resultPaste').value.trim();
  if (!raw) { alert('Paste Computer results first.'); return; }
  lastParsedResults = parseResults(raw);
  renderResultSummary(lastParsedResults);
  const hasReview = lastParsedResults.some(r => r.result.toLowerCase() === 'needs review');
  document.getElementById('reviewSection').style.display = hasReview ? 'block' : 'none';
  buildLogText(raw, lastParsedResults);
}

function renderResultSummary(results) {
  const success = results.filter(r => r.result.toLowerCase() === 'success');
  const failed  = results.filter(r => r.result.toLowerCase() === 'failed');
  const review  = results.filter(r => r.result.toLowerCase() === 'needs review');
  let html = '<div class="card"><h2>&#128202; Results Summary</h2><div class="result-grid">'
    + '<div class="result-card rc-success"><div class="num">' + success.length + '</div><div class="lbl">Succeeded</div></div>'
    + '<div class="result-card rc-failed"><div class="num">'  + failed.length  + '</div><div class="lbl">Failed</div></div>'
    + '<div class="result-card rc-review"><div class="num">'  + review.length  + '</div><div class="lbl">Needs Review</div></div>'
    + '</div>';
  if (results.length) {
    html += '<div class="tbl-wrap"><table><thead><tr><th>Scout</th><th>Item</th><th>Date</th><th>Result</th><th>Reason</th></tr></thead><tbody>';
    results.forEach(r => {
      const cls = r.result.toLowerCase() === 'success' ? 'b-success'
                : r.result.toLowerCase() === 'failed'  ? 'b-failed' : 'b-review';
      html += '<tr>'
        + '<td>' + esc(r.name)   + '</td>'
        + '<td>' + esc(r.item)   + '</td>'
        + '<td>' + esc(r.date)   + '</td>'
        + '<td><span class="badge ' + cls + '">' + esc(r.result) + '</span></td>'
        + '<td>' + esc(r.reason || '\u2014') + '</td>'
        + '</tr>';
    });
    html += '</tbody></table></div>';
  }
  html += '</div>';
  document.getElementById('resultSummary').innerHTML = html;
}

function buildLogText(raw, results) {
  const s  = results.filter(r => r.result.toLowerCase() === 'success').length;
  const f  = results.filter(r => r.result.toLowerCase() === 'failed').length;
  const rv = results.filter(r => r.result.toLowerCase() === 'needs review').length;
  const session = document.getElementById('sessionName').value || 'Unnamed session';
  const d = new Date();
  currentLogText = 'SESSION: ' + session
    + '\nDATE: ' + d.toLocaleDateString() + ' ' + d.toLocaleTimeString()
    + '\nSUMMARY: ' + s + ' succeeded \u00b7 ' + f + ' failed \u00b7 ' + rv + ' needs review'
    + '\n\n' + raw;
  document.getElementById('logBox').textContent = currentLogText;
  document.getElementById('logSection').style.display = 'block';
}

function doExportLog() {
  const a = document.createElement('a');
  a.href     = 'data:text/plain;charset=utf-8,' + encodeURIComponent(currentLogText);
  a.download = 'trailhead-log-' + Date.now() + '.txt';
  a.click();
}

function doSaveRun() {
  const results = lastParsedResults;
  const s  = results.filter(r => r.result.toLowerCase() === 'success').length;
  const f  = results.filter(r => r.result.toLowerCase() === 'failed').length;
  const rv = results.filter(r => r.result.toLowerCase() === 'needs review').length;
  const fd = new FormData();
  fd.append('action',       'save_run');
  fd.append('session_name', document.getElementById('sessionName').value || '');
  fd.append('raw_notes',    document.getElementById('notes').value || '');
  fd.append('prompt',       document.getElementById('promptBox').textContent || '');
  fd.append('raw_results',  document.getElementById('resultPaste').value || '');
  fd.append('summary',      s + ' succeeded \u00b7 ' + f + ' failed \u00b7 ' + rv + ' needs review');
  fetch(window.location.href, { method:'POST', body:fd })
    .then(r => r.json())
    .then(d => {
      if (d.ok) { alert('Run saved!'); showTab('history', document.querySelectorAll('.tab')[2]); }
    })
    .catch(() => alert('Save failed \u2014 check connection.'));
}

function buildReviewPrompt(results) {
  const items = results.filter(r => r.result.toLowerCase() === 'needs review');
  if (!items.length) return '\u2190 No NEEDS REVIEW items found.';
  const lines = items.map((r, i) =>
    '  ' + (i+1) + '. Scout: ' + r.name
    + '\n     Item: ' + r.item
    + '\n     Date: ' + r.date
    + '\n     Prior reason: ' + (r.reason || 'Needs review')
    + '\n     Instruction: Re-attempt after my clarification. If still unclear, report Needs review again.'
  ).join('\n\n');
  return 'Use Scoutbook Plus to resolve only items previously returned as NEEDS REVIEW.\n\n'
    + 'ACCOUNT: chrispowell6203\n'
    + 'UNIT CONTEXT: Scouts BSA Troop 911 Boys \u2014 Position: Scoutmaster\n'
    + 'SITE: https://advancements.scouting.org\n\n'
    + 'LOGIN STEPS: Same as primary run \u2014 LastPass auto-fills, handle CAPTCHA manually if needed.\n\n'
    + 'GLOBAL RULES:\n'
    + '- Work ONLY the items listed below.\n'
    + '- Re-attempt only after applying my guidance or decision.\n'
    + '- If already approved, report Needs review: Already approved.\n'
    + '- If scout/requirement still cannot be found confidently, report Needs review with reason.\n\n'
    + 'ITEMS TO RESOLVE:\n\n'
    + lines
    + '\n\nREQUIRED OUTPUT FORMAT:\n\n'
    + 'SUCCESS\nScout: [name] | Item: [item] | Date: [date] | Result: Success\n\n'
    + 'FAILED\nScout: [name] | Item: [item] | Date: [date] | Result: Failed | Reason: [brief reason]\n\n'
    + 'NEEDS REVIEW\nScout: [name] | Item: [item] | Date: [date] | Result: Needs review | Reason: [brief reason]\n\n'
    + 'Then: "Done. X succeeded, Y failed, Z need review."';
}

function doBuildReview() {
  const p = buildReviewPrompt(lastParsedResults);
  document.getElementById('reviewPromptBox').textContent = p;
  document.getElementById('reviewPromptWrap').style.display = 'block';
}

function doSendReview() {
  const p = document.getElementById('reviewPromptBox').textContent;
  if (p.startsWith('\u2190')) { alert('Parse results first.'); return; }
  navigator.clipboard.writeText(p).then(() => {
    const btn = document.querySelector('#reviewSection .btn-send');
    btn.textContent = '\u2705 Copied! Paste into Computer.';
    setTimeout(() => { btn.textContent = '\uD83D\uDCCB Copy Review Prompt for Computer'; }, 3000);
  });
}

function doCopyReview() {
  navigator.clipboard.writeText(document.getElementById('reviewPromptBox').textContent).then(() => {
    const btn = document.querySelector('#reviewSection .btn-ghost');
    btn.textContent = '\u2705 Copied!';
    setTimeout(() => { btn.textContent = '\uD83D\uDCCB Copy only'; }, 2000);
  });
}

function clearResults() {
  document.getElementById('resultPaste').value = '';
  document.getElementById('resultSummary').innerHTML = '';
  document.getElementById('reviewSection').style.display = 'none';
  document.getElementById('logSection').style.display    = 'none';
}

let recognition = null, isRecording = false;
function toggleMic() {
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SR) { alert('Voice dictation not supported in this browser. Try Chrome or Safari.'); return; }
  if (isRecording) { recognition.stop(); return; }
  recognition = new SR();
  recognition.continuous     = true;
  recognition.interimResults = false;
  recognition.lang           = 'en-US';
  recognition.onresult = e => {
    const t  = Array.from(e.results).slice(e.resultIndex).map(r => r[0].transcript).join(' ');
    const ta = document.getElementById('notes');
    const v  = ta.value;
    ta.value = v + (v && !v.endsWith('\n') ? '\n' : '') + t.trim() + '\n';
  };
  recognition.onend  = () => { isRecording = false; document.getElementById('micBtn').classList.remove('recording'); document.getElementById('micBtn').textContent = '\uD83C\uDFA4 Dictate'; };
  recognition.onerror = () => { isRecording = false; document.getElementById('micBtn').classList.remove('recording'); document.getElementById('micBtn').textContent = '\uD83C\uDFA4 Dictate'; };
  recognition.start();
  isRecording = true;
  document.getElementById('micBtn').classList.add('recording');
  document.getElementById('micBtn').textContent = '\uD83D\uDD34 Stop';
}

function showTab(name, btn) {
  document.querySelectorAll('.pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  btn.classList.add('active');
}

document.addEventListener('DOMContentLoaded', function() {
  const d = new Date();
  document.getElementById('defaultDate').value = d.toISOString().split('T')[0];
  loadQueue();
});
