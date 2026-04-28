<?php
// Meeting Mode — Trailhead
if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit; }

$pdo->exec("CREATE TABLE IF NOT EXISTS run_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_name VARCHAR(200) DEFAULT NULL,
    raw_notes TEXT,
    prompt TEXT,
    raw_results TEXT,
    summary VARCHAR(200) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_run') {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare('INSERT INTO run_history (user_id, session_name, raw_notes, prompt, raw_results, summary) VALUES (?,?,?,?,?,?)');
    $stmt->execute([
        $_SESSION['user_id'],
        trim($_POST['session_name'] ?? ''),
        $_POST['raw_notes'] ?? '',
        $_POST['prompt'] ?? '',
        $_POST['raw_results'] ?? '',
        $_POST['summary'] ?? '',
    ]);
    echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

$history = $pdo->prepare('SELECT id, session_name, summary, created_at FROM run_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
$history->execute([$_SESSION['user_id']]);
$history = $history->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<meta name="apple-mobile-web-app-capable" content="yes">
<title>Meeting Mode — Trailhead</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
:root{
  --green:#0f766e;--green-dark:#0a5c55;--green-light:#e6f4f3;
  --amber:#d97706;--amber-soft:#fef3c7;
  --red:#b00020;--red-soft:#fee2e2;
  --blue:#1d4ed8;--blue-soft:#dbeafe;
  --purple:#6d28d9;--purple-soft:#ede9fe;
  --gray:#6b7280;--border:#e5e7eb;--bg:#f9fafb;
  --radius:10px;--shadow:0 1px 4px rgba(0,0,0,.08);
}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:var(--bg);color:#111;font-size:15px;line-height:1.5;}
header{background:var(--green);color:#fff;padding:.75rem 1rem;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:100;box-shadow:0 2px 8px rgba(0,0,0,.15);}
header strong{font-size:1.1rem;letter-spacing:-.01em;}
.hdr-right{display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;}
.hdr-right a{color:rgba(255,255,255,.85);text-decoration:none;font-size:.85rem;padding:.2rem .4rem;border-radius:4px;}
.hdr-right a:hover{color:#fff;background:rgba(255,255,255,.15);}
.wrap{max-width:900px;margin:0 auto;padding:.75rem;}
/* tabs */
.tabs{display:flex;gap:.25rem;margin-bottom:.75rem;background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:.3rem;box-shadow:var(--shadow);}
.tab{flex:1;padding:.55rem .4rem;border:0;background:none;border-radius:8px;font-size:.82rem;font-weight:700;color:var(--gray);cursor:pointer;transition:.15s;line-height:1.2;}
.tab.active{background:var(--green);color:#fff;}
.tab:hover:not(.active){background:var(--green-light);color:var(--green);}
.pane{display:none;}.pane.active{display:block;}
/* cards */
.card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:.9rem;margin-bottom:.75rem;box-shadow:var(--shadow);}
.card h2{font-size:.95rem;font-weight:700;color:var(--green);margin-bottom:.6rem;display:flex;align-items:center;gap:.4rem;}
.card h2 .step{background:var(--green);color:#fff;width:20px;height:20px;border-radius:50%;font-size:.7rem;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;}
/* inputs */
label{display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.3rem;}
textarea,input[type=text],input[type=date]{width:100%;padding:.65rem .75rem;border:1px solid var(--border);border-radius:8px;font-size:.95rem;font-family:inherit;resize:vertical;background:#fff;}
textarea:focus,input:focus{outline:2px solid var(--green);border-color:var(--green);}
#notes{min-height:110px;font-size:1rem;}
/* buttons */
.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.6rem 1rem;border:0;border-radius:8px;font-size:.9rem;font-weight:700;cursor:pointer;transition:.15s;text-decoration:none;min-height:44px;}
.btn-primary{background:var(--green);color:#fff;}
.btn-primary:hover{background:var(--green-dark);}
.btn-send{background:#0ea5e9;color:#fff;font-size:1rem;padding:.8rem 1.25rem;width:100%;justify-content:center;}
.btn-send:hover{background:#0284c7;}
.btn-ghost{background:#fff;border:1.5px solid var(--border);color:#374151;}
.btn-ghost:hover{border-color:var(--green);color:var(--green);}
.btn-sm{padding:.4rem .75rem;font-size:.8rem;min-height:36px;}
.btn-red{background:var(--red);color:#fff;}
.btn-red:hover{background:#8a001a;}
.btn-amber{background:var(--amber);color:#fff;}
.btn-amber:hover{background:#b45309;}
.btn-row{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.6rem;}
/* voice */
#micBtn{background:#fff;border:2px solid var(--border);color:var(--gray);border-radius:8px;padding:.6rem .9rem;font-size:.9rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;min-height:44px;}
#micBtn.recording{border-color:var(--red);color:var(--red);animation:pulse 1s infinite;}
@keyframes pulse{0%,100%{opacity:1;}50%{opacity:.5;}}
/* meta row */
.meta-row{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-bottom:.6rem;}
/* table */
.tbl-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;}
table{width:100%;border-collapse:collapse;font-size:.83rem;}
th{text-align:left;padding:.45rem .55rem;background:#f3f4f6;border-bottom:2px solid var(--border);font-size:.75rem;white-space:nowrap;}
td{padding:.45rem .55rem;border-bottom:1px solid #f3f4f6;vertical-align:middle;}
tr:last-child td{border-bottom:0;}
/* badges */
.badge{display:inline-flex;align-items:center;padding:.15rem .5rem;border-radius:999px;font-size:.7rem;font-weight:700;white-space:nowrap;}
.b-rank{background:var(--blue-soft);color:var(--blue);}
.b-mb{background:var(--purple-soft);color:var(--purple);}
.b-high{background:#d1fae5;color:#065f46;}
.b-medium{background:#e0f2fe;color:#0369a1;}
.b-low{background:var(--amber-soft);color:var(--amber);}
.b-success{background:#d1fae5;color:#065f46;}
.b-failed{background:var(--red-soft);color:var(--red);}
.b-review{background:var(--amber-soft);color:var(--amber);}
/* status bar */
.status-bar{background:var(--green-light);border:1px solid #a7d9d5;border-radius:8px;padding:.55rem .8rem;font-size:.83rem;color:var(--green-dark);display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;margin-bottom:.6rem;}
.status-bar .pill{font-weight:700;}
/* prompt box */
.prompt-box{background:#1e1e2e;color:#cdd6f4;border-radius:8px;padding:.8rem;font-family:monospace;font-size:.75rem;white-space:pre-wrap;word-break:break-word;max-height:260px;overflow-y:auto;margin-top:.6rem;}
/* result summary */
.result-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;margin:.6rem 0;}
.result-card{text-align:center;padding:.7rem .4rem;border-radius:8px;}
.result-card .num{font-size:1.7rem;font-weight:800;}
.result-card .lbl{font-size:.72rem;font-weight:600;}
.rc-success{background:#d1fae5;color:#065f46;}
.rc-failed{background:var(--red-soft);color:var(--red);}
.rc-review{background:var(--amber-soft);color:var(--amber);}
/* history */
.hist-item{display:flex;justify-content:space-between;align-items:flex-start;padding:.6rem 0;border-bottom:1px solid var(--border);gap:.5rem;}
.hist-item:last-child{border-bottom:0;}
.hist-meta{font-size:.78rem;color:var(--gray);}
/* chips */
.chips{display:flex;flex-wrap:wrap;gap:.3rem;margin-bottom:.5rem;}
.chip{background:#f3f4f6;border:1px solid var(--border);border-radius:6px;padding:.2rem .55rem;font-size:.73rem;font-family:monospace;color:#374151;cursor:pointer;}
.chip:hover{background:var(--green-light);border-color:var(--green);color:var(--green);}
/* empty */
.empty{text-align:center;padding:1.25rem;color:var(--gray);font-size:.88rem;}
/* note box */
.note-box{background:var(--amber-soft);border:1px solid #fcd34d;border-radius:8px;padding:.55rem .8rem;font-size:.8rem;color:#78350f;margin-bottom:.6rem;}
/* log */
.log-pre{font-size:.75rem;white-space:pre-wrap;word-break:break-word;max-height:180px;overflow-y:auto;background:#f9fafb;padding:.7rem;border-radius:8px;border:1px solid var(--border);}
@media(max-width:520px){
  .meta-row{grid-template-columns:1fr;}
  .tabs .tab{font-size:.75rem;padding:.5rem .25rem;}
  .hdr-right a:not(:last-child){display:none;}
}
</style>
</head>
<body>
<header>
  <strong>&#9978; Trailhead</strong>
  <div class="hdr-right">
    <a href="/">Dashboard</a>
    <a href="/sessions">Sessions</a>
    <a href="/scouts">Scouts</a>
    <span style="font-size:.8rem;opacity:.75">&#128100; <?= htmlspecialchars($_SESSION['display_name'] ?? '') ?></span>
    <a href="/logout">Logout</a>
  </div>
</header>

<div class="wrap">
  <div class="tabs">
    <button class="tab active" onclick="showTab('meeting',this)">&#128203; Meeting</button>
    <button class="tab" onclick="showTab('results',this)">&#128229; Results</button>
    <button class="tab" onclick="showTab('history',this)">&#128336; History</button>
  </div>

  <!-- TAB 1: MEETING -->
  <div id="tab-meeting" class="pane active">

    <div class="card">
      <h2><span class="step">1</span> Capture Notes</h2>
      <div class="meta-row">
        <div>
          <label>Session name (optional)</label>
          <input type="text" id="sessionName" placeholder="e.g. April Court of Honor">
        </div>
        <div>
          <label>Default date</label>
          <input type="date" id="defaultDate">
        </div>
      </div>
      <label>Notes &mdash; type naturally or use shorthand</label>
      <div class="chips">
        <span class="chip" onclick="insertChip('Sam completed First Aid merit badge requirement 3')">Sam MB req</span>
        <span class="chip" onclick="insertChip('John earned Tenderfoot 1b')">John rank req</span>
        <span class="chip" onclick="insertChip('Chris passed Cooking merit badge requirement 2')">Chris MB</span>
        <span class="chip" onclick="insertChip('Tyler completed Eagle rank')">Full rank</span>
      </div>
      <textarea id="notes" placeholder="Type or speak:
Sam completed First Aid merit badge requirement 3
John earned Tenderfoot 1b
Chris passed Cooking MB req 2 on 4/28/2026
Tyler tf1a tf1b sc1a"></textarea>
      <div class="btn-row">
        <button id="micBtn" onclick="toggleMic()">&#127908; Dictate</button>
        <button class="btn btn-primary" onclick="doParse()">&#9889; Parse</button>
        <button class="btn btn-ghost btn-sm" onclick="clearAll()">Clear</button>
      </div>
    </div>

    <div class="card" id="previewCard" style="display:none">
      <h2><span class="step">2</span> Parsed Preview</h2>
      <div class="status-bar" id="statusBar">Parsing&hellip;</div>
      <div class="tbl-wrap">
        <table>
          <thead><tr><th>Scout</th><th>Type</th><th>Item</th><th>Date</th><th>Name flag</th></tr></thead>
          <tbody id="previewBody"></tbody>
        </table>
      </div>
      <div class="btn-row">
        <button class="btn btn-primary" onclick="doAddToQueue()">&#9989; Add All to Queue</button>
      </div>
    </div>

    <div class="card" id="queueCard" style="display:none">
      <h2><span class="step">3</span> Queue <span id="queueCount" style="font-size:.8rem;font-weight:400;color:var(--gray)"></span></h2>
      <div class="tbl-wrap">
        <table>
          <thead><tr><th>#</th><th>Scout</th><th>Type</th><th>Item</th><th>Date</th><th>Flag</th><th></th></tr></thead>
          <tbody id="queueBody"></tbody>
        </table>
      </div>
    </div>

    <div class="card" id="sendCard" style="display:none">
      <h2><span class="step">4</span> Send to Computer</h2>
      <div class="note-box">&#9888;&#65039; LastPass will auto-fill credentials. If a CAPTCHA image challenge appears, complete it manually before continuing.</div>
      <button class="btn btn-send" id="sendBtn" onclick="doSend()">&#128640; Open Scoutbook + Copy Prompt</button>
      <div class="btn-row">
        <button class="btn btn-ghost btn-sm" id="copyOnlyBtn" onclick="doCopyOnly()">&#128203; Copy prompt only</button>
        <button class="btn btn-ghost btn-sm" onclick="togglePrompt()">&#128065; View prompt</button>
      </div>
      <div id="promptWrap" style="display:none">
        <pre class="prompt-box" id="promptBox"></pre>
      </div>
    </div>

  </div><!-- /tab-meeting -->

  <!-- TAB 2: RESULTS -->
  <div id="tab-results" class="pane">
    <div class="card">
      <h2><span class="step">5</span> Paste Computer Results</h2>
      <label>Paste the full output from Computer here</label>
      <textarea id="resultPaste" style="min-height:110px;font-family:monospace;font-size:.83rem" placeholder="SUCCESS&#10;Scout: Sam | Item: First Aid &mdash; Req 3 | Date: 4/28/2026 | Result: Success&#10;&#10;NEEDS REVIEW&#10;Scout: Dan | Item: Tenderfoot 1b | Date: 4/28/2026 | Result: Needs review | Reason: Multiple scouts matched"></textarea>
      <div class="btn-row">
        <button class="btn btn-primary" onclick="doParseResults()">&#128202; Parse Results</button>
        <button class="btn btn-ghost btn-sm" onclick="clearResults()">Clear</button>
      </div>
    </div>

    <div id="resultSummary"></div>

    <div id="reviewSection" class="card" style="display:none">
      <h2><span class="step">6</span> Review Follow-up</h2>
      <p style="font-size:.82rem;color:var(--gray);margin-bottom:.6rem">Builds a second-pass prompt for items marked Needs Review.</p>
      <button class="btn btn-amber" onclick="doBuildReview()">&#128260; Build Review Prompt</button>
      <div id="reviewPromptWrap" style="display:none;margin-top:.6rem">
        <pre class="prompt-box" id="reviewPromptBox"></pre>
        <div class="btn-row">
          <button class="btn btn-send" style="flex:1" onclick="doSendReview()">&#128640; Open Scoutbook + Copy Review Prompt</button>
          <button class="btn btn-ghost btn-sm" onclick="doCopyReview()">&#128203; Copy only</button>
        </div>
      </div>
    </div>

    <div id="logSection" class="card" style="display:none">
      <h2>&#128221; Session Log</h2>
      <pre class="log-pre" id="logBox"></pre>
      <div class="btn-row">
        <button class="btn btn-ghost btn-sm" onclick="doExportLog()">&#11015;&#65039; Export .txt</button>
        <button class="btn btn-ghost btn-sm" onclick="doSaveRun()">&#128190; Save to History</button>
      </div>
    </div>
  </div><!-- /tab-results -->

  <!-- TAB 3: HISTORY -->
  <div id="tab-history" class="pane">
    <div class="card">
      <h2>&#128336; Run History</h2>
      <?php if (empty($history)): ?>
        <div class="empty">No runs saved yet.<br>After parsing results, tap &ldquo;Save to History&rdquo; on the Results tab.</div>
      <?php else: ?>
        <?php foreach ($history as $h): ?>
        <div class="hist-item">
          <div>
            <div style="font-weight:600;font-size:.9rem"><?= htmlspecialchars($h['session_name'] ?: 'Unnamed session') ?></div>
            <div class="hist-meta"><?= htmlspecialchars($h['summary'] ?? '') ?></div>
          </div>
          <div class="hist-meta" style="white-space:nowrap"><?= date('M j, g:ia', strtotime($h['created_at'])) ?></div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div><!-- /tab-history -->

</div><!-- /wrap -->

<script>
const RANK={
  tf1a:'Tenderfoot 1a',tf1b:'Tenderfoot 1b',tf1c:'Tenderfoot 1c',tf1d:'Tenderfoot 1d',tf1e:'Tenderfoot 1e',
  tf2a:'Tenderfoot 2a',tf2b:'Tenderfoot 2b',tf2c:'Tenderfoot 2c',
  tf3a:'Tenderfoot 3a',tf3b:'Tenderfoot 3b',tf3c:'Tenderfoot 3c',tf3d:'Tenderfoot 3d',
  tf4a:'Tenderfoot 4a',tf4b:'Tenderfoot 4b',
  sc1a:'Second Class 1a',sc1b:'Second Class 1b',sc1c:'Second Class 1c',
  sc2a:'Second Class 2a',sc2b:'Second Class 2b',sc2c:'Second Class 2c',
  sc3a:'Second Class 3a',sc3b:'Second Class 3b',sc3c:'Second Class 3c',
  fc1a:'First Class 1a',fc1b:'First Class 1b',fc1c:'First Class 1c',
  fc2a:'First Class 2a',fc2b:'First Class 2b',
  scout1a:'Scout 1a',scout1b:'Scout 1b',scout1c:'Scout 1c',scout1d:'Scout 1d',scout1e:'Scout 1e',scout1f:'Scout 1f',
  star1:'Star 1',star2:'Star 2',life1:'Life 1',life2:'Life 2',
};
const COMMON=new Set(['sam','dan','ben','max','tom','tim','jim','bob','joe','mike','chris','alex','jake','ryan','kyle','adam','matt','john','mark','luke','evan','noah','liam','owen','cole','drew','seth','zach','will','jack','tyler','james','brandon','cody','hunter','austin','logan','ethan','mason','carter']);

let parsedRows=[],queueRows=[],lastParsedResults=[],currentLogText='';

function todayStr(){
  const d=document.getElementById('defaultDate').value;
  if(d){const[y,m,day]=d.split('-');return`${parseInt(m)}/${parseInt(day)}/${y}`;}
  const n=new Date();return`${n.getMonth()+1}/${n.getDate()}/${n.getFullYear()}`;
}
function nameConf(n){
  const w=n.trim().split(/\s+/);
  if(w.length>=2)return'high';
  return COMMON.has(w[0].toLowerCase())?'low':'medium';
}
function typeBadge(t){return t==='mb'?'<span class="badge b-mb">Merit Badge</span>':'<span class="badge b-rank">Rank</span>';}
function confBadge(c){
  if(c==='high')return'<span class="badge b-high">&#10003; Full name</span>';
  if(c==='medium')return'<span class="badge b-medium">Single &middot; OK</span>';
  return'<span class="badge b-low">&#9888; Verify</span>';
}
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

function parseNotes(raw){
  const today=todayStr();
  return raw.trim().split('\n').filter(l=>l.trim()).flatMap(line=>{
    const cm=line.match(/comment:\s*(.+)$/i);
    const comment=cm?cm[1].trim():'';
    let clean=line.replace(/comment:.+$/i,'').trim();
    const dm=clean.match(/\b(\d{1,2}\/\d{1,2}\/\d{4})\b/);
    const date=dm?dm[1]:today;
    let noDate=clean.replace(/\b\d{1,2}\/\d{1,2}\/\d{4}\b/g,'').trim();

    // Natural language merit badge
    const nlMB=noDate.match(/^(.+?)\s+(?:completed|passed|finished|earned|did|got)\s+(.+?)\s+(?:merit\s*badge|MB)\s+req(?:uirement)?s?\s*([\d\w]+(?:[,\s]+[\d\w]+)*)$/i);
    if(nlMB){
      const name=nlMB[1].trim();
      const badge=nlMB[2].trim();
      const reqs=nlMB[3].split(/[,\s]+/).map(r=>r.replace(/[^\w]/g,'')).filter(Boolean);
      const conf=nameConf(name);
      return reqs.map(req=>({name,type:'mb',badge,item:`${badge} \u2014 Req ${req}`,req,date,comment,conf}));
    }

    // Shorthand merit badge: mb:Badge req1 req2
    const shMB=noDate.match(/^(.+?)\s+mb:(.+?)\s+((?:req[\d\w]+\s*)+)$/i);
    if(shMB){
      const name=shMB[1].trim();
      const badge=shMB[2].trim();
      const reqs=shMB[3].trim().split(/\s+/).filter(Boolean);
      const conf=nameConf(name);
      return reqs.map(req=>({name,type:'mb',badge,item:`${badge} \u2014 ${req.replace(/req/i,'Req ')}`,req:req.replace(/req/i,'').trim(),date,comment,conf}));
    }

    // Natural language full rank
    const nlRank=noDate.match(/^(.+?)\s+(?:completed|passed|finished|earned|achieved|got|received)\s+(Scout|Tenderfoot|Second\s+Class|First\s+Class|Star|Life|Eagle)(?:\s+rank)?$/i);
    if(nlRank){
      const name=nlRank[1].trim();
      const rank=nlRank[2].replace(/\s+/g,' ').trim();
      const conf=nameConf(name);
      return[{name,type:'rank',badge:null,item:`${rank} (Full Rank)`,req:null,date,comment,conf}];
    }

    // Natural language rank requirement
    const nlRankReq=noDate.match(/^(.+?)\s+(?:completed|passed|finished|earned|did|got)?\s*(?:requirement\s+)?((?:Tenderfoot|Second\s+Class|First\s+Class|Scout|Star|Life)\s+[\da-z]+)$/i);
    if(nlRankReq){
      const name=nlRankReq[1].trim();
      const rawItem=nlRankReq[2].replace(/\s+/g,' ').trim();
      const conf=nameConf(name);
      const aliasKey=rawItem.toLowerCase().replace(/\s+/g,'').replace('secondclass','sc').replace('firstclass','fc').replace('tenderfoot','tf');
      const resolved=RANK[aliasKey]||rawItem;
      return[{name,type:'rank',badge:null,item:resolved,req:null,date,comment,conf}];
    }

    // Shorthand rank tokens
    const tokens=noDate.split(/\s+/).filter(Boolean);
    const nameT=[],itemT=[];
    tokens.forEach(t=>{if(RANK[t.toLowerCase()])itemT.push(t.toLowerCase());else nameT.push(t);});
    const name=nameT.join(' ').trim();
    if(!name||!itemT.length)return[];
    const conf=nameConf(name);
    return itemT.map(item=>({name,type:'rank',badge:null,item:RANK[item],req:null,date,comment,conf}));
  });
}

function renderPreview(){
  const b=document.getElementById('previewBody');
  if(!parsedRows.length){b.innerHTML='<tr><td colspan="5" class="empty">No items parsed.</td></tr>';return;}
  b.innerHTML=parsedRows.map(r=>`<tr>
    <td>${esc(r.name)}</td><td>${typeBadge(r.type)}</td><td>${esc(r.item)}</td><td>${esc(r.date)}</td><td>${confBadge(r.conf)}</td>
  </tr>`).join('');
  document.getElementById('previewCard').style.display='block';
}

function renderQueue(){
  const b=document.getElementById('queueBody');
  if(!queueRows.length){document.getElementById('queueCard').style.display='none';return;}
  document.getElementById('queueCard').style.display='block';
  document.getElementById('queueCount').textContent=`(${queueRows.length} items)`;
  b.innerHTML=queueRows.map((r,i)=>`<tr>
    <td>${i+1}</td><td>${esc(r.name)}</td><td>${typeBadge(r.type)}</td><td>${esc(r.item)}</td><td>${esc(r.date)}</td><td>${confBadge(r.conf)}</td>
    <td><button class="btn btn-red btn-sm" onclick="removeFromQueue(${i})">&#10005;</button></td>
  </tr>`).join('');
  document.getElementById('sendCard').style.display='block';
  document.getElementById('promptBox').textContent=buildPrompt(queueRows);
}

function updateStatus(){
  const total=parsedRows.length,flagged=parsedRows.filter(r=>r.conf==='low').length;
  const scouts=new Set(parsedRows.map(r=>r.name)).size;
  const mb=parsedRows.filter(r=>r.type==='mb').length;
  const rank=parsedRows.filter(r=>r.type==='rank').length;
  const bar=document.getElementById('statusBar');
  if(!total){bar.textContent='Nothing parsed yet.';return;}
  bar.innerHTML=`<span class="pill">${total} items</span> &middot; ${scouts} scouts &middot; ${rank} rank &middot; ${mb} MB`+(flagged?` &middot; <span style="color:var(--amber);font-weight:700">&#9888; ${flagged} name(s) need verification</span>`:'');
}

function removeFromQueue(i){
  queueRows.splice(i,1);
  renderQueue();
}

function doParse(){
  parsedRows=parseNotes(document.getElementById('notes').value);
  queueRows=[];
  renderPreview();
  renderQueue();
  updateStatus();
  if(parsedRows.length)document.getElementById('previewCard').scrollIntoView({behavior:'smooth',block:'nearest'});
}

function doAddToQueue(){
  queueRows=[...parsedRows];
  renderQueue();
  document.getElementById('queueCard').scrollIntoView({behavior:'smooth',block:'nearest'});
}

function clearAll(){
  document.getElementById('notes').value='';
  parsedRows=[];queueRows=[];
  document.getElementById('previewCard').style.display='none';
  document.getElementById('queueCard').style.display='none';
  document.getElementById('sendCard').style.display='none';
  updateStatus();
}

function insertChip(txt){
  const ta=document.getElementById('notes');
  const v=ta.value;
  ta.value=v+(v&&!v.endsWith('\n')?'\n':'')+txt+'\n';
  ta.focus();
}

function buildAction(r,i){
  const vNote=r.conf==='low'?'\n     \u26a0 Short name \u2014 if search returns multiple results, STOP this item and report Needs review: Multiple scouts matched.':'';
  if(r.type==='mb'){
    let s=`  ${i+1}. Scout: ${r.name}${vNote}\n     Type: Merit Badge requirement\n     Badge: ${r.badge}\n     Requirement: ${r.req}\n     Date: ${r.date}\n     Navigation: Find scout \u2192 scroll to Merit Badges \u2192 View All \u2192 search \"${r.badge}\" \u2192 open badge \u2192 find Req ${r.req} \u2192 approve`;
    if(r.comment)s+=`\n     Comment: ${r.comment}`;
    return s;
  }
  let s=`  ${i+1}. Scout: ${r.name}${vNote}\n     Type: Rank requirement\n     Item: ${r.item}\n     Date: ${r.date}\n     Navigation: Find scout \u2192 open rank card \u2192 View More \u2192 find ${r.item} \u2192 approve`;
  if(r.comment)s+=`\n     Comment: ${r.comment}`;
  return s;
}

function buildPrompt(rows){
  if(!rows.length)return'\u2190 Parse your notes and add items to the queue first.';
  const actions=rows.map((r,i)=>buildAction(r,i)).join('\n\n');
  return `Use Scoutbook Plus to enter rank advancements and merit badge requirements.\n\nACCOUNT: chrispowell6203\nUNIT CONTEXT: Scouts BSA Troop 911 Boys \u2014 Position: Scoutmaster\nSITE: https://advancements.scouting.org\n\nLOGIN STEPS (do these first):\n- Navigate to https://advancements.scouting.org\n- Wait for the page to fully load.\n- LastPass will auto-fill username and password \u2014 confirm both fields are populated.\n- Click the \"I'm not a robot\" reCAPTCHA v2 checkbox and wait for the green checkmark.\n- If an image challenge appears instead, STOP and notify me \u2014 I will complete it manually.\n- Click the Login button and wait for the dashboard to load.\n- Dismiss any startup popup.\n- Verify account is chrispowell6203 and context is Troop 911 Scoutmaster before proceeding.\n\nGLOBAL RULES:\n- Use the search field to find each scout by name.\n- Process all items independently; continue the full queue even if one item fails.\n- Confirm the requirement is NOT already Approved before making any change.\n- If already approved: report Needs review \u2014 Already approved. Do not re-enter.\n- If the scout cannot be found confidently, multiple scouts match, the requirement cannot be found,\n  the merit badge cannot be matched exactly, or the page state is unexpected:\n  do not guess \u2014 add to FAILED or NEEDS REVIEW with reason, then continue.\n- Mark items Approved, set the date exactly as given, add comment if listed.\n- Verify the requirement shows Approved with the correct date after saving.\n- Only stop the entire run for a blocking login/session failure.\n\nNAVIGATION PATHS:\n- Rank requirements: search scout \u2192 open rank card \u2192 View More \u2192 find requirement \u2192 approve\n- Merit badge requirements: search scout \u2192 scroll to Merit Badges \u2192 View All \u2192\n  search badge name \u2192 open badge \u2192 find requirement \u2192 approve\n  (Use \"View All\" \u2014 do NOT rely on pending/started status)\n\nACTIONS (process in order):\n\n${actions}\n\nREQUIRED OUTPUT FORMAT (after all actions are complete):\n\nSUCCESS\nScout: [name] | Item: [item] | Date: [date] | Result: Success\n\nFAILED\nScout: [name] | Item: [item] | Date: [date] | Result: Failed | Reason: [brief reason]\n\nNEEDS REVIEW\nScout: [name] | Item: [item] | Date: [date] | Result: Needs review | Reason: [Already approved / Multiple scouts matched / Requirement not found / Merit badge not found / Unexpected page state / CAPTCHA image challenge]\n\nThen: \"Done. X succeeded, Y failed, Z need review.\"`;
}

function togglePrompt(){
  const w=document.getElementById('promptWrap');
  w.style.display=w.style.display==='none'?'block':'none';
}

function doSend(){
  const p=document.getElementById('promptBox').textContent;
  if(p.startsWith('\u2190')){alert('Parse notes and add items to queue first.');return;}
  navigator.clipboard.writeText(p).then(()=>{
    window.open('https://advancements.scouting.org','_blank');
    const btn=document.getElementById('sendBtn');
    btn.textContent='\u2705 Prompt copied! Scoutbook opening\u2026';
    setTimeout(()=>{btn.textContent='\uD83D\uDE80 Open Scoutbook + Copy Prompt';},3000);
  }).catch(()=>{
    document.getElementById('promptWrap').style.display='block';
    alert('Clipboard blocked \u2014 prompt shown below. Copy manually.');
  });
}

function doCopyOnly(){
  navigator.clipboard.writeText(document.getElementById('promptBox').textContent).then(()=>{
    const btn=document.getElementById('copyOnlyBtn');
    btn.textContent='\u2705 Copied!';
    setTimeout(()=>{btn.textContent='\uD83D\uDCCB Copy prompt only';},2000);
  });
}

function parseResults(raw){
  let section='';
  return raw.trim().split('\n').reduce((acc,line)=>{
    const t=line.trim();
    if(!t)return acc;
    if(/^SUCCESS$/i.test(t)){section='SUCCESS';return acc;}
    if(/^FAILED$/i.test(t)){section='FAILED';return acc;}
    if(/^NEEDS REVIEW$/i.test(t)){section='NEEDS REVIEW';return acc;}
    const m=t.match(/Scout:\s*(.+?)\s*\|\s*Item:\s*(.+?)\s*\|\s*Date:\s*(.+?)\s*\|\s*Result:\s*(Success|Failed|Needs review)(?:\s*\|\s*Reason:\s*(.+))?/i);
    if(m)acc.push({name:m[1].trim(),item:m[2].trim(),date:m[3].trim(),result:m[4].trim(),reason:(m[5]||'').trim(),section});
    return acc;
  },[]);
}

function doParseResults(){
  const raw=document.getElementById('resultPaste').value.trim();
  if(!raw){alert('Paste Computer results first.');return;}
  lastParsedResults=parseResults(raw);
  renderResultSummary(lastParsedResults);
  const hasReview=lastParsedResults.some(r=>r.result.toLowerCase()==='needs review');
  document.getElementById('reviewSection').style.display=hasReview?'block':'none';
  buildLogText(raw,lastParsedResults);
}

function renderResultSummary(results){
  const success=results.filter(r=>r.result.toLowerCase()==='success');
  const failed=results.filter(r=>r.result.toLowerCase()==='failed');
  const review=results.filter(r=>r.result.toLowerCase()==='needs review');
  let html=`<div class="card"><h2>&#128202; Results Summary</h2><div class="result-grid">
    <div class="result-card rc-success"><div class="num">${success.length}</div><div class="lbl">Succeeded</div></div>
    <div class="result-card rc-failed"><div class="num">${failed.length}</div><div class="lbl">Failed</div></div>
    <div class="result-card rc-review"><div class="num">${review.length}</div><div class="lbl">Needs Review</div></div>
  </div>`;
  if(results.length){
    html+=`<div class="tbl-wrap"><table><thead><tr><th>Scout</th><th>Item</th><th>Date</th><th>Result</th><th>Reason</th></tr></thead><tbody>`;
    results.forEach(r=>{
      const cls=r.result.toLowerCase()==='success'?'b-success':r.result.toLowerCase()==='failed'?'b-failed':'b-review';
      html+=`<tr><td>${esc(r.name)}</td><td>${esc(r.item)}</td><td>${esc(r.date)}</td><td><span class="badge ${cls}">${esc(r.result)}</span></td><td>${esc(r.reason||'\u2014')}</td></tr>`;
    });
    html+=`</tbody></table></div>`;
  }
  html+=`</div>`;
  document.getElementById('resultSummary').innerHTML=html;
}

function buildLogText(raw,results){
  const s=results.filter(r=>r.result.toLowerCase()==='success').length;
  const f=results.filter(r=>r.result.toLowerCase()==='failed').length;
  const rv=results.filter(r=>r.result.toLowerCase()==='needs review').length;
  const session=document.getElementById('sessionName').value||'Unnamed session';
  const d=new Date();
  currentLogText=`SESSION: ${session}\nDATE: ${d.toLocaleDateString()} ${d.toLocaleTimeString()}\nSUMMARY: ${s} succeeded \u00b7 ${f} failed \u00b7 ${rv} needs review\n\n${raw}`;
  document.getElementById('logBox').textContent=currentLogText;
  document.getElementById('logSection').style.display='block';
}

function doExportLog(){
  const a=document.createElement('a');
  a.href='data:text/plain;charset=utf-8,'+encodeURIComponent(currentLogText);
  a.download='trailhead-log-'+Date.now()+'.txt';
  a.click();
}

function doSaveRun(){
  const results=lastParsedResults;
  const s=results.filter(r=>r.result.toLowerCase()==='success').length;
  const f=results.filter(r=>r.result.toLowerCase()==='failed').length;
  const rv=results.filter(r=>r.result.toLowerCase()==='needs review').length;
  const fd=new FormData();
  fd.append('action','save_run');
  fd.append('session_name',document.getElementById('sessionName').value||'');
  fd.append('raw_notes',document.getElementById('notes').value||'');
  fd.append('prompt',document.getElementById('promptBox').textContent||'');
  fd.append('raw_results',document.getElementById('resultPaste').value||'');
  fd.append('summary',`${s} succeeded \u00b7 ${f} failed \u00b7 ${rv} needs review`);
  fetch(window.location.href,{method:'POST',body:fd})
    .then(r=>r.json())
    .then(d=>{
      if(d.ok){alert('Run saved!');showTab('history',document.querySelectorAll('.tab')[2]);}
    }).catch(()=>alert('Save failed \u2014 check connection.'));
}

function buildReviewPrompt(results){
  const items=results.filter(r=>r.result.toLowerCase()==='needs review');
  if(!items.length)return'\u2190 No NEEDS REVIEW items found.';
  const lines=items.map((r,i)=>`  ${i+1}. Scout: ${r.name}\n     Item: ${r.item}\n     Date: ${r.date}\n     Prior reason: ${r.reason||'Needs review'}\n     Instruction: Re-attempt after my clarification. If still unclear, report Needs review again.`).join('\n\n');
  return `Use Scoutbook Plus to resolve only items previously returned as NEEDS REVIEW.\n\nACCOUNT: chrispowell6203\nUNIT CONTEXT: Scouts BSA Troop 911 Boys \u2014 Position: Scoutmaster\nSITE: https://advancements.scouting.org\n\nLOGIN STEPS: Same as primary run \u2014 LastPass auto-fills, handle CAPTCHA manually if needed.\n\nGLOBAL RULES:\n- Work ONLY the items listed below.\n- Re-attempt only after applying my guidance or decision.\n- If already approved, report Needs review: Already approved.\n- If scout/requirement still cannot be found confidently, report Needs review with reason.\n\nITEMS TO RESOLVE:\n\n${lines}\n\nREQUIRED OUTPUT FORMAT:\n\nSUCCESS\nScout: [name] | Item: [item] | Date: [date] | Result: Success\n\nFAILED\nScout: [name] | Item: [item] | Date: [date] | Result: Failed | Reason: [brief reason]\n\nNEEDS REVIEW\nScout: [name] | Item: [item] | Date: [date] | Result: Needs review | Reason: [brief reason]\n\nThen: \"Done. X succeeded, Y failed, Z need review.\"`;
}

function doBuildReview(){
  const p=buildReviewPrompt(lastParsedResults);
  document.getElementById('reviewPromptBox').textContent=p;
  document.getElementById('reviewPromptWrap').style.display='block';
}

function doSendReview(){
  const p=document.getElementById('reviewPromptBox').textContent;
  if(p.startsWith('\u2190')){alert('Parse results first.');return;}
  navigator.clipboard.writeText(p).then(()=>{
    window.open('https://advancements.scouting.org','_blank');
    const btn=document.querySelector('#reviewSection .btn-send');
    btn.textContent='\u2705 Copied! Scoutbook opening\u2026';
    setTimeout(()=>{btn.textContent='\uD83D\uDE80 Open Scoutbook + Copy Review Prompt';},3000);
  });
}

function doCopyReview(){
  navigator.clipboard.writeText(document.getElementById('reviewPromptBox').textContent).then(()=>{
    const btn=document.querySelector('#reviewSection .btn-ghost');
    btn.textContent='\u2705 Copied!';
    setTimeout(()=>{btn.textContent='\uD83D\uDCCB Copy only';},2000);
  });
}

function clearResults(){
  document.getElementById('resultPaste').value='';
  document.getElementById('resultSummary').innerHTML='';
  document.getElementById('reviewSection').style.display='none';
  document.getElementById('logSection').style.display='none';
}

let recognition=null,isRecording=false;
function toggleMic(){
  const SR=window.SpeechRecognition||window.webkitSpeechRecognition;
  if(!SR){alert('Voice dictation not supported in this browser. Try Chrome or Safari.');return;}
  if(isRecording){recognition.stop();return;}
  recognition=new SR();
  recognition.continuous=true;
  recognition.interimResults=false;
  recognition.lang='en-US';
  recognition.onresult=e=>{
    const t=Array.from(e.results).slice(e.resultIndex).map(r=>r[0].transcript).join(' ');
    const ta=document.getElementById('notes');
    const v=ta.value;
    ta.value=v+(v&&!v.endsWith('\n')?'\n':'')+t.trim()+'\n';
  };
  recognition.onend=()=>{isRecording=false;document.getElementById('micBtn').classList.remove('recording');document.getElementById('micBtn').textContent='\uD83C\uDFA4 Dictate';};
  recognition.onerror=()=>{isRecording=false;document.getElementById('micBtn').classList.remove('recording');document.getElementById('micBtn').textContent='\uD83C\uDFA4 Dictate';};
  recognition.start();
  isRecording=true;
  document.getElementById('micBtn').classList.add('recording');
  document.getElementById('micBtn').textContent='\uD83D\uDD34 Stop';
}

function showTab(name,btn){
  document.querySelectorAll('.pane').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
  document.getElementById('tab-'+name).classList.add('active');
  btn.classList.add('active');
}

(function(){
  const d=new Date();
  document.getElementById('defaultDate').value=d.toISOString().split('T')[0];
})();
</script>
</body>
</html>
