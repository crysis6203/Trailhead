<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond_error('Method not allowed', 405);
}

$scouts = $db->query('SELECT id, name, member_type, patrol, last_rank_approved
                       FROM scouts WHERE member_type = "youth" ORDER BY name')
             ->fetchAll();

// In-progress ranks per scout, with their requirement lists inline —
// excluding requirements this scout has already individually completed
// (a real bug found on first live pull) AND excluding non-checkable group
// headers (a second bug: ranks were assumed flat, but some — e.g. Star
// Scout's requirement 6, "Do the following" containing 6a/6b — nest just
// like merit badges do). Header rows are dropped entirely here rather
// than shown as a group, since the rank UI is a flat list; only the real
// checkable items (6a, 6b, etc.) appear.
$rankStmt = $db->prepare('
    SELECT srp.scout_id, r.name AS rank_name, rv.version_year,
           rr.id AS req_id, rr.requirement_code, rr.display_text, rr.sort_order
    FROM scout_rank_progress srp
    JOIN ranks r ON r.id = srp.rank_id
    JOIN rank_versions rv ON rv.id = srp.rank_version_id
    JOIN rank_requirements rr ON rr.rank_version_id = rv.id
    LEFT JOIN scout_rank_requirement_status status
        ON status.scout_id = srp.scout_id AND status.rank_requirement_id = rr.id
    WHERE srp.scout_id = ? AND srp.status = "in_progress"
        AND rr.is_checkable = TRUE
        AND (status.completed IS NULL OR status.completed = FALSE)
    ORDER BY r.sort_order, rr.sort_order
');

// In-progress merit badges per scout, with nested requirement groups —
// same completed-requirement exclusion. Group headers (is_checkable=0)
// are never individually "completed" themselves, so they're never
// filtered by this join; only checkable leaf requirements are excluded
// once done, exactly as we saw on the scout's own Scoutbook page.
$badgeStmt = $db->prepare('
    SELECT smp.scout_id, mb.name AS badge_name, mb.eagle_required,
           mbv.version_year, req.id, req.parent_requirement_id,
           req.requirement_code, req.display_text, req.is_checkable, req.sort_order
    FROM scout_merit_badge_progress smp
    JOIN merit_badges mb ON mb.id = smp.merit_badge_id
    JOIN merit_badge_versions mbv ON mbv.id = smp.merit_badge_version_id
    JOIN merit_badge_requirements req ON req.merit_badge_version_id = mbv.id
    LEFT JOIN scout_merit_badge_requirement_status status
        ON status.scout_id = smp.scout_id AND status.merit_badge_requirement_id = req.id
    WHERE smp.scout_id = ? AND smp.status = "in_progress"
        AND (req.is_checkable = FALSE OR status.completed IS NULL OR status.completed = FALSE)
    ORDER BY mb.name, req.sort_order
');

$result = [];

foreach ($scouts as $scout) {
    $rankStmt->execute([$scout['id']]);
    $rankRows = $rankStmt->fetchAll();

    $ranksByName = [];
    foreach ($rankRows as $row) {
        $ranksByName[$row['rank_name']] ??= [
            'rank' => $row['rank_name'],
            'version' => (string) $row['version_year'],
            'reqs' => [],
        ];
        $ranksByName[$row['rank_name']]['reqs'][] = [
            'id' => (int) $row['req_id'],
            'code' => $row['requirement_code'],
            'text' => $row['display_text'],
        ];
    }

    $badgeStmt->execute([$scout['id']]);
    $badgeRows = $badgeStmt->fetchAll();

    // Drop any in-progress rank left with zero remaining requirements
    // (everything already completed, just not yet marked awarded in
    // Scoutbook) so it doesn't show as an empty tab.
    $ranksByName = array_filter($ranksByName, fn($r) => count($r['reqs']) > 0);

    $badgesByName = [];
    $groupsByReqId = []; // req id -> group code, for attaching children to parents
    foreach ($badgeRows as $row) {
        $badgesByName[$row['badge_name']] ??= [
            'name' => $row['badge_name'],
            'version' => (string) $row['version_year'],
            'eagleRequired' => (bool) $row['eagle_required'],
            'groups' => [],
        ];
        $groupsByReqId[$row['id']] = $row;
    }

    // Build group->items structure: top-level (no parent) rows are group
    // headers; their children (by parent_requirement_id) are the checkable
    // items. Matches what we verified live in Scoutbook (e.g. First Aid's
    // "1. Handling a First Aid Emergency" -> "1a", "1b"...).
    foreach ($badgeRows as $row) {
        if ($row['parent_requirement_id'] !== null) {
            continue; // handled as a child below
        }
        $badge = &$badgesByName[$row['badge_name']];
        $children = array_values(array_filter($badgeRows, fn($r) => $r['parent_requirement_id'] === $row['id']));
        if (!$children && !$row['is_checkable']) {
            continue; // header whose children are all already completed — nothing left to show
        }
        $badge['groups'][] = [
            'code' => $row['requirement_code'],
            'header' => $row['display_text'],
            'checkable' => (bool) $row['is_checkable'],
            'items' => array_map(fn($c) => ['id' => (int) $c['id'], 'code' => $c['requirement_code'], 'text' => $c['display_text']], $children),
        ];
        unset($badge);
    }

    // Drop any badge left with zero groups (everything in it turned out
    // already completed) so it doesn't show as an empty tab.
    $badgesByName = array_filter($badgesByName, fn($b) => count($b['groups']) > 0);

    $result[] = [
        'id' => $scout['id'],
        'name' => $scout['name'],
        'lastRank' => $scout['last_rank_approved'],
        'patrol' => $scout['patrol'],
        'inProgressRanks' => array_values($ranksByName),
        'inProgressBadges' => array_values($badgesByName),
    ];
}

respond(['scouts' => $result]);
