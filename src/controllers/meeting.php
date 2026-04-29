<?php
// Meeting Mode — Trailhead
date_default_timezone_set('America/Chicago');

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

$pdo->exec("CREATE TABLE IF NOT EXISTS queue_state (
    user_id INT NOT NULL PRIMARY KEY,
    queue_json MEDIUMTEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_run') {
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
    // Auto-clear the queue after saving
    $pdo->prepare('INSERT INTO queue_state (user_id, queue_json) VALUES (?, ?) ON DUPLICATE KEY UPDATE queue_json = VALUES(queue_json), updated_at = CURRENT_TIMESTAMP')
        ->execute([$_SESSION['user_id'], '[]']);
    echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_queue') {
    header('Content-Type: application/json');
    $json = $_POST['queue_json'] ?? '[]';
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) { echo json_encode(['ok' => false, 'error' => 'invalid json']); exit; }
    $pdo->prepare('INSERT INTO queue_state (user_id, queue_json) VALUES (?, ?) ON DUPLICATE KEY UPDATE queue_json = VALUES(queue_json), updated_at = CURRENT_TIMESTAMP')
        ->execute([$_SESSION['user_id'], $json]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'load_queue') {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare('SELECT queue_json FROM queue_state WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    echo json_encode(['ok' => true, 'queue' => $row ? json_decode($row['queue_json'], true) : []]);
    exit;
}

// Fetch run history with new columns
$histStmt = $pdo->prepare('
    SELECT id, session_name, summary, created_at, status,
           succeeded, failed, needs_review, total_items
    FROM run_history
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 20
');
$histStmt->execute([$_SESSION['user_id']]);
$history = $histStmt->fetchAll();

// Fetch all run_items for those runs in one query
$runItems = [];
if (!empty($history)) {
    $ids = implode(',', array_map('intval', array_column($history, 'id')));
    $itemStmt = $pdo->query("
        SELECT run_history_id, scout_name, type, item_name, requirement,
               completion_date, status, note
        FROM run_items
        WHERE run_history_id IN ({$ids})
        ORDER BY id ASC
    ");
    foreach ($itemStmt->fetchAll() as $item) {
        $runItems[$item['run_history_id']][] = $item;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<meta name="apple-mobile-web-app-capable" content="yes">
<title>Meeting Mode &mdash; Trailhead</title>
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
.tabs{display:flex;gap:.25rem;margin-bottom:.75rem;background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:.3rem;box-shadow:var(--shadow);}
.tab{flex:1;padding:.55rem .4rem;border:0;background:none;border-radius:8px;font-size:.82rem;font-weight:700;color:var(--gray);cursor:pointer;transition:.15s;line-height:1.2;}
.tab.active{background:var(--green);color:#fff;}
.tab:hover:not(.active){background:var(--green-light);color:var(--green);}
.pane{display:none;}.pane.active{display:block;}
.card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:.9rem;margin-bottom:.75rem;box-shadow:var(--shadow);}
.card h2{font-size:.95rem;font-weight:700;color:var(--green);margin-bottom:.6rem;display:flex;align-items:center;gap:.4rem;}
.card h2 .step{background:var(--green);color:#fff;width:20px;height:20px;border-radius:50%;font-size:.7rem;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;}
label{display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.3rem;}
textarea,input[type=text],input[type=date]{width:100%;padding:.65rem .75rem;border:1px solid var(--border);border-radius:8px;font-size:.95rem;font-family:inherit;resize:vertical;background:#fff;}
textarea:focus,input:focus{outline:2px solid var(--green);border-color:var(--green);}
#notes{min-height:110px;font-size:1rem;}
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
#micBtn{background:#fff;border:2px solid var(--border);color:var(--gray);border-radius:8px;padding:.6rem .9rem;font-size:.9rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;min-height:44px;}
#micBtn.recording{border-color:var(--red);color:var(--red);animation:pulse 1s infinite;}
@keyframes pulse{0%,100%{opacity:1;}50%{opacity:.5;}}
.meta-row{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-bottom:.6rem;}
.tbl-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;}
table{width:100%;border-collapse:collapse;font-size:.83rem;}
th{text-align:left;padding:.45rem .55rem;background:#f3f4f6;border-bottom:2px solid var(--border);font-size:.75rem;white-space:nowrap;}
td{padding:.45rem .55rem;border-bottom:1px solid #f3f4f6;vertical-align:middle;}
tr:last-child td{border-bottom:0;}
.badge{display:inline-flex;align-items:center;padding:.15rem .5rem;border-radius:999px;font-size:.7rem;font-weight:700;white-space:nowrap;}
.b-rank{background:var(--blue-soft);color:var(--blue);}
.b-mb{background:var(--purple-soft);color:var(--purple);}
.b-success{background:#d1fae5;color:#065f46;}
.b-failed{background:var(--red-soft);color:var(--red);}
.b-review{background:var(--amber-soft);color:var(--amber);}
.status-bar{background:var(--green-light);border:1px solid #a7d9d5;border-radius:8px;padding:.55rem .8rem;font-size:.83rem;color:var(--green-dark);display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;margin-bottom:.6rem;}
.status-bar .pill{font-weight:700;}
.prompt-box{background:#1e1e2e;color:#cdd6f4;border-radius:8px;padding:.8rem;font-family:monospace;font-size:.75rem;white-space:pre-wrap;word-break:break-word;max-height:260px;overflow-y:auto;margin-top:.6rem;}
.result-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;margin:.6rem 0;}
.result-card{text-align:center;padding:.7rem .4rem;border-radius:8px;}
.result-card .num{font-size:1.7rem;font-weight:800;}
.result-card .lbl{font-size:.72rem;font-weight:600;}
.rc-success{background:#d1fae5;color:#065f46;}
.rc-failed{background:var(--red-soft);color:var(--red);}
.rc-review{background:var(--amber-soft);color:var(--amber);}

/* History styles */
.hist-run{border:1px solid var(--border);border-radius:var(--radius);margin-bottom:.75rem;overflow:hidden;}
.hist-run-header{display:flex;justify-content:space-between;align-items:flex-start;padding:.7rem .9rem;background:#f9fafb;cursor:pointer;gap:.5rem;}
.hist-run-header:hover{background:var(--green-light);}
.hist-run-name{font-weight:700;font-size:.9rem;}
.hist-run-counts{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.25rem;}
.hist-run-time{font-size:.75rem;color:var(--gray);white-space:nowrap;}
.count-pill{font-size:.72rem;font-weight:700;padding:.15rem .45rem;border-radius:999px;}
.cp-success{background:#d1fae5;color:#065f46;}
.cp-failed{background:var(--red-soft);color:var(--red);}
.cp-review{background:var(--amber-soft);color:var(--amber);}
.cp-pending{background:#f3f4f6;color:var(--gray);}
.hist-run-items{display:none;border-top:1px solid var(--border);}
.hist-run-items.open{display:block;}
.hist-item-row{display:grid;grid-template-columns:1.5rem 1fr auto;gap:.4rem .6rem;align-items:center;padding:.5rem .9rem;border-bottom:1px solid #f9fafb;font-size:.82rem;}
.hist-item-row:last-child{border-bottom:0;}
.hist-item-row:nth-child(even){background:#fafafa;}
.hi-icon{font-size:1rem;text-align:center;}
.hi-detail{min-width:0;}
.hi-scout{font-weight:700;font-size:.83rem;}
.hi-sub{color:var(--gray);font-size:.75rem;}
.hi-date{font-size:.75rem;color:var(--gray);white-space:nowrap;}
.hi-note{font-size:.73rem;color:var(--red);margin-top:.1rem;}
.hist-empty-items{padding:.75rem .9rem;font-size:.8rem;color:var(--gray);font-style:italic;}

.chips{display:flex;flex-wrap:wrap;gap:.3rem;margin-bottom:.5rem;}
.chip{background:#f3f4f6;border:1px solid var(--border);border-radius:6px;padding:.2rem .55rem;font-size:.73rem;font-family:monospace;color:#374151;cursor:pointer;}
.chip:hover{background:var(--green-light);border-color:var(--green);color:var(--green);}
.empty{text-align:center;padding:1.25rem;color:var(--gray);font-size:.88rem;}
.note-box{background:var(--amber-soft);border:1px solid #fcd34d;border-radius:8px;padding:.55rem .8rem;font-size:.8rem;color:#78350f;margin-bottom:.6rem;}
.instr-note{font-size:.78rem;color:var(--gray);margin-top:.4rem;text-align:center;}
.log-pre{font-size:.75rem;white-space:pre-wrap;word-break:break-word;max-height:180px;overflow-y:auto;background:#f9fafb;padding:.7rem;border-radius:8px;border:1px solid var(--border);}
.restore-banner{display:none;background:#d1fae5;border:1px solid #6ee7b7;border-radius:8px;padding:.55rem .8rem;font-size:.83rem;color:#065f46;margin-bottom:.6rem;}
@media(max-width:520px){
  .meta-row{grid-template-columns:1fr;}
  .tabs .tab{font-size:.75rem;padding:.5rem .25rem;}
  .hdr-right a:not(:last-child){display:none;}
  .hist-item-row{grid-template-columns:1.5rem 1fr;}
  .hi-date{display:none;}
}
</style>
</head>
<body>
<header>
  <strong>&#9978; Trailhead</strong>
  <div class="hdr-right">
    <a href="/">Dashboard</a>
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

    <div class="restore-banner" id="restoreBanner"></div>

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
          <thead><tr><th>Scout</th><th>Type</th><th>Item</th><th>Date</th></tr></thead>
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
          <thead><tr><th>#</th><th>Scout</th><th>Type</th><th>Item</th><th>Date</th><th></th></tr></thead>
          <tbody id="queueBody"></tbody>
        </table>
      </div>
    </div>

    <div class="card" id="sendCard" style="display:none">
      <h2><span class="step">4</span> Send to Computer</h2>
      <div class="note-box">&#9888;&#65039; LastPass will auto-fill credentials. If a CAPTCHA image challenge appears, complete it manually before continuing.</div>
      <button class="btn btn-send" id="sendBtn" onclick="doSend()">&#128421;&#65039; Send to Computer</button>
      <p class="instr-note">Saves the queue and opens the Computer task page in a new tab &mdash; ready for Perplexity to read and execute.</p>
      <div class="btn-row">
        <button class="btn btn-ghost btn-sm" id="copyOnlyBtn" onclick="doCopyOnly()">&#128203; Copy prompt only</button>
        <button class="btn btn-ghost btn-sm" onclick="togglePrompt()">&#128065; View prompt</button>
      </div>
      <div id="promptWrap" style="display:none">
        <pre class="prompt-box" id="promptBox"></pre>
      </div>
    </div>
  </div>

  <!-- TAB 2: RESULTS -->
  <div id="tab-results" class="pane">
    <div class="card">
      <h2><span class="step">5</span> Paste Computer Results</h2>
      <label>Paste the full output from Computer here</label>
      <textarea id="resultPaste" style="min-height:110px;font-family:monospace;font-size:.83rem" placeholder="SUCCESS
Scout: Sam | Item: First Aid &mdash; Req 3 | Date: 4/28/2026 | Result: Success

NEEDS REVIEW
Scout: Dan | Item: Tenderfoot 1b | Date: 4/28/2026 | Result: Needs review | Reason: Multiple scouts matched"></textarea>
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
          <button class="btn btn-send" style="flex:1" onclick="doSendReview()">&#128203; Copy Review Prompt for Computer</button>
          <button class="btn btn-ghost btn-sm" onclick="doCopyReview()">&#128203; Copy only</button>
        </div>
        <p class="instr-note">Copies the review prompt &mdash; paste it into Computer and open Scoutbook there.</p>
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
  </div>

  <!-- TAB 3: HISTORY -->
  <div id="tab-history" class="pane">
    <div class="card">
      <h2>&#128336; Run History</h2>
      <?php if (empty($history)): ?>
        <div class="empty">No runs saved yet.<br>After parsing results, tap &ldquo;Save to History&rdquo; on the Results tab.</div>
      <?php else: ?>
        <?php foreach ($history as $h):
            $items     = $runItems[$h['id']] ?? [];
            $hasItems  = !empty($items);
            $hasCounts = $h['succeeded'] !== null;

            // Status icon for the run
            $runStatus = $h['status'] ?? null;
            if ($runStatus === 'complete')     $runIcon = '✅';
            elseif ($runStatus === 'failed')   $runIcon = '❌';
            elseif ($runStatus === 'running')  $runIcon = '⏳';
            else                               $runIcon = '📋';
        ?>
        <div class="hist-run">
          <div class="hist-run-header" onclick="toggleRun(<?= $h['id'] ?>)">
            <div>
              <div class="hist-run-name">
                <?= $runIcon ?> <?= htmlspecialchars($h['session_name'] ?: 'Unnamed session') ?>
              </div>
              <div class="hist-run-counts">
                <?php if ($hasCounts): ?>
                  <span class="count-pill cp-success">✅ <?= (int)$h['succeeded'] ?> entered</span>
                  <?php if ((int)$h['failed'] > 0): ?>
                    <span class="count-pill cp-failed">❌ <?= (int)$h['failed'] ?> failed</span>
                  <?php endif; ?>
                  <?php if ((int)$h['needs_review'] > 0): ?>
                    <span class="count-pill cp-review">⚠️ <?= (int)$h['needs_review'] ?> review</span>
                  <?php endif; ?>
                <?php elseif (!empty($h['summary'])): ?>
                  <span class="count-pill cp-pending"><?= htmlspecialchars($h['summary']) ?></span>
                <?php else: ?>
                  <span class="count-pill cp-pending">No results yet</span>
                <?php endif; ?>
              </div>
            </div>
            <div class="hist-run-time">
              <?= date('M j, g:ia', strtotime($h['created_at'] . ' UTC')) ?> CT
              <?php if ($hasItems): ?>
                <div style="text-align:right;font-size:.7rem;color:var(--green);margin-top:.2rem">▼ details</div>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($hasItems): ?>
          <div class="hist-run-items" id="run-items-<?= $h['id'] ?>">
            <?php foreach ($items as $item):
                $s = $item['status'];
              if ($s === 'entered')          { $statusIcon = '✅'; $statusLabel = 'Entered'; }
              elseif ($s === 'already_approved') { $statusIcon = '⚠️'; $statusLabel = 'Already approved'; }
              elseif ($s === 'needs_review') { $statusIcon = '⚠️'; $statusLabel = 'Needs review'; }
              elseif ($s === 'failed')       { $statusIcon = '❌'; $statusLabel = 'Failed'; }
              else                           { $statusIcon = '📋'; $statusLabel = $s; }
              $typeLabel = $item['type'] === 'merit_badge' ? 'MB' : 'Rank';
            ?>
            <div class="hist-item-row">
              <div class="hi-icon"><?= $statusIcon ?></div>
              <div class="hi-detail">
                <div class="hi-scout"><?= htmlspecialchars($item['scout_name']) ?></div>
                <div class="hi-sub">
                  <span class="badge <?= $item['type'] === 'merit_badge' ? 'b-mb' : 'b-rank' ?>"><?= $typeLabel ?></span>
                  <?= htmlspecialchars($item['item_name']) ?>
                  &mdash; Req <?= htmlspecialchars($item['requirement']) ?>
                  &mdash; <em><?= $statusLabel ?></em>
                </div>
                <?php if (!empty($item['note'])): ?>
                  <div class="hi-note">&#9432; <?= htmlspecialchars($item['note']) ?></div>
                <?php endif; ?>
              </div>
              <div class="hi-date"><?= date('M j', strtotime($item['completion_date'] . ' UTC')) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php elseif ($h['status'] === 'running' || $h['status'] === 'pending'): ?>
          <div class="hist-run-items" id="run-items-<?= $h['id'] ?>">
            <div class="hist-empty-items">⏳ Run in progress — results will appear here when Computer finishes.</div>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>
<script>
function toggleRun(id) {
    var el = document.getElementById('run-items-' + id);
    if (el) el.classList.toggle('open');
}
</script>
<script src="/meeting.js"></script>
</body>
</html>
