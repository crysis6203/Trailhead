<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond_error('Method not allowed', 405);
}

$scouts = $db->query('SELECT id, name, member_type, patrol, last_rank_approved
                       FROM scouts WHERE member_type = "youth" ORDER BY name')
             ->fetchAll();

// In-progress ranks per scout, with their requirement lists inline.
$rankStmt = $db->prepare('
    SELECT srp.scout_id, r.name AS rank_name, rv.version_year,
           rr.id AS req_id, rr.requirement_code, rr.display_text, rr.sort_order
    FROM scout_rank_progress srp
    JOIN ranks r ON r.id = srp.rank_id
    JOIN rank_versions rv ON rv.id = srp.rank_version_id
    JOIN rank_requirements rr ON rr.rank_version_id = rv.id
    WHERE srp.scout_id = ? AND srp.status = "in_progress"
    ORDER BY r.sort_order, rr.sort_order
');

// In-progress merit badges per scout, with nested requirement groups.
$badgeStmt = $db->prepare('
    SELECT smp.scout_id, mb.name AS badge_name, mb.eagle_required,
           mbv.version_year, req.id, req.parent_requirement_id,
           req.requirement_code, req.display_text, req.is_checkable, req.sort_order
    FROM scout_merit_badge_progress smp
    JOIN merit_badges mb ON mb.id = smp.merit_badge_id
    JOIN merit_badge_versions mbv ON mbv.id = smp.merit_badge_version_id
    JOIN merit_badge_requirements req ON req.merit_badge_version_id = mbv.id
    WHERE smp.scout_id = ? AND smp.status = "in_progress"
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
        $badge['groups'][] = [
            'code' => $row['requirement_code'],
            'header' => $row['display_text'],
            'checkable' => (bool) $row['is_checkable'],
            'items' => array_map(fn($c) => ['id' => (int) $c['id'], 'code' => $c['requirement_code'], 'text' => $c['display_text']], $children),
        ];
        unset($badge);
    }

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
