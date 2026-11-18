<?php
/**
 * Data store + service layer.
 *
 * The data lives in data/rockon.json (created from the seed on first run). Every use case is one
 * function below that validates its input against the rules in the design report and returns
 * ['ok' => bool, 'error' => ?string, ...]. Swapping the JSON file for MySQL means rewriting db_load()
 * and db_save() (or these functions) — the pages do not change.
 */

function &db(): array
{
    return $GLOBALS['ROCKON_DB'];
}

function db_load(): void
{
    $data = null;
    if (is_file(DATA_FILE)) {
        $fh = fopen(DATA_FILE, 'rb');
        if ($fh && flock($fh, LOCK_SH)) {
            $data = json_decode(stream_get_contents($fh), true);
            flock($fh, LOCK_UN);
        }
        if ($fh) fclose($fh);
    }
    if (!is_array($data) || ($data['version'] ?? 0) !== 1) {
        $data = build_seed();
        $GLOBALS['ROCKON_DB'] = $data;
        db_save();
        return;
    }
    $GLOBALS['ROCKON_DB'] = $data;
}

function db_save(): void
{
    $dir = dirname(DATA_FILE);
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $tmp = DATA_FILE . '.' . bin2hex(random_bytes(4)) . '.tmp';
    file_put_contents($tmp, json_encode($GLOBALS['ROCKON_DB'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    rename($tmp, DATA_FILE); // atomic replace
}

function db_reset(): void
{
    $GLOBALS['ROCKON_DB'] = build_seed();
    db_save();
}

/** Index of a record by id in one of the collections, or -1. */
function idx(string $collection, string $id): int
{
    foreach (db()[$collection] as $i => $row) if ($row['id'] === $id) return $i;
    return -1;
}

function ok(array $extra = []): array { return ['ok' => true, 'error' => null] + $extra; }
function fail(string $error, array $extra = []): array { return ['ok' => false, 'error' => $error] + $extra; }

function notify(string $memberId, string $type, string $content, string $detail, string $link): void
{
    $d = &db();
    $d['notifications'][] = ['id' => uid('n'), 'memberId' => $memberId, 'type' => $type, 'content' => $content,
        'detail' => $detail, 'link' => $link, 'isRead' => false, 'at' => now_iso()];
}

// ---------- Authentication (FR-01) ----------

function svc_login(string $email, string $password): array
{
    $u = user_by_email($email);
    // One generic message: never reveal whether the email or the password was wrong.
    if (!$u || !password_verify($password, $u['passwordHash'])) {
        return fail('Email or password isn’t correct. Check your details and try again.');
    }
    if (!empty($u['suspended'])) return fail('This account is suspended. Contact support if you think this is a mistake.');
    login_as($u['id']);
    return ok(['user' => $u]);
}

function svc_register(array $v): array
{
    $e = [];
    if ($v['name'] === '') $e['name'] = 'Enter your name.';
    elseif (mb_strlen($v['name']) > 60) $e['name'] = 'Keep your name under 60 characters.';
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $e['email'] = 'Enter a valid email address, like name@example.com.';
    elseif (user_by_email($v['email'])) $e['email'] = 'An account with this email already exists. Try logging in.';
    if (mb_strlen($v['password']) < MIN_PASSWORD) $e['password'] = 'Use at least ' . MIN_PASSWORD . ' characters.';
    if ($v['confirm'] !== $v['password']) $e['confirm'] = 'The two passwords don’t match.';
    if (!$v['adult']) $e['adult'] = 'You must confirm you are 18 or older to create an account.';
    if ($e) return fail('Please fix the highlighted fields.', ['errors' => $e]);

    $id = uid('u');
    $d = &db();
    $d['users'][] = [
        'id' => $id, 'kind' => 'member', 'name' => $v['name'], 'email' => $v['email'],
        'passwordHash' => password_hash($v['password'], PASSWORD_DEFAULT), // NFR-01
        'photo' => null, 'bio' => '', 'interests' => [], 'languages' => [], 'joinedAt' => now_iso(),
        'suspended' => false, 'blocked' => [], 'history' => ['n' => 0, 'p' => 0, 'r' => 0, 'f' => 0],
    ];
    notify($id, 'welcome', 'Welcome to RockOn!', 'Add a bio and interests so hosts know who’s asking.', 'profile.php?id=me');
    db_save();
    login_as($id);
    return ok(['id' => $id]);
}

// ---------- Profile (FR-02, FR-16) ----------

function svc_update_profile(string $meId, array $patch): array
{
    $i = idx('users', $meId);
    if ($i < 0) return fail('Not logged in.');
    $d = &db();
    $d['users'][$i] = array_merge($d['users'][$i], $patch);
    db_save();
    return ok();
}

function svc_toggle_block(string $meId, string $targetId): array
{
    if ($meId === $targetId) return fail('You can’t block yourself.');
    $i = idx('users', $meId);
    $d = &db();
    $blocked = $d['users'][$i]['blocked'] ?? [];
    $was = in_array($targetId, $blocked, true);
    if ($was) {
        $blocked = array_values(array_diff($blocked, [$targetId]));
    } else {
        $blocked[] = $targetId;
        // Pending requests from the blocked member to my activities are declined.
        foreach ($d['participations'] as &$p) {
            $a = activity_by_id($p['activityId']);
            if ($p['memberId'] === $targetId && $p['status'] === 'pending' && $a && $a['hostId'] === $meId) $p['status'] = 'declined';
        }
        unset($p);
    }
    $d['users'][$i]['blocked'] = $blocked;
    db_save();
    return ok(['blocked' => !$was]);
}

// ---------- Activities (FR-04, FR-08, FR-10, FR-14) ----------

function validate_activity(array $v, ?string $id): array
{
    $e = [];
    if ($v['title'] === '') $e['title'] = 'Give your activity a title.';
    elseif (mb_strlen($v['title']) > 80) $e['title'] = 'Keep the title under 80 characters.';
    $catIds = array_column(db()['categories'], 'id');
    if (!in_array($v['categoryId'], $catIds, true)) $e['categoryId'] = 'Choose a category.';
    if (mb_strlen($v['description']) < 20) $e['description'] = 'Describe the plan in at least 20 characters.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v['date'])) $e['date'] = 'Pick a date.';
    if (!preg_match('/^\d{2}:\d{2}$/', $v['startTime'])) $e['startTime'] = 'Pick a start time.';
    if (!preg_match('/^\d{2}:\d{2}$/', $v['endTime'])) $e['endTime'] = 'Pick an end time.';
    if (empty($e['startTime']) && empty($e['endTime']) && $v['endTime'] <= $v['startTime']) $e['endTime'] = 'End time must be after the start time.';
    if (empty($e['date']) && empty($e['startTime']) && to_dt($v['date'], $v['startTime']) < new DateTimeImmutable()) $e['date'] = 'Choose a date and time in the future.';
    if (!isset(PLACES[$v['placeId']])) $e['placeId'] = 'Choose an area.';
    if ($v['meetingPoint'] === '') $e['meetingPoint'] = 'Add a clear meeting point.';
    $going = $id ? count(accepted_of($id)) : 1;
    if (!is_int($v['capacity']) || $v['capacity'] < 2) $e['capacity'] = 'Capacity must be at least 2 (it includes you).';
    elseif ($v['capacity'] > 500) $e['capacity'] = 'Keep it under 500 people.';
    elseif ($v['capacity'] < $going) $e['capacity'] = "$going people are already going. Capacity can’t be lower.";
    if ($v['minReputation'] !== null && ($v['minReputation'] < 1 || $v['minReputation'] > 5)) $e['minReputation'] = 'Use a value between 1.0 and 5.0.';
    if (!in_array($v['joinPolicy'], ['open', 'hostApproval'], true)) $e['joinPolicy'] = 'Choose how people can join.';
    return $e;
}

function svc_save_activity(string $meId, array $input, ?string $id = null): array
{
    $e = validate_activity($input, $id);
    if ($e) return fail('Please fix the highlighted fields.', ['errors' => $e]);
    $d = &db();
    if ($id) {
        $i = idx('activities', $id);
        if ($i < 0 || $d['activities'][$i]['hostId'] !== $meId) return fail('Only the host can edit this activity.');
        $old = $d['activities'][$i];
        if (!array_key_exists('photo', $input) || $input['photo'] === false) $input['photo'] = $old['photo'];
        $d['activities'][$i] = array_merge($old, $input);
        $changed = false;
        foreach (['date', 'startTime', 'endTime', 'placeId', 'meetingPoint'] as $k) if ($old[$k] !== $input[$k]) $changed = true;
        if ($changed) {
            foreach (accepted_of($id) as $p) {
                if ($p['memberId'] !== $meId) notify($p['memberId'], 'changed', "{$input['title']} was updated", 'The date, time or meeting point changed. Check the details.', "activity.php?id=$id");
            }
        }
    } else {
        $id = uid('a');
        $d['activities'][] = array_merge(['id' => $id, 'hostId' => $meId, 'status' => 'published', 'createdAt' => now_iso(),
            'cancelReason' => '', 'removedByModerator' => false], $input);
        // The host has a Participation too (Activity has 1..* Participation).
        $d['participations'][] = ['id' => uid('p'), 'memberId' => $meId, 'activityId' => $id, 'status' => 'accepted', 'joinedAt' => now_iso()];
    }
    db_save();
    return ok(['id' => $id]);
}

function svc_cancel_activity(string $meId, string $id, string $reason): array
{
    $i = idx('activities', $id);
    $d = &db();
    if ($i < 0 || $d['activities'][$i]['hostId'] !== $meId) return fail('Only the host can cancel this activity.');
    if (activity_status($d['activities'][$i]) !== 'published') return fail('This activity can no longer be cancelled.');
    $d['activities'][$i]['status'] = 'cancelled';
    $d['activities'][$i]['cancelReason'] = mb_substr($reason, 0, 300);
    $title = $d['activities'][$i]['title'];
    foreach ($d['participations'] as $p) {
        if ($p['activityId'] === $id && $p['memberId'] !== $meId && in_array($p['status'], ['accepted', 'pending'], true)) {
            notify($p['memberId'], 'cancelled', "$title was cancelled", $reason !== '' ? "Host’s note: $reason" : 'The host cancelled this plan.', "activity.php?id=$id");
        }
    }
    db_save();
    return ok();
}

// ---------- Joining (FR-06, FR-07) ----------

function svc_request_join(string $meId, string $activityId): array
{
    $me = user_by_id($meId);
    $a = activity_by_id($activityId);
    if (!$a) return fail('Activity not found.');
    $block = join_blocker($me, $a);
    if ($block) return fail($block === 'login' ? 'Log in to request to join.' : $block);
    if (participation_for($meId, $activityId)) return fail('You already asked to join.');
    $status = $a['joinPolicy'] === 'open' ? 'accepted' : 'pending';
    $d = &db();
    $d['participations'][] = ['id' => uid('p'), 'memberId' => $meId, 'activityId' => $activityId, 'status' => $status, 'joinedAt' => now_iso()];
    if ($status === 'accepted') notify($a['hostId'], 'joined', "{$me['name']} joined {$a['title']}", places_left($a) . ' places left.', "activity.php?id={$a['id']}");
    else notify($a['hostId'], 'request', "{$me['name']} requested a place", "{$a['title']} · Review the request.", 'dashboard.php');
    db_save();
    return ok(['status' => $status]);
}

function svc_leave(string $meId, string $activityId): array
{
    $a = activity_by_id($activityId);
    if (!$a) return fail('Activity not found.');
    if (is_host($a, $meId)) return fail('Hosts cancel the activity instead of leaving it.');
    $d = &db();
    foreach ($d['participations'] as $i => $p) {
        if ($p['memberId'] === $meId && $p['activityId'] === $activityId && in_array($p['status'], ['accepted', 'pending'], true)) {
            $was = $p['status'];
            $d['participations'][$i]['status'] = 'left';
            if ($was === 'accepted') notify($a['hostId'], 'left', user_by_id($meId)['name'] . " left {$a['title']}", places_left($a) . ' places now open.', "activity.php?id={$a['id']}");
            db_save();
            return ok(['was' => $was]);
        }
    }
    return fail('You are not part of this activity.');
}

function svc_decide(string $meId, string $participationId, bool $accept): array
{
    $i = idx('participations', $participationId);
    $d = &db();
    if ($i < 0) return fail('Request not found.');
    $p = $d['participations'][$i];
    $a = activity_by_id($p['activityId']);
    if (!$a || $a['hostId'] !== $meId) return fail('Only the host can review requests.');
    if ($p['status'] !== 'pending') return fail('This request was already handled.');
    $name = user_by_id($p['memberId'])['name'];
    if ($accept) {
        if (places_left($a) === 0) return fail('The activity is full. Increase capacity or decline the request.'); // capacity checked on acceptance
        $d['participations'][$i]['status'] = 'accepted';
        notify($p['memberId'], 'accepted', "You’re in: {$a['title']}", 'The participant chat is now open.', "activity.php?id={$a['id']}");
    } else {
        $d['participations'][$i]['status'] = 'declined';
        notify($p['memberId'], 'declined', "Your request for {$a['title']} wasn’t accepted", 'There are plenty of other plans on Explore.', 'explore.php');
    }
    db_save();
    return ok(['name' => $name]);
}

// ---------- Chat (FR-09) ----------

function svc_send_message(string $meId, string $activityId, string $content): array
{
    $text = trim($content);
    if ($text === '') return fail('Write a message first.');
    $a = activity_by_id($activityId);
    $mine = participation_for($meId, $activityId);
    if (!$a || !$mine || $mine['status'] !== 'accepted') return fail('Only accepted participants can send messages.');
    if (activity_status($a) === 'cancelled') return fail('This activity was cancelled.');
    $d = &db();
    $d['messages'][] = ['id' => uid('m'), 'activityId' => $activityId, 'memberId' => $meId, 'content' => mb_substr($text, 0, 1000), 'sentAt' => now_iso()];
    db_save();
    return ok();
}

// ---------- Ratings (FR-11, FR-12, NFR-02, NFR-03) ----------

function svc_submit_rating(string $meId, string $activityId, string $ratedId, array $scores, string $comment): array
{
    $a = activity_by_id($activityId);
    if (!$a) return fail('Activity not found.');
    if ($ratedId === $meId) return fail('You cannot rate yourself.');
    [$ok, $why] = rating_eligibility($meId, $a);
    if (!$ok) return fail($why);
    if (!in_array($ratedId, array_column(accepted_of($activityId), 'memberId'), true)) return fail('This person did not attend.');
    if (find_rating($meId, $ratedId, $activityId)) return fail('You already rated this person for this activity.');
    foreach (['punctuality', 'respect', 'fun'] as $k) {
        if (!isset($scores[$k]) || $scores[$k] < 1 || $scores[$k] > 5) return fail('Choose a score from 1 to 5 for each criterion.');
    }
    $d = &db();
    $reverseIdx = -1;
    foreach ($d['ratings'] as $i => $r) {
        if ($r['raterId'] === $ratedId && $r['ratedId'] === $meId && $r['activityId'] === $activityId) $reverseIdx = $i;
    }
    $released = $reverseIdx >= 0;
    $d['ratings'][] = ['id' => uid('r'), 'raterId' => $meId, 'ratedId' => $ratedId, 'activityId' => $activityId,
        'punctuality' => $scores['punctuality'], 'respect' => $scores['respect'], 'fun' => $scores['fun'],
        'comment' => mb_substr(trim($comment), 0, 500), 'date' => now_iso(), 'isReleased' => $released];
    $meName = user_by_id($meId)['name'];
    $themName = user_by_id($ratedId)['name'];
    if ($released) {
        $d['ratings'][$reverseIdx]['isReleased'] = true;
        notify($ratedId, 'released', 'A rating was released', "You and $meName both rated each other for {$a['title']}.", "profile.php?id=$ratedId");
        notify($meId, 'released', 'A rating was released', "You and $themName both rated each other for {$a['title']}.", "profile.php?id=$meId");
    } else {
        notify($ratedId, 'rating', "How was {$a['title']}?", "$meName left feedback. Rate them back to release both ratings.", "rate.php?id=$activityId");
    }
    db_save();
    return ok(['released' => $released]);
}

// ---------- Reports (FR-15) ----------

function svc_report(string $meId, string $targetType, string $targetId, string $reason, string $details): array
{
    if ($reason === '') return fail('Choose a reason.');
    if (!in_array($targetType, ['member', 'activity'], true)) return fail('Unknown report target.');
    if (($targetType === 'member' && !user_by_id($targetId)) || ($targetType === 'activity' && !activity_by_id($targetId))) return fail('Report target not found.');
    $d = &db();
    $d['reports'][] = ['id' => sprintf('R-%03d', count($d['reports']) + 26), 'reporterId' => $meId, 'targetType' => $targetType, 'targetId' => $targetId,
        'reason' => mb_substr($reason, 0, 80), 'summary' => $details !== '' ? mb_substr($details, 0, 600) : 'No additional details provided.',
        'excerpt' => '', 'date' => now_iso(), 'status' => 'open', 'resolution' => null];
    db_save();
    return ok();
}

// ---------- Notifications (FR-18) ----------

function svc_mark_read(string $meId, ?string $id = null): void
{
    $d = &db();
    foreach ($d['notifications'] as &$n) {
        if ($n['memberId'] === $meId && ($id === null || $n['id'] === $id)) $n['isRead'] = true;
    }
    unset($n);
    db_save();
}

// ---------- Administration (FR-17) ----------

function svc_set_report_status(string $reportId, string $status, ?string $resolution = null): array
{
    $i = idx('reports', $reportId);
    if ($i < 0) return fail('Report not found.');
    $d = &db();
    $d['reports'][$i]['status'] = $status;
    if ($resolution) $d['reports'][$i]['resolution'] = $resolution;
    db_save();
    return ok();
}

function svc_remove_content(string $reportId, string $note): array
{
    $ri = idx('reports', $reportId);
    $d = &db();
    if ($ri < 0 || $d['reports'][$ri]['targetType'] !== 'activity') return fail('Only activity listings can be removed.');
    $ai = idx('activities', $d['reports'][$ri]['targetId']);
    if ($ai < 0) return fail('Listing not found.');
    $a = $d['activities'][$ai];
    $d['activities'][$ai]['status'] = 'cancelled';
    $d['activities'][$ai]['removedByModerator'] = true;
    $d['activities'][$ai]['cancelReason'] = 'Removed by a moderator';
    $d['reports'][$ri]['status'] = 'resolved';
    $d['reports'][$ri]['resolution'] = 'Listing removed' . ($note !== '' ? " — $note" : '');
    notify($a['hostId'], 'moderation', "{$a['title']} was removed", $note !== '' ? $note : 'It did not follow the community guidelines.', "activity.php?id={$a['id']}");
    foreach (accepted_of($a['id']) as $p) {
        if ($p['memberId'] !== $a['hostId']) notify($p['memberId'], 'cancelled', "{$a['title']} is no longer available", 'A moderator removed this listing.', 'explore.php');
    }
    db_save();
    return ok();
}

function svc_set_suspended(string $userId, bool $suspended, ?string $reportId = null): array
{
    $i = idx('users', $userId);
    $d = &db();
    if ($i < 0 || $d['users'][$i]['kind'] !== 'member') return fail('Member not found.');
    $d['users'][$i]['suspended'] = $suspended;
    if ($reportId && ($ri = idx('reports', $reportId)) >= 0) {
        $d['reports'][$ri]['status'] = 'resolved';
        $d['reports'][$ri]['resolution'] = 'Account suspended: ' . $d['users'][$i]['name'];
    }
    db_save();
    return ok();
}

function svc_save_category(?string $id, string $name, string $icon): array
{
    $name = trim($name);
    if ($name === '') return fail('Give the category a name.');
    $d = &db();
    foreach ($d['categories'] as $c) {
        if (strcasecmp($c['name'], $name) === 0 && $c['id'] !== $id) return fail('A category with this name already exists.');
    }
    $icon = $icon !== '' ? mb_substr($icon, 0, 4) : '•';
    if ($id && ($i = idx('categories', $id)) >= 0) {
        $d['categories'][$i]['name'] = $name;
        $d['categories'][$i]['icon'] = $icon;
    } else {
        $d['categories'][] = ['id' => uid('c'), 'name' => $name, 'icon' => $icon];
    }
    db_save();
    return ok();
}

function svc_delete_category(string $id): array
{
    foreach (db()['activities'] as $a) {
        if ($a['categoryId'] === $id && activity_status($a) === 'published') return fail('This category still has upcoming activities. Move them first.');
    }
    $d = &db();
    $d['categories'] = array_values(array_filter($d['categories'], fn($c) => $c['id'] !== $id));
    db_save();
    return ok();
}
