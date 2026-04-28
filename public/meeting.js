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

let parsedRows = [], queueRows = [], lastParsedResults = [], currentLogText = '';

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

function parseNotes(raw) {
  const today = todayStr();
  return raw.trim().split('\n').filter(l => l.trim()).flatMap(line => {
    const cm = line.match(/comment:\s*(.+)$/i);
    const comment = cm ? cm[1].trim() : '';
    let clean = line.replace(/comment:.+$/i, '').trim();
    const dm = clean.match(/\b(\d{1,2}\/\d{1,2}\/\d{4})\b/);
    const date = dm ? dm[1] : today;
    let noDate = clean.replace(/\b\d{1,2}\/\d{1,2}\/\d{4}\b/g, '').trim();

    // Natural language merit badge: "Sam completed First Aid merit badge requirement 3"
    const nlMB = noDate.match(
      /^(.+?)\s+(?:completed|passed|finished|earned|did|got)\s+(.+?)\s+(?:merit\s*badge|MB)\s+req(?:uirement)?s?\s*([\d\w]+(?:[,\s]+[\d\w]+)*)$/i
    );
    if (nlMB) {
      const name  = nlMB[1].trim();
      const badge = nlMB[2].trim();
      const reqs  = nlMB[3].split(/[,\s]+/).map(r => r.replace(/[^\w]/g, '')).filter(Boolean);
      return reqs.map(req => ({ name, type:'mb', badge, item: badge + ' \u2014 Req ' + req, req, date, comment }));
    }

    // Shorthand merit badge: "Sam mb:First Aid req3 req4"
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

    // Natural language full rank: "Tyler completed Eagle rank"
    const nlRank = noDate.match(
      /^(.+?)\s+(?:completed|passed|finished|earned|achieved|got|received)\s+(Scout|Tenderfoot|Second\s+Class|First\s+Class|Star|Life|Eagle)(?:\s+rank)?$/i
    );
    if (nlRank) {
      const name = nlRank[1].trim();
      const rank = nlRank[2].replace(/\s+/g, ' ').trim();
      return [{ name, type:'rank', badge:null, item: rank + ' (Full Rank)', req:null, date, comment }];
    }

    // Natural language rank requirement: "John earned Tenderfoot 1b"
    const nlRankReq = noDate.match(
      /^(.+?)\s+(?:completed|passed|finished|earned|did|got)?\s*(?:requirement\s+)?((?:Tenderfoot|Second\s+Class|First\s+Class|Scout|Star|Life)\s+[\da-z]+)$/i
    );
    if (nlRankReq) {
      const name    = nlRankReq[1].trim();
      const rawItem = nlRankReq[2].replace(/\s+/g, ' ').trim();
      const aliasKey = rawItem.toLowerCase()
        .replace(/\s+/g, '')
        .replace('secondclass', 'sc')
        .replace('firstclass', 'fc')
        .replace('tenderfoot', 'tf');
      const resolved = RANK[aliasKey] || rawItem;
      return [{ name, type:'rank', badge:null, item: resolved, req:null, date, comment }];
    }

    // Shorthand rank tokens: "Tyler tf1a tf1b sc1a"
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

    // Catch-all: couldn't parse
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
    + '<td>' + esc(r.item) + '</td>'
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
    '<tr>'
    + '<td>' + (i+1) + '</td>'
    + '<td>' + esc(r.name) + '</td>'
    + '<td>' + typeBadge(r.type) + '</td>'
    + '<td>' + esc(r.item) + '</td>'
    + '<td>' + esc(r.date) + '</td>'
    + '<td><button class="btn btn-red btn-sm" onclick="removeFromQueue(' + i + ')">&#10005;</button></td>'
    + '</tr>'
  ).join('');
  document.getElementById('sendCard').style.display = 'block';
  document.getElementById('promptBox').textContent = buildPrompt(queueRows);
}

function updateStatus() {
  const total  = parsedRows.length;
  const scouts = new Set(parsedRows.map(r => r.name)).size;
  const mb     = parsedRows.filter(r => r.type === 'mb').length;
  const rank   = parsedRows.filter(r => r.type === 'rank').length;
  const bar    = document.getElementById('statusBar');
  if (!total) { bar.textContent = 'Nothing parsed yet.'; return; }
  bar.innerHTML = '<span class="pill">' + total + ' items</span> &middot; '
    + scouts + ' scouts &middot; '
    + rank + ' rank &middot; '
    + mb + ' MB';
}

function removeFromQueue(i) {
  queueRows.splice(i, 1);
  renderQueue();
}

function doParse() {
  try {
    const notes = document.getElementById('notes').value;
    if (!notes.trim()) { alert('Enter some notes first.'); return; }
    parsedRows = parseNotes(notes);
    queueRows  = [];
    renderPreview();
    renderQueue();
    updateStatus();
    document.getElementById('previewCard').scrollIntoView({ behavior:'smooth', block:'nearest' });
  } catch(e) {
    alert('Parse error: ' + e.message);
    console.error(e);
  }
}

function doAddToQueue() {
  const valid = parsedRows.filter(r => !r.unparsed);
  if (!valid.length) { alert('No valid parsed items to add.'); return; }
  queueRows = [...valid];
  renderQueue();
  document.getElementById('queueCard').scrollIntoView({ behavior:'smooth', block:'nearest' });
}

function clearAll() {
  document.getElementById('notes').value = '';
  parsedRows = []; queueRows = [];
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

function doSend() {
  const p = document.getElementById('promptBox').textContent;
  if (p.startsWith('\u2190')) { alert('Parse notes and add items to queue first.'); return; }
  navigator.clipboard.writeText(p).then(() => {
    const btn = document.getElementById('sendBtn');
    btn.textContent = '\u2705 Prompt copied! Paste into Computer.';
    setTimeout(() => { btn.textContent = '\uD83D\uDCCB Copy Prompt for Computer'; }, 3000);
  }).catch(() => {
    document.getElementById('promptWrap').style.display = 'block';
    alert('Clipboard blocked \u2014 prompt shown below. Copy manually.');
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
});
