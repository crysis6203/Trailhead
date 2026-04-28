<?php
// Dashboard controller
if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit; }

// Queue count (run_history based)
$runCount = 0;
try {
    $q = $pdo->prepare('SELECT COUNT(*) FROM run_history WHERE user_id = ?');
    $q->execute([$_SESSION['user_id']]);
    $runCount = (int)$q->fetchColumn();
} catch (Exception $e) {}

// Recent run history (last 5)
$runs = [];
try {
    $r = $pdo->prepare('SELECT id, session_name, summary, created_at FROM run_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
    $r->execute([$_SESSION['user_id']]);
    $runs = $r->fetchAll();
} catch (Exception $e) {}

// Recent sessions (last 5)
$sessions = [];
try {
    $s = $pdo->prepare('SELECT id, name, created_at FROM sessions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
    $s->execute([$_SESSION['user_id']]);
    $sessions = $s->fetchAll();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<meta name="apple-mobile-web-app-capable" content="yes">
<title>Trailhead Dashboard</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f9fafb;color:#111;font-size:15px;line-height:1.5;}
header{background:#0f766e;color:#fff;padding:.75rem 1rem;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:100;box-shadow:0 2px 8px rgba(0,0,0,.15);}
header strong{font-size:1.1rem;}
.hdr-right{display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;}
.hdr-right a{color:rgba(255,255,255,.85);text-decoration:none;font-size:.85rem;padding:.2rem .4rem;border-radius:4px;}
.hdr-right a:hover{color:#fff;background:rgba(255,255,255,.15);}
.wrap{max-width:900px;margin:0 auto;padding:.75rem;}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:1rem;margin-bottom:.75rem;box-shadow:0 1px 4px rgba(0,0,0,.06);}
.card h2{font-size:.95rem;font-weight:700;color:#0f766e;margin-bottom:.6rem;}
.kpi-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:.6rem;margin-bottom:.75rem;}
.kpi{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:.75rem 1rem;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.06);}
.kpi .num{font-size:2rem;font-weight:800;color:#0f766e;line-height:1;}
.kpi .lbl{font-size:.75rem;color:#6b7280;font-weight:600;margin-top:.2rem;}
.quick-links{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.5rem;margin-bottom:.75rem;}
.ql{display:flex;align-items:center;gap:.5rem;padding:.7rem 1rem;background:#fff;border:1px solid #e5e7eb;border-radius:10px;text-decoration:none;color:#111;font-weight:600;font-size:.88rem;box-shadow:0 1px 4px rgba(0,0,0,.06);transition:.15s;}
.ql:hover{border-color:#0f766e;color:#0f766e;background:#e6f4f3;}
.ql.primary{background:#0f766e;color:#fff;border-color:#0f766e;}
.ql.primary:hover{background:#0a5c55;}
.ql .icon{font-size:1.2rem;}
.hist-item{display:flex;justify-content:space-between;align-items:flex-start;padding:.5rem 0;border-bottom:1px solid #f3f4f6;gap:.5rem;}
.hist-item:last-child{border-bottom:0;}
.hist-meta{font-size:.75rem;color:#6b7280;white-space:nowrap;}
.empty{text-align:center;padding:1rem;color:#6b7280;font-size:.85rem;}
@media(max-width:520px){
  .hdr-right a:not(:last-child){display:none;}
}
</style>
</head>
<body>
<header>
  <strong>&#9978; Trailhead</strong>
  <div class="hdr-right">
    <a href="/sessions">Sessions</a>
    <a href="/meeting">Meeting</a>
    <span style="font-size:.8rem;opacity:.75">&#128100; <?= htmlspecialchars($_SESSION['display_name'] ?? '') ?></span>
    <a href="/logout">Logout</a>
  </div>
</header>

<div class="wrap">

  <!-- KPIs -->
  <div class="kpi-row">
    <div class="kpi"><div class="num"><?= count($sessions) ?: '&mdash;' ?></div><div class="lbl">Sessions</div></div>
    <div class="kpi"><div class="num"><?= $runCount ?: '&mdash;' ?></div><div class="lbl">Runs Saved</div></div>
  </div>

  <!-- Quick links -->
  <div class="quick-links">
    <a class="ql primary" href="/meeting"><span class="icon">&#128203;</span> Meeting Mode</a>
    <a class="ql" href="/sessions"><span class="icon">&#128197;</span> Sessions</a>
    <a class="ql" href="/logout"><span class="icon">&#128274;</span> Logout</a>
  </div>

  <!-- Recent runs -->
  <div class="card">
    <h2>&#128336; Recent Meeting Runs</h2>
    <?php if (empty($runs)): ?>
      <div class="empty">No runs yet &mdash; use <a href="/meeting" style="color:#0f766e">Meeting Mode</a> to enter advancements.</div>
    <?php else: ?>
      <?php foreach ($runs as $run): ?>
      <div class="hist-item">
        <div>
          <div style="font-weight:600;font-size:.9rem"><?= htmlspecialchars($run['session_name'] ?: 'Unnamed session') ?></div>
          <div style="font-size:.78rem;color:#6b7280"><?= htmlspecialchars($run['summary'] ?? '') ?></div>
        </div>
        <div class="hist-meta"><?= date('M j, g:ia', strtotime($run['created_at'])) ?></div>
      </div>
      <?php endforeach; ?>
      <div style="margin-top:.6rem;font-size:.8rem;"><a href="/meeting" style="color:#0f766e">View all in Meeting Mode &rarr;</a></div>
    <?php endif; ?>
  </div>

  <!-- Recent sessions -->
  <div class="card">
    <h2>&#128197; Recent Sessions</h2>
    <?php if (empty($sessions)): ?>
      <div class="empty">No sessions yet &mdash; <a href="/sessions" style="color:#0f766e">create one</a>.</div>
    <?php else: ?>
      <?php foreach ($sessions as $sess): ?>
      <div class="hist-item">
        <div style="font-weight:600;font-size:.9rem"><?= htmlspecialchars($sess['name']) ?></div>
        <div class="hist-meta"><?= date('M j, g:ia', strtotime($sess['created_at'])) ?></div>
      </div>
      <?php endforeach; ?>
      <div style="margin-top:.6rem;font-size:.8rem;"><a href="/sessions" style="color:#0f766e">View all sessions &rarr;</a></div>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
