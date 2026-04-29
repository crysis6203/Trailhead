<?php
// Computer task page — Trailhead
if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit; }

$stmt = $pdo->prepare('SELECT queue_json FROM queue_state WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$row = $stmt->fetch();
$queue = ($row && $row['queue_json']) ? json_decode($row['queue_json'], true) : [];

// Create a run_history record and get its ID
$runId = null;
if (!empty($queue)) {
    $sessionName = date('Y-m-d g:i a') . ' — ' . ($_SESSION['display_name'] ?? 'Unknown');
    $ins = $pdo->prepare("
        INSERT INTO run_history (user_id, session_name, status)
        VALUES (?, ?, 'pending')
    ");
    $ins->execute([$_SESSION['user_id'], $sessionName]);
    $runId = (int) $pdo->lastInsertId();
}

// Secret from .env
$secret = $_ENV['TRAILHEAD_SECRET'] ?? '';

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
    $actions     = implode("\n\n", array_map('buildAction', $queue, array_keys($queue)));
    $site        = 'https://advancements.scouting.org';
    $acct        = 'chrispowell6203';
    $unit        = 'Scouts BSA Troop 911 Boys';
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
        . "- Confirm the requirement is NOT already Approved before making any change.\n"
        . "- If already approved: report Needs review - Already approved. Do not re-enter.\n"
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

    // Save the generated prompt into run_history
    if ($runId) {
        $pdo->prepare("UPDATE run_history SET prompt = ?, status = 'running' WHERE id = ?")
            ->execute([$prompt, $runId]);
    }
}
?>

No queue loaded. Go back to Meeting Mode, parse your notes, and add items to the queue first.

← Go to Meeting Mode