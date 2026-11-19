<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_error('Method not allowed', 405);
}

/**
 * This endpoint is the PULL half of the sync. It intentionally has no code
 * path that can write to `signoffs` — that table is not even referenced
 * below. See /areas/trailhead: "the pull must never touch signoff/queue
 * history records."
 *
 * Expected payload shape (Cowork builds this from what it scrapes):
 * {
 *   "scouts": [{id, name, member_type, age, patrol, position,
 *               last_rank_approved, renewal_status, expiration_date}, ...],
 *   "ranks": [{name, version_year,
 *              requirements: [{code, text, sort_order}, ...]}, ...],
 *   "rank_progress": [{scout_id, rank_name, version_year, status}, ...],
 *   "merit_badges": [{name, eagle_required, versions: [{version_year,
 *       is_current, expiration_date,
 *       requirements: [{code, text, checkable, parent_code, sort_order}, ...]
 *   }, ...]}, ...],
 *   "merit_badge_progress": [{scout_id, badge_name, version_year, status}, ...]
 * }
 */

$body = json_input();
$updated = 0;

$db->beginTransaction();

try {
    $updated += syncScouts($db, $body['scouts'] ?? []);
    $updated += syncRanks($db, $body['ranks'] ?? []);
    $updated += syncRankProgress($db, $body['rank_progress'] ?? []);
    $updated += syncMeritBadges($db, $body['merit_badges'] ?? []);
    $updated += syncMeritBadgeProgress($db, $body['merit_badge_progress'] ?? []);

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    error_log('[sync] failed: ' . $e->getMessage());
    respond_error('Sync failed: ' . $e->getMessage(), 500);
}

respond(['updated' => $updated]);

// ----------------------------------------------------------------------

function syncScouts(PDO $db, array $scouts): int
{
    if (!$scouts) return 0;

    $stmt = $db->prepare('
        INSERT INTO scouts (id, name, member_type, age, patrol, position,
                             last_rank_approved, renewal_status, expiration_date, last_synced_at)
        VALUES (:id, :name, :member_type, :age, :patrol, :position,
                :last_rank_approved, :renewal_status, :expiration_date, NOW())
        ON DUPLICATE KEY UPDATE
            name = VALUES(name), member_type = VALUES(member_type), age = VALUES(age),
            patrol = VALUES(patrol), position = VALUES(position),
            last_rank_approved = VALUES(last_rank_approved),
            renewal_status = VALUES(renewal_status), expiration_date = VALUES(expiration_date),
            last_synced_at = NOW()
    ');

    foreach ($scouts as $s) {
        $stmt->execute([
            'id' => $s['id'], 'name' => $s['name'], 'member_type' => $s['member_type'],
            'age' => $s['age'] ?? null, 'patrol' => $s['patrol'] ?? null,
            'position' => $s['position'] ?? null, 'last_rank_approved' => $s['last_rank_approved'] ?? null,
            'renewal_status' => $s['renewal_status'] ?? null, 'expiration_date' => $s['expiration_date'] ?? null,
        ]);
    }

    return count($scouts);
}

function syncRanks(PDO $db, array $ranks): int
{
    if (!$ranks) return 0;

    $findRank = $db->prepare('SELECT id FROM ranks WHERE name = ?');
    $upsertVersion = $db->prepare('
        INSERT INTO rank_versions (rank_id, version_year, is_current)
        VALUES (?, ?, TRUE)
        ON DUPLICATE KEY UPDATE is_current = TRUE
    ');
    $findVersion = $db->prepare('SELECT id FROM rank_versions WHERE rank_id = ? AND version_year = ?');
    $upsertReq = $db->prepare('
        INSERT INTO rank_requirements (rank_version_id, requirement_code, display_text, sort_order)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE display_text = VALUES(display_text), sort_order = VALUES(sort_order)
    ');

    $count = 0;
    foreach ($ranks as $rank) {
        $findRank->execute([$rank['name']]);
        $rankId = $findRank->fetchColumn();
        if (!$rankId) continue; // ranks are seeded at migration time; unknown name is a data problem, skip

        $upsertVersion->execute([$rankId, $rank['version_year']]);
        $findVersion->execute([$rankId, $rank['version_year']]);
        $versionId = $findVersion->fetchColumn();

        foreach ($rank['requirements'] ?? [] as $req) {
            $upsertReq->execute([$versionId, $req['code'], $req['text'], $req['sort_order']]);
            $count++;
        }
    }

    return $count;
}

function syncRankProgress(PDO $db, array $progress): int
{
    if (!$progress) return 0;

    $findVersion = $db->prepare('
        SELECT rv.id FROM rank_versions rv JOIN ranks r ON r.id = rv.rank_id
        WHERE r.name = ? AND rv.version_year = ?
    ');
    $findRankId = $db->prepare('SELECT id FROM ranks WHERE name = ?');
    $upsert = $db->prepare('
        INSERT INTO scout_rank_progress (scout_id, rank_id, rank_version_id, status, last_synced_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE rank_version_id = VALUES(rank_version_id),
            status = VALUES(status), last_synced_at = NOW()
    ');

    $count = 0;
    foreach ($progress as $p) {
        $findRankId->execute([$p['rank_name']]);
        $rankId = $findRankId->fetchColumn();
        $findVersion->execute([$p['rank_name'], $p['version_year']]);
        $versionId = $findVersion->fetchColumn();
        if (!$rankId || !$versionId) continue;

        $upsert->execute([$p['scout_id'], $rankId, $versionId, $p['status']]);
        $count++;
    }

    return $count;
}

function syncMeritBadges(PDO $db, array $badges): int
{
    if (!$badges) return 0;

    $upsertBadge = $db->prepare('
        INSERT INTO merit_badges (name, eagle_required) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE eagle_required = VALUES(eagle_required)
    ');
    $findBadge = $db->prepare('SELECT id FROM merit_badges WHERE name = ?');
    $upsertVersion = $db->prepare('
        INSERT INTO merit_badge_versions (merit_badge_id, version_year, is_current, expiration_date)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE is_current = VALUES(is_current), expiration_date = VALUES(expiration_date)
    ');
    $findVersion = $db->prepare('SELECT id FROM merit_badge_versions WHERE merit_badge_id = ? AND version_year = ?');
    $upsertReq = $db->prepare('
        INSERT INTO merit_badge_requirements
            (merit_badge_version_id, parent_requirement_id, requirement_code, display_text, is_checkable, sort_order)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE display_text = VALUES(display_text),
            is_checkable = VALUES(is_checkable), sort_order = VALUES(sort_order)
    ');
    $findReqId = $db->prepare('SELECT id FROM merit_badge_requirements WHERE merit_badge_version_id = ? AND requirement_code = ?');

    $count = 0;
    foreach ($badges as $badge) {
        $upsertBadge->execute([$badge['name'], (int) !empty($badge['eagle_required'])]);
        $findBadge->execute([$badge['name']]);
        $badgeId = $findBadge->fetchColumn();

        foreach ($badge['versions'] ?? [] as $version) {
            $upsertVersion->execute([$badgeId, $version['version_year'], (int) !empty($version['is_current']), $version['expiration_date'] ?? null]);
            $findVersion->execute([$badgeId, $version['version_year']]);
            $versionId = $findVersion->fetchColumn();

            // Two passes: parents (no parent_code) first, so children can
            // resolve their parent's real DB id via requirement_code lookup.
            $requirements = $version['requirements'] ?? [];
            usort($requirements, fn($a, $b) => empty($b['parent_code']) <=> empty($a['parent_code']));

            foreach ($requirements as $req) {
                $parentId = null;
                if (!empty($req['parent_code'])) {
                    $findReqId->execute([$versionId, $req['parent_code']]);
                    $parentId = $findReqId->fetchColumn() ?: null;
                }
                $upsertReq->execute([
                    $versionId, $parentId, $req['code'], $req['text'],
                    (int) ($req['checkable'] ?? true), $req['sort_order'],
                ]);
                $count++;
            }
        }
    }

    return $count;
}

function syncMeritBadgeProgress(PDO $db, array $progress): int
{
    if (!$progress) return 0;

    $findBadgeId = $db->prepare('SELECT id FROM merit_badges WHERE name = ?');
    $findVersionId = $db->prepare('SELECT id FROM merit_badge_versions WHERE merit_badge_id = ? AND version_year = ?');
    $upsert = $db->prepare('
        INSERT INTO scout_merit_badge_progress (scout_id, merit_badge_id, merit_badge_version_id, status, last_synced_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE merit_badge_version_id = VALUES(merit_badge_version_id),
            status = VALUES(status), last_synced_at = NOW()
    ');

    $count = 0;
    foreach ($progress as $p) {
        $findBadgeId->execute([$p['badge_name']]);
        $badgeId = $findBadgeId->fetchColumn();
        if (!$badgeId) continue;

        $versionId = null;
        if (!empty($p['version_year'])) {
            $findVersionId->execute([$badgeId, $p['version_year']]);
            $versionId = $findVersionId->fetchColumn() ?: null;
        }

        $upsert->execute([$p['scout_id'], $badgeId, $versionId, $p['status']]);
        $count++;
    }

    return $count;
}
