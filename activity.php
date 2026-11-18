<?php
/** Screen 5 — Activity detail (FR-06, FR-07, FR-08, FR-09, FR-15). Guest, member, participant and host views. */
require __DIR__ . '/includes/bootstrap.php';

$me = me();
$a = activity_by_id((string)($_GET['id'] ?? ''));

if (!$a) {
    http_response_code(404);
    page_header('Activity not found');
    echo '<div class="container"><div class="card"><h1>Activity not found</h1><p class="muted" style="margin:8px 0 16px">It may have been removed.</p><a class="btn btn-primary" href="' . url('explore.php') . '">Back to Explore</a></div></div>';
    page_footer();
    exit;
}

$cat = category_by_id($a['categoryId']);
$host = user_by_id($a['hostId']);
$status = activity_status($a);
$accepted = accepted_of($a['id']);
$left = places_left($a);
$meId = $me['id'] ?? null;
$mine = $meId ? participation_for($meId, $a['id']) : null;
$amHost = is_host($a, $meId);
$canChat = $mine && $mine['status'] === 'accepted';
$blocker = join_blocker($me, $a);
$isMember = $me && $me['kind'] === 'member';

page_header($a['title'], 'explore.php');
?>
<div class="container">
  <?php crumbs([['Explore', 'explore.php'], [$cat['name'], 'explore.php?cat=' . urlencode($cat['id'])], [$a['title'], null]]); ?>
  <div class="two-col">
    <div class="stack">
      <div>
        <span class="tag amber"><?= h($cat['name']) ?></span>
        <h1 style="margin-top:10px"><?= h($a['title']) ?></h1>
      </div>

      <?php if (!empty($a['photo'])): ?>
        <div class="hero-wrap" data-hero style="background-image:url('<?= h(base() . $a['photo']) ?>')">
          <img class="hero-img" src="<?= h(base() . $a['photo']) ?>" alt="Photo for <?= h($a['title']) ?>">
        </div>
      <?php else: activity_image($a, 'hero-img', 'font-size:80px'); endif; ?>

      <section class="card">
        <h3><?= h($a['tagline'] ?: 'About this plan') ?></h3>
        <p style="margin:12px 0 18px;white-space:pre-line"><?= h($a['description']) ?></p>
        <div class="host-row">
          <div class="who">
            <?php avatar($host, 44); ?>
            <div>
              <strong style="color:var(--crimson-dark)">Hosted by <?= h($host['name']) ?></strong>
              <div class="tiny muted">Reputation: <?= rep_text($host['id']) ?> / 5</div>
            </div>
          </div>
          <a href="<?= url('profile.php', ['id' => $host['id']]) ?>" class="link-btn">View profile →</a>
        </div>
      </section>

      <section class="card" aria-labelledby="going">
        <div class="card-head"><h3 id="going"><?= count($accepted) ?> going</h3><span class="tiny muted">Host included</span></div>
        <div class="avatar-stack">
          <?php foreach (array_slice($accepted, 0, 5) as $p): $u = user_by_id($p['memberId']); ?>
            <a href="<?= url('profile.php', ['id' => $u['id']]) ?>"><?php avatar($u, 46); ?><span><?= $u['id'] === $meId ? 'You' : h(explode(' ', $u['name'])[0]) ?></span></a>
          <?php endforeach; ?>
          <?php if (count($accepted) > 5): $more = count($accepted) - 5; ?>
            <span class="muted" style="display:grid;justify-items:center;gap:4px;font-size:13px"><span class="more-bubble">+<?= $more ?></span>+<?= $more ?></span>
          <?php endif; ?>
        </div>
      </section>

      <?php if ($amHost && $status === 'published'): ?>
        <section class="card">
          <div class="card-head"><h3>Pending join requests</h3><span class="tag"><?= count(pending_of($a['id'])) ?> waiting</span></div>
          <?php request_list($a); ?>
        </section>
      <?php endif; ?>

      <?php if ($isMember && !$amHost): ?>
        <p class="small"><button type="button" class="link-btn" data-open="report-dialog">Report this activity</button></p>
      <?php endif; ?>
    </div>

    <aside class="stack">
      <section class="card" aria-label="Joining">
        <?php if ($status === 'published'): ?>
          <span class="tag amber"><?= $left === 0 ? 'Full' : $left . ' ' . ($left === 1 ? 'place' : 'places') . ' available' ?></span>
        <?php endif; ?>
        <h2 style="margin:14px 0 12px"><?= $status === 'published' ? 'Make room for a new face' : ($status === 'completed' ? 'This plan happened' : 'Cancelled') ?></h2>
        <div class="facts">
          <p class="big"><?= fmt_long($a['date']) ?></p>
          <p class="muted"><?= fmt_range($a) ?> · Local time</p>
          <hr class="divider" style="margin:4px 0">
          <p><?= h(place_name($a['placeId'])) ?> · <?= h($a['meetingPoint']) ?></p>
          <p><?= count($accepted) ?> / <?= (int)$a['capacity'] ?> accepted · Includes host</p>
          <div class="row">
            <?= policy_tag($a['joinPolicy']) ?>
            <?php if ($a['minReputation'] !== null): ?><span class="tag">Min. reputation <?= number_format((float)$a['minReputation'], 1) ?> ★</span><?php endif; ?>
          </div>
        </div>
        <div style="margin-top:18px">
          <?php if ($status === 'cancelled'): ?>
            <div class="alert err"><?= $a['removedByModerator'] ? 'This listing was removed by a moderator.' : 'The host cancelled this activity.' . ($a['cancelReason'] !== '' ? ' Note: “' . h($a['cancelReason']) . '”' : '') ?></div>
          <?php elseif ($status === 'completed'): [$canRate] = $meId ? rating_eligibility($meId, $a) : [false]; ?>
            <div class="alert info">This activity has ended.</div>
            <?php if ($canRate): ?>
              <a href="<?= url('rate.php', ['id' => $a['id']]) ?>" class="btn btn-primary" style="margin-top:12px">Rate participants</a>
              <p class="tiny muted" style="margin-top:8px">Feedback window closes <?= fmt_when(rating_window_end($a)) ?>.</p>
            <?php endif; ?>
          <?php elseif ($amHost): ?>
            <div class="row">
              <a href="<?= url('activity-form.php', ['id' => $a['id']]) ?>" class="btn btn-primary">Edit activity</a>
              <button type="button" class="btn btn-outline" data-open="cancel-dialog">Cancel activity</button>
            </div>
            <p class="tiny muted" style="margin-top:10px">You are the host. Participants are notified of any change.</p>
          <?php elseif ($mine && $mine['status'] === 'accepted'): ?>
            <div class="alert ok">You’re going. See you there!</div>
            <button type="button" class="btn btn-outline" style="margin-top:12px" data-open="leave-dialog">Leave activity</button>
          <?php elseif ($mine && $mine['status'] === 'pending'): ?>
            <div class="alert info">Request sent · waiting for <?= h(explode(' ', $host['name'])[0]) ?> to reply.</div>
            <button type="button" class="btn btn-outline" style="margin-top:12px" data-open="leave-dialog">Withdraw request</button>
          <?php elseif (!$me): ?>
            <a class="btn btn-primary" href="<?= url('login.php', ['next' => 'activity.php?id=' . $a['id']]) ?>">Log in to request to join →</a>
            <p class="tiny muted" style="margin-top:10px">Guest view. After login, send a request; your place is confirmed <?= $a['joinPolicy'] === 'open' ? 'right away if a place is free' : 'only when the host accepts' ?>.</p>
          <?php else: ?>
            <?= action_button('join', ['activity' => $a['id']], $a['joinPolicy'] === 'open' ? 'Join activity →' : 'Request to join →', 'btn btn-primary', $blocker ? 'disabled aria-describedby="join-why"' : '') ?>
            <p id="join-why" class="tiny muted" style="margin-top:10px">
              <?= h($blocker ?? ($a['joinPolicy'] === 'open' ? 'Open joining: your place is confirmed immediately.' : 'Your place is confirmed only when the host accepts.')) ?>
            </p>
          <?php endif; ?>
        </div>
      </section>

      <?php if (!$canChat): ?>
        <section class="card" aria-labelledby="chat-h">
          <h3 id="chat-h" class="row" style="gap:8px"><span aria-hidden="true">🔒</span> Activity group chat</h3>
          <span class="tag" style="margin:12px 0">Locked · Participants only</span>
          <p>Chat opens after your request is accepted. Only accepted participants can read or send messages.</p>
        </section>
      <?php else:
          $msgs = array_values(array_filter(db()['messages'], fn($m) => $m['activityId'] === $a['id']));
          usort($msgs, fn($x, $y) => strcmp($x['sentAt'], $y['sentAt'])); ?>
        <section class="card" aria-labelledby="chat-h" id="chat">
          <div class="card-head"><h3 id="chat-h">Activity group chat</h3><span class="tag ok">Participants only</span></div>
          <div class="chat-log" data-chat-log>
            <?php if (!$msgs): ?><p class="muted small">No messages yet. Say hi 👋</p><?php endif; ?>
            <?php foreach ($msgs as $m): $u = user_by_id($m['memberId']); $mineMsg = $m['memberId'] === $meId; ?>
              <div class="msg <?= $mineMsg ? 'mine' : '' ?>">
                <?php avatar($u, 28); ?>
                <div>
                  <div class="meta" style="text-align:<?= $mineMsg ? 'right' : 'left' ?>"><?= $mineMsg ? 'You' : h($u['name']) ?><?= $u['id'] === $a['hostId'] ? ' · Host' : '' ?> · <?= fmt_when($m['sentAt']) ?></div>
                  <div class="bubble"><?= h($m['content']) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ($status !== 'cancelled'): ?>
            <form class="chat-form" method="post" action="<?= url('action.php') ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="do" value="message">
              <input type="hidden" name="activity" value="<?= h($a['id']) ?>">
              <input type="hidden" name="back" value="activity.php?id=<?= h($a['id']) ?>#chat">
              <label for="chat-input" class="sr-only">Message</label>
              <input id="chat-input" name="content" class="input" placeholder="Write a message…" maxlength="1000" autocomplete="off" required>
              <button class="btn btn-primary" type="submit">Send</button>
            </form>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </aside>
  </div>
</div>
<?php
if ($amHost && $status === 'published') {
    $affected = count($accepted) - 1 + count(pending_of($a['id']));
    confirm_dialog('cancel-dialog', 'Cancel this activity?', "Everyone who joined or requested a place ($affected) will be notified. This can’t be undone.",
        'cancel', ['activity' => $a['id']], 'Cancel activity', 'Keep it', 'Note for participants (optional)');
}
if ($mine && !$amHost) {
    $pending = $mine['status'] === 'pending';
    confirm_dialog('leave-dialog', $pending ? 'Withdraw your request?' : 'Leave this activity?',
        $pending ? 'The host won’t see your request any more.' : 'Your place will open up for someone else and the host will be notified.',
        'leave', ['activity' => $a['id']], $pending ? 'Withdraw' : 'Leave activity', 'Stay');
}
if ($isMember && !$amHost) report_dialog('activity', $a['id'], $a['title']);
page_footer();
