<?php
/**
 * Domain rules (read-only queries over the data). Each rule names the requirement it implements.
 */

const RATING_WINDOW_DAYS = 7; // NFR-02
const MIN_PASSWORD = 12;      // registration guidance
const CRITERIA = [
    ['key' => 'punctuality', 'label' => 'Punctuality', 'low' => 'Very late', 'high' => 'Right on time'],
    ['key' => 'respect', 'label' => 'Respect', 'low' => 'Disrespectful', 'high' => 'Considerate and respectful'],
    ['key' => 'fun', 'label' => 'Fun', 'low' => 'Not enjoyable', 'high' => 'Great company'],
];

function user_by_id(?string $id): ?array
{
    foreach (db()['users'] as $u) if ($u['id'] === $id) return $u;
    return null;
}

function user_by_email(string $email): ?array
{
    foreach (db()['users'] as $u) if (strcasecmp($u['email'], trim($email)) === 0) return $u;
    return null;
}

function activity_by_id(?string $id): ?array
{
    foreach (db()['activities'] as $a) if ($a['id'] === $id) return $a;
    return null;
}

function category_by_id(?string $id): array
{
    foreach (db()['categories'] as $c) if ($c['id'] === $id) return $c;
    return ['id' => $id, 'name' => 'Uncategorised', 'icon' => '•'];
}

/** ActivityStatus: published | cancelled | completed ("completed" is derived from the end time). */
function activity_status(array $a): string
{
    if ($a['status'] === 'cancelled') return 'cancelled';
    if (end_of($a) < new DateTimeImmutable()) return 'completed';
    return 'published';
}

function is_upcoming(array $a): bool { return activity_status($a) === 'published'; }
function has_started(array $a): bool { return start_of($a) <= new DateTimeImmutable(); }
function is_host(array $a, ?string $memberId): bool { return $memberId !== null && $a['hostId'] === $memberId; }

function participations_of(string $activityId, ?string $status = null): array
{
    return array_values(array_filter(db()['participations'], fn($p) => $p['activityId'] === $activityId && ($status === null || $p['status'] === $status)));
}

function accepted_of(string $activityId): array { return participations_of($activityId, 'accepted'); }
function pending_of(string $activityId): array { return participations_of($activityId, 'pending'); }
function going_count(array $a): int { return count(accepted_of($a['id'])); } // includes the host
function places_left(array $a): int { return max(0, (int)$a['capacity'] - going_count($a)); }

/** The member's live participation (pending or accepted) in an activity. */
function participation_for(?string $memberId, string $activityId): ?array
{
    foreach (db()['participations'] as $p) {
        if ($p['memberId'] === $memberId && $p['activityId'] === $activityId && in_array($p['status'], ['pending', 'accepted'], true)) return $p;
    }
    return null;
}

/** ParticipationStatus "attended" = accepted on an activity that has ended. */
function effective_status(array $p, array $a): string
{
    return ($p['status'] === 'accepted' && activity_status($a) === 'completed') ? 'attended' : $p['status'];
}

/**
 * FR-13 reputation: derived — mean of released ratings across the three criteria, rounded to 1 decimal.
 * Seed "history" stands in for released ratings received before the demo.
 */
function reputation(string $memberId): ?array
{
    $u = user_by_id($memberId);
    if (!$u || $u['kind'] !== 'member') return null;
    $h = $u['history'] ?? ['n' => 0, 'p' => 0, 'r' => 0, 'f' => 0];
    $released = array_filter(db()['ratings'], fn($r) => $r['ratedId'] === $memberId && $r['isReleased']);
    $n = $h['n'] + count($released);
    if ($n === 0) return ['score' => null, 'n' => 0, 'punctuality' => null, 'respect' => null, 'fun' => null];
    $sum = fn($k, $hk) => $h[$hk] * $h['n'] + array_sum(array_column($released, $k));
    $p = $sum('punctuality', 'p') / $n;
    $r = $sum('respect', 'r') / $n;
    $f = $sum('fun', 'f') / $n;
    return ['score' => round(($p + $r + $f) / 3, 1), 'n' => $n, 'punctuality' => round($p, 1), 'respect' => round($r, 1), 'fun' => round($f, 1)];
}

function badge_for(?array $rep): array
{
    if (!$rep || $rep['score'] === null || $rep['n'] < 3) return ['label' => 'New profile', 'amber' => false];
    if ($rep['score'] >= 4.5 && $rep['n'] >= 10) return ['label' => 'Community regular', 'amber' => true];
    if ($rep['score'] >= 4.0) return ['label' => 'Trusted member', 'amber' => true];
    return ['label' => 'Building trust', 'amber' => false];
}

function rep_text(string $memberId): string
{
    $rep = reputation($memberId);
    return ($rep && $rep['score'] !== null) ? number_format($rep['score'], 1) . ' ★' : 'New';
}

/** FR-06 / FR-14 / FR-16: why a member can't join (null = can join, 'login' = guest). */
function join_blocker(?array $member, array $a): ?string
{
    if (!$member) return 'login';
    if ($member['kind'] !== 'member') return 'Administrators cannot join activities.';
    $st = activity_status($a);
    if ($st === 'cancelled') return 'This activity was cancelled.';
    if ($st === 'completed') return 'This activity has already happened.';
    if (has_started($a)) return 'This activity has already started.';
    if (is_host($a, $member['id'])) return null;
    $host = user_by_id($a['hostId']);
    if (in_array($member['id'], $host['blocked'] ?? [], true) || in_array($a['hostId'], $member['blocked'] ?? [], true)) return 'You can’t join this activity.';
    if (places_left($a) === 0) return 'This activity is full.';
    if ($a['minReputation'] !== null) {
        $rep = reputation($member['id']);
        if (!$rep || $rep['score'] === null || $rep['score'] < $a['minReputation']) {
            return 'The host asks for a reputation of ' . number_format((float)$a['minReputation'], 1) . ' ★ or higher.';
        }
    }
    return null;
}

// ---------- Ratings (NFR-02) ----------

function rating_window_end(array $a): DateTimeImmutable
{
    return end_of($a)->modify('+' . RATING_WINDOW_DAYS . ' days');
}

function rating_eligibility(string $raterId, array $a): array
{
    if (activity_status($a) !== 'completed') return [false, 'You can rate once the activity has ended.'];
    $mine = null;
    foreach (db()['participations'] as $p) if ($p['memberId'] === $raterId && $p['activityId'] === $a['id']) $mine = $p;
    if (!$mine || effective_status($mine, $a) !== 'attended') return [false, 'Only attendees can rate this activity.'];
    if (new DateTimeImmutable() > rating_window_end($a)) return [false, 'The 7-day rating window has closed.'];
    return [true, null];
}

function find_rating(string $raterId, string $ratedId, string $activityId): ?array
{
    foreach (db()['ratings'] as $r) {
        if ($r['raterId'] === $raterId && $r['ratedId'] === $ratedId && $r['activityId'] === $activityId) return $r;
    }
    return null;
}

/** Fellow attendees and the rating this member already gave each of them (if any). */
function attendees_to_rate(string $raterId, array $a): array
{
    $out = [];
    foreach (accepted_of($a['id']) as $p) {
        if ($p['memberId'] === $raterId) continue;
        $out[] = ['member' => user_by_id($p['memberId']), 'rating' => find_rating($raterId, $p['memberId'], $a['id'])];
    }
    return $out;
}

function pending_rating_activities(string $memberId): array
{
    return array_values(array_filter(db()['activities'], function ($a) use ($memberId) {
        if (!rating_eligibility($memberId, $a)[0]) return false;
        foreach (attendees_to_rate($memberId, $a) as $x) if (!$x['rating']) return true;
        return false;
    }));
}

function notifications_for(string $memberId): array
{
    $list = array_values(array_filter(db()['notifications'], fn($n) => $n['memberId'] === $memberId));
    usort($list, fn($a, $b) => strcmp($b['at'], $a['at']));
    return $list;
}

function unread_count(string $memberId): int
{
    return count(array_filter(db()['notifications'], fn($n) => $n['memberId'] === $memberId && !$n['isRead']));
}

function sort_by_start(array $list): array
{
    usort($list, fn($x, $y) => start_of($x) <=> start_of($y));
    return $list;
}
