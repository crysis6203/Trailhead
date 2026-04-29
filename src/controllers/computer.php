<?php
// Trailhead — Computer Relay Page
date_default_timezone_set('America/Chicago');

if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit; }

$stmt = $pdo->prepare('SELECT queue_json, session_name FROM queue_state WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$row = $stmt->fetch();
$queue = ($row && $row['queue_json']) ? json_decode($row['queue_json'], true) : [];

// ── NEW: Create a run_history record and get its ID ──────────────────────────
$runId = null;
if (!empty($queue)) {
    // Use session name from queue if set, otherwise fall back to date/user
    $sessionName = (!empty($row['session_name']))
        ? trim($row['session_name'])
        : date('Y-m-d g:i a') . ' — ' . ($_SESSION['display_name'] ?? 'Unknown');
    $ins = $pdo->prepare("
        INSERT INTO run_history (user_id, session_name, status)
        VALUES (?, ?, 'pending')
    ");
    $ins->execute([$_SESSION['user_id'], $sessionName]);
    $runId = (int) $pdo->lastInsertId();
}

// ── Secret from .env ─────────────────────────────────────────────────────────
$secret = $_ENV['TRAILHEAD_SECRET'] ?? '';
// ─────────────────────────────────────────────────────────────────────────────

function buildAction($r, $idx) {
    $arrow = ' -> ';
    $i = $idx + 1;

    if ($r['type'] === 'mb') {
        $s = "  {$i}. Scout: {$r['name']}\n"
           . "     Type: Merit Badge requirement\n"
           . "     Badge: {$r['badge']}\n"
           . "     Requirement: {$r['req']}\n"
           . "     Date: {$r['date']}\n"
           . "     Navigation: Find scout{$arrow}scroll to Merit Badges{$arrow}View All{$arrow}search \"{$r['badge']}\"{$arrow}open badge{$arrow}find Req {$r['req']}{$arrow}approve";
        if (!empty($r['comment'])) $s .= "\n     Comment: {$r['comment']}";
        return $s;
    }

    $s = "  {$i}. Scout: {$r['name']}\n"
       . "     Type: Rank requirement\n"
       . "     Item: {$r['item']}\n"
       . "     Date: {$r['date']}\n"
       . "     Navigation: Find scout{$arrow}open rank card{$arrow}View More{$arrow}find {$r['item']}{$arrow}approve";
    if (!empty($r['comment'])) $s .= "\n     Comment: {$r['comment']}";
    return $s;
}

$prompt = '';
if (!empty($queue)) {
    $actions   = implode("\n\n", array_map('buildAction', $queue, array_keys($queue)));
    $site      = 'https://advancements.scouting.org';
    $acct      = 'chrispowell6203';
    $unit      = 'Scouts BSA Troop 911 Boys';
    $callbackUrl = 'https://trailhead.mjtroop911.org/api/run/callback.php';

    $prompt = "Use Scoutbook Plus to enter rank advancements and merit badge requirements.\n\n"
        . "ACCOUNT: {$acct}\n"
        . "UNIT CONTEXT: {$unit} - Position: Scoutmaster\n"
        . "SITE: {$site}\n\n"
        . "LOGIN STEPS (do these first):\n"
        . "- Navigate to {$site}\n"
        . "- Wait for the page to fully load.\n"
        . "- LastPass will auto-fill username and password - confirm both fields are populated.\n"
        . "- Click the \"I'm not a robot\" reCAPTCHA v2 checkbox and wait for the green checkmark.\n"
        . "- If an image challenge appears instead, STOP and notify me - I will complete it manually.\n"
        . "- Click the Login button and wait for the dashboard to load.\n"
        . "- Dismiss any startup popup.\n"
        . "- Verify account is {$acct} and context is Troop 911 Scoutmaster before proceeding.\n\n"
        . "GLOBAL RULES:\n"
        . "- Use the search field to find each scout by name.\n"
        . "- Process all items independently; continue the full queue even if one item fails.\n"
        . "- Check if the requirement is already APPROVED (fully approved by Scoutmaster, shows Approved status). If fully Approved, report Needs review - Already approved and skip it.\n"
        . "- If the requirement shows a completion date but is NOT yet Approved (status shows Completed or has a date but no Approved status), still proceed: click Approve, confirm the date matches what is given, and save.\n"
        . "- Only skip a requirement if it already shows fully Approved status. A completion date alone is not the same as Approved.\n"
        . "- If the scout cannot be found confidently, multiple scouts match, the requirement cannot be found,\n"
        . "  the merit badge cannot be matched exactly, or the page state is unexpected:\n"
        . "  do not guess - add to FAILED or NEEDS REVIEW with reason, then continue.\n"
        . "- Mark items Approved, set the date exactly as given, add comment if listed.\n"
        . "- Verify the requirement shows Approved with the correct date after saving.\n"
        . "- Only stop the entire run for a blocking login/session failure.\n\n"
        . "NAVIGATION PATHS:\n"
        . "- Rank requirements: search scout -> open rank card -> View More -> find requirement -> approve\n"
        . "- Merit badge requirements: search scout -> scroll to Merit Badges -> View All ->\n"
        . "  search badge name -> open badge -> find requirement -> approve\n"
        . "  (Use \"View All\" - do NOT rely on pending/started status)\n\n"
        . "ACTIONS (process in order):\n\n"
        . $actions
        . "\n\nREQUIRED OUTPUT FORMAT (after all actions are complete):\n\n"
        . "SUCCESS\n"
        . "Scout: [name] | Item: [item] | Requirement: [req] | Date: [date] | Result: Success\n\n"
        . "FAILED\n"
        . "Scout: [name] | Item: [item] | Requirement: [req] | Date: [date] | Result: Failed | Reason: [brief reason]\n\n"
        . "NEEDS REVIEW\n"
        . "Scout: [name] | Item: [item] | Requirement: [req] | Date: [date] | Result: Needs review | Reason: [Already approved / Multiple scouts matched / Requirement not found / Merit badge not found / Unexpected page state / CAPTCHA image challenge]\n\n"
        . "Then: \"Done. X succeeded, Y failed, Z need review.\"\n\n"
        . "---\n\n"
        . "FINAL STEP — CALLBACK (do this after all actions and output above are complete):\n\n"
        . "After printing the output above, send an HTTP POST request to:\n"
        . "  {$callbackUrl}\n\n"
        . "Use this exact JSON structure:\n\n"
        . "{\n"
        . "  \"run_id\": {$runId},\n"
        . "  \"secret\": \"{$secret}\",\n"
        . "  \"results\": [\n"
        . "    {\n"
        . "      \"scout\": \"[scout name]\",\n"
        . "      \"type\": \"[rank or merit_badge]\",\n"
        . "      \"item\": \"[rank name or badge name]\",\n"
        . "      \"requirement\": \"[requirement number e.g. 2a]\",\n"
        . "      \"date\": \"[date as given]\",\n"
        . "      \"status\": \"[entered or already_approved or needs_review or failed]\",\n"
        . "      \"note\": \"[reason if failed or needs_review, otherwise omit]\"\n"
        . "    }\n"
        . "  ]\n"
        . "}\n\n"
        . "Rules for the callback:\n"
        . "- Include every action in the results array, one object per requirement.\n"
        . "- For rank items split the item and requirement: e.g. \"Tenderfoot 2a\" -> item: \"Tenderfoot\", requirement: \"2a\".\n"
        . "- For merit badge items use the badge name as item and the req number as requirement.\n"
        . "- Map result statuses exactly: Success = entered, Already approved = already_approved,\n"
        . "  Needs review = needs_review, Failed = failed.\n"
        . "- Send the POST with header: Content-Type: application/json\n"
        . "- If the POST fails, note it at the end of your output but do not retry.\n"
        . "- The callback is the last thing you do. Do not take any further actions after it.";

    // ── NEW: Save the generated prompt into run_history ──────────────────────
    if ($runId) {
        $pdo->prepare("UPDATE run_history SET prompt = ?, status = 'running' WHERE id = ?")
            ->execute([$prompt, $runId]);
    }
    // ─────────────────────────────────────────────────────────────────────────
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Trailhead &mdash; Computer Task</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh;display:flex;flex-direction:column;}
.top-bar{background:#0f766e;color:#fff;padding:.6rem 1rem;display:flex;justify-content:space-between;align-items:center;font-size:.85rem;}
.top-bar strong{font-size:1rem;}
.top-bar a{color:rgba(255,255,255,.8);text-decoration:none;padding:.2rem .5rem;border-radius:4px;}
.top-bar a:hover{background:rgba(255,255,255,.15);color:#fff;}
.badge-count{background:#fff;color:#0f766e;font-weight:800;font-size:.8rem;border-radius:999px;padding:.15rem .55rem;margin-left:.4rem;}
/* Auto-copy banner */
.copy-banner{position:fixed;top:0;left:0;right:0;z-index:9999;padding:.9rem 1rem;text-align:center;font-size:1rem;font-weight:700;color:#fff;transition:opacity .5s ease;}
.copy-banner.ok{background:#059669;}
.copy-banner.warn{background:#d97706;}
.copy-banner.hidden{opacity:0;pointer-events:none;}
.container{flex:1;max-width:860px;margin:0 auto;padding:1.25rem 1rem;width:100%;}
.label{font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94a3b8;margin-bottom:.5rem;display:flex;justify-content:space-between;align-items:center;}
.copy-again{background:#1e40af;color:#fff;border:0;border-radius:6px;padding:.3rem .8rem;font-size:.75rem;font-weight:700;cursor:pointer;}
.copy-again:hover{background:#1d4ed8;}
.prompt-block{background:#1e293b;border:1px solid #334155;border-radius:10px;padding:1.1rem 1.25rem;font-family:'Courier New',Courier,monospace;font-size:.88rem;line-height:1.7;white-space:pre-wrap;word-break:break-word;color:#e2e8f0;user-select:all;-webkit-user-select:all;cursor:pointer;}
.prompt-block:active{background:#263348;}
.empty-state{text-align:center;padding:3rem 1rem;color:#64748b;}
.empty-state .icon{font-size:2.5rem;margin-bottom:.75rem;}
.empty-state p{margin-bottom:1rem;}
.empty-state a{display:inline-block;background:#0f766e;color:#fff;padding:.6rem 1.25rem;border-radius:8px;text-decoration:none;font-weight:700;font-size:.9rem;}
.meta{font-size:.78rem;color:#64748b;margin-top:.75rem;display:flex;gap:1rem;flex-wrap:wrap;}
/* Run ID badge */
.run-badge{display:inline-block;background:#0f766e;color:#fff;font-size:.72rem;font-weight:700;border-radius:6px;padding:.2rem .6rem;margin-left:.5rem;letter-spacing:.04em;}
</style>
</head>
<body>
<div class="copy-banner hidden" id="copyBanner"></div>

<div class="top-bar">
    <strong>&#9978; Trailhead &mdash; Computer Task</strong>
    <div style="display:flex;gap:.75rem;align-items:center;">
        <?php if (!empty($queue)): ?>
        <span><span class="badge-count"><?= count($queue) ?></span> item<?= count($queue) === 1 ? '' : 's' ?></span>
        <?php endif; ?>
        <a href="/meeting">&#8592; Back to Meeting</a>
    </div>
</div>

<div class="container">
    <?php if (empty($queue)): ?>
    <div class="empty-state">
        <div class="icon">&#128203;</div>
        <p>No queue loaded. Go back to Meeting Mode, parse your notes, and add items to the queue first.</p>
        <a href="/meeting">&#8592; Go to Meeting Mode</a>
    </div>
    <?php else: ?>
    <div class="label">
        <span>
            Task for Perplexity Computer &mdash; <?= date('F j, Y') ?> &mdash; <?= count($queue) ?> item<?= count($queue) === 1 ? '' : 's' ?>
            <?php if ($runId): ?>
            <span class="run-badge">Run #<?= $runId ?></span>
            <?php endif; ?>
        </span>
        <button class="copy-again" onclick="doCopy()">&#128203; Copy again</button>
    </div>
    <div class="prompt-block" id="promptBlock" onclick="doCopy()"><?= htmlspecialchars($prompt) ?></div>
    <div class="meta">
        <span id="copyStatus">&#9203; Copying&hellip;</span>
        <span>&#128336; <?= date('g:i a') ?></span>
        <span>&#128100; <?= htmlspecialchars($_SESSION['display_name'] ?? '') ?></span>
        <?php if ($runId): ?>
        <span>&#128190; Results will auto-save to Run #<?= $runId ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($queue)): ?>
<script>
var promptText = document.getElementById('promptBlock').textContent;

function showBanner(ok) {
    var b = document.getElementById('copyBanner');
    var s = document.getElementById('copyStatus');
    if (ok) {
        b.textContent = '\u2705 Prompt copied \u2014 switch to Perplexity Computer and paste.';
        b.className = 'copy-banner ok';
        s.textContent = '\u2705 Copied to clipboard \u2014 paste into Computer now.';
    } else {
        b.textContent = '\u26a0\ufe0f Clipboard blocked \u2014 click the prompt to select all, then copy manually.';
        b.className = 'copy-banner warn';
        s.textContent = '\u26a0\ufe0f Click prompt to select, then copy manually.';
    }
    b.classList.remove('hidden');
    setTimeout(function(){ b.classList.add('hidden'); }, 5000);
}

function doCopy() {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(promptText).then(function(){ showBanner(true); }).catch(function(){ showBanner(false); });
    } else {
        try {
            var t = document.createElement('textarea');
            t.value = promptText; t.style.position = 'fixed'; t.style.opacity = '0';
            document.body.appendChild(t); t.select(); document.execCommand('copy');
            document.body.removeChild(t); showBanner(true);
        } catch(e) { showBanner(false); }
    }
}

doCopy();
</script>
<?php endif; ?>
</body>
</html>
