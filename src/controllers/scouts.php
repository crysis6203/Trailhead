<?php
// Scouts — retired. Scout lookup is handled by Computer via Scoutbook search.
if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Scouts — Trailhead</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f9fafb;color:#111;font-size:15px;line-height:1.5;}
header{background:#0f766e;color:#fff;padding:.75rem 1rem;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:100;box-shadow:0 2px 8px rgba(0,0,0,.15);}
header strong{font-size:1.1rem;}
.hdr-right{display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;}
.hdr-right a{color:rgba(255,255,255,.85);text-decoration:none;font-size:.85rem;padding:.2rem .4rem;border-radius:4px;}
.hdr-right a:hover{color:#fff;background:rgba(255,255,255,.15);}
.wrap{max-width:640px;margin:0 auto;padding:1.5rem .75rem;}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:1.5rem;margin-bottom:.75rem;box-shadow:0 1px 4px rgba(0,0,0,.06);text-align:center;}
.icon{font-size:2.5rem;margin-bottom:.75rem;}
h2{font-size:1.1rem;font-weight:700;color:#0f766e;margin-bottom:.5rem;}
p{font-size:.9rem;color:#6b7280;max-width:48ch;margin:0 auto .75rem;}
.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.65rem 1.25rem;background:#0f766e;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;font-size:.9rem;margin-top:.25rem;}
.btn:hover{background:#0a5c55;}
</style>
</head>
<body>
<header>
  <strong>&#9978; Trailhead</strong>
  <div class="hdr-right">
    <a href="/">Dashboard</a>
    <a href="/sessions">Sessions</a>
    <a href="/meeting">Meeting</a>
    <span style="font-size:.8rem;opacity:.75">&#128100; <?= htmlspecialchars($_SESSION['display_name'] ?? '') ?></span>
    <a href="/logout">Logout</a>
  </div>
</header>
<div class="wrap">
  <div class="card">
    <div class="icon">&#129351;</div>
    <h2>Scout roster not stored here</h2>
    <p>Scout lookup is handled automatically by Computer during a run. Trailhead does not store scout names, BSA IDs, or member records &mdash; Computer searches Scoutbook directly using the names you enter in Meeting Mode.</p>
    <p>To enter advancements, use Meeting Mode to build and send a prompt.</p>
    <a class="btn" href="/meeting">&#128203; Go to Meeting Mode</a>
  </div>
</div>
</body>
</html>
