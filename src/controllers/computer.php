<?php
// Trailhead — Computer Relay Page
// Opens as a clean page in Comet so the AI agent can read and execute the prompt directly.
if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit; }

// Load the saved queue for this user
$stmt = $pdo->prepare('SELECT queue_json FROM queue_state WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$row  = $stmt->fetch();
$queue = ($row && $row['queue_json']) ? json_decode($row['queue_json'], true) : [];

// Build the prompt server-side (mirrors buildPrompt() in meeting.js)
function buildAction($r, $idx) {
    $arrow = ' → ';
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
    $actions = implode("\n\n", array_map('buildAction', $queue, array_keys($queue)));
    $prompt =
        "Use Scoutbook Plus to enter rank advancements and merit badge requirements.\n\n"
      . "ACCOUNT: chrispowell6203\n"
      . "UNIT CONTEXT: Scouts BSA Troop 911 Boys — Position: Scoutmaster\n"
      . "SITE: https://advancements.scouting.org\n\n"
      . "LOGIN STEPS (do these first):\n"
      . "- Navigate to https://advancements.scouting.org\n"
      . "- Wait for the page to fully load.\n"
      . "- LastPass will auto-fill username and password — confirm both fields are populated.\n"
      . "- Click the \"I'm not a robot\" reCAPTCHA v2 checkbox and wait for the green checkmark.\n"
      . "- If an image challenge appears instead, STOP and notify me — I will complete it manually.\n"
      . "- Click the Login button and wait for the dashboard to load.\n"
      . "- Dismiss any startup popup.\n"
      . "- Verify account is chrispowell6203 and context is Troop 911 Scoutmaster before proceeding.\n\n"
      . "GLOBAL RULES:\n"
      . "- Use the search field to find each scout by name.\n"
      . "- Process all items independently; continue the full queue even if one item fails.\n"
      . "- Confirm the requirement is NOT already Approved before making any change.\n"
      . "- If already approved: report Needs review — Already approved. Do not re-enter.\n"
      . "- If the scout cannot be found confidently, multiple scouts match, the requirement cannot be found,\n"
      . "  the merit badge cannot be matched exactly, or the page state is unexpected:\n"
      . "  do not guess — add to FAILED or NEEDS REVIEW with reason, then continue.\n"
      . "- Mark items Approved, set the date exactly as given, add comment if listed.\n"
      . "- Verify the requirement shows Approved with the correct date after saving.\n"
      . "- Only stop the entire run for a blocking login/session failure.\n\n"
      . "NAVIGATION PATHS:\n"
      . "- Rank requirements: search scout → open rank card → View More → find requirement → approve\n"
      . "- Merit badge requirements: search scout → scroll to Merit Badges → View All →\n"
      . "  search badge name → open badge → find requirement → approve\n"
      . "  (Use \"View All\" — do NOT rely on pending/started status)\n\n"
      . "ACTIONS (process in order):\n\n"
      . $actions
      . "\n\nREQUIRED OUTPUT FORMAT (after all actions are complete):\n\n"
      . "SUCCESS\n"
      . "Scout: [name] | Item: [item] | Date: [date] | Result: Success\n\n"
      . "FAILED\n"
      . "Scout: [name] | Item: [item] | Date: [date] | Result: Failed | Reason: [brief reason]\n\n"
      . "NEEDS REVIEW\n"
      . "Scout: [name] | Item: [item] | Date: [date] | Result: Needs review | Reason: [Already approved / Multiple scouts matched / Requirement not found / Merit badge not found / Unexpected page state / CAPTCHA image challenge]\n\n"
      . 'Then: "Done. X succeeded, Y failed, Z need review."';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Trailhead — Computer Task</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{
  font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
  background:#0f172a;
  color:#e2e8f0;
  min-height:100vh;
  display:flex;
  flex-direction:column;
}
.top-bar{
  background:#0f766e;
  color:#fff;
  padding:.6rem 1rem;
  display:flex;
  justify-content:space-between;
  align-items:center;
  font-size:.85rem;
}
.top-bar strong{font-size:1rem;}
.top-bar a{color:rgba(255,255,255,.8);text-decoration:none;padding:.2rem .5rem;border-radius:4px;}
.top-bar a:hover{background:rgba(255,255,255,.15);color:#fff;}
.badge-count{
  background:#fff;
  color:#0f766e;
  font-weight:800;
  font-size:.8rem;
  border-radius:999px;
  padding:.15rem .55rem;
  margin-left:.4rem;
}
.container{
  flex:1;
  max-width:860px;
  margin:0 auto;
  padding:1.25rem 1rem;
  width:100%;
}
.label{
  font-size:.7rem;
  font-weight:700;
  letter-spacing:.08em;
  text-transform:uppercase;
  color:#94a3b8;
  margin-bottom:.5rem;
}
.prompt-block{
  background:#1e293b;
  border:1px solid #334155;
  border-radius:10px;
  padding:1.1rem 1.25rem;
  font-family:'Courier New',Courier,monospace;
  font-size:.88rem;
  line-height:1.7;
  white-space:pre-wrap;
  word-break:break-word;
  color:#e2e8f0;
  /* Make it easy for Comet to select all text */
  user-select:all;
  -webkit-user-select:all;
}
.empty-state{
  text-align:center;
  padding:3rem 1rem;
  color:#64748b;
}
.empty-state .icon{font-size:2.5rem;margin-bottom:.75rem;}
.empty-state p{margin-bottom:1rem;}
.empty-state a{
  display:inline-block;
  background:#0f766e;
  color:#fff;
  padding:.6rem 1.25rem;
  border-radius:8px;
  text-decoration:none;
  font-weight:700;
  font-size:.9rem;
}
.meta{
  font-size:.78rem;
  color:#64748b;
  margin-top:.75rem;
  display:flex;
  gap:1rem;
  flex-wrap:wrap;
}
</style>
</head>
<body>

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
  <div class="label">Task for Perplexity Computer &mdash; <?= date('F j, Y') ?> &mdash; <?= count($queue) ?> item<?= count($queue) === 1 ? '' : 's' ?></div>
  <div class="prompt-block" id="promptBlock"><?= htmlspecialchars($prompt) ?></div>
  <div class="meta">
    <span>&#9989; Queue loaded from your session</span>
    <span>&#128336; Generated <?= date('g:i a') ?></span>
    <span>&#128100; <?= htmlspecialchars($_SESSION['display_name'] ?? '') ?></span>
  </div>
<?php endif; ?>
</div>

</body>
</html>
