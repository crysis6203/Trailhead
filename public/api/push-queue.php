<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond_error('Method not allowed', 405);
}

// Grouped by exact (type, name, version, requirement code) — this is what
// lets the push task open Quick Entry once per requirement and check off
// every scout who needs it in a single pass, rather than one page load per
// scout. Each item carries the scout's real Scoutbook Member ID (scouts.id)
// since matching must happen by ID, never by name.
$rows = $db->query('
    SELECT s.id AS signoff_id, s.scout_id, sc.name AS scout_name,
           s.requirement_type, s.comment, s.completion_date,
           CASE WHEN s.requirement_type = "rank" THEN r.name ELSE mb.name END AS req_group_name,
           CASE WHEN s.requirement_type = "rank" THEN rv.version_year ELSE mbv.version_year END AS version_year,
           COALESCE(rr.requirement_code, mbr.requirement_code) AS req_code
    FROM signoffs s
    JOIN scouts sc ON sc.id = s.scout_id
    LEFT JOIN rank_requirements rr ON rr.id = s.rank_requirement_id
    LEFT JOIN rank_versions rv ON rv.id = rr.rank_version_id
    LEFT JOIN ranks r ON r.id = rv.rank_id
    LEFT JOIN merit_badge_requirements mbr ON mbr.id = s.merit_badge_requirement_id
    LEFT JOIN merit_badge_versions mbv ON mbv.id = mbr.merit_badge_version_id
    LEFT JOIN merit_badges mb ON mb.id = mbv.merit_badge_id
    WHERE s.status = "pending"
    ORDER BY req_group_name, req_code, sc.name
')->fetchAll();

$groups = [];
foreach ($rows as $row) {
    $key = implode('|', [$row['requirement_type'], $row['req_group_name'], $row['version_year'], $row['req_code']]);
    $groups[$key] ??= [
        'requirementType' => $row['requirement_type'],
        'name' => $row['req_group_name'],
        'version' => (string) $row['version_year'],
        'code' => $row['req_code'],
        'scouts' => [],
    ];
    $groups[$key]['scouts'][] = [
        'signoffId' => (int) $row['signoff_id'],
        'memberId' => (int) $row['scout_id'], // must match by this, never by name
        'name' => $row['scout_name'],
        'comment' => $row['comment'],
        'completionDate' => $row['completion_date'],
    ];
}

respond(['groups' => array_values($groups)]);
