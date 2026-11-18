<?php
/** Screen 3 — Member dashboard (FR-07, FR-08, FR-18): Hosting / Joined tabs, join requests, notifications, calendar. */
require __DIR__ . '/includes/bootstrap.php';

$me = require_member();
$tab = ($_GET['tab'] ?? 'hosting') === 'joined' ? 'joined' : 'hosting';

$hosting = sort_by_start(array_filter(db()['activities'], fn($a) => $a['hostId'] === $me['id'] && activity_status($a) === 'published'));
$past = array_filter(db()['activities'], fn($a) => $a['hostId'] === $me['id'] && activity_status($a) !== 'published');
usort($past, fn($x, $y) => start_of($y) <=> start_of($x));
$past = array_slice($past, 0, 3);

$joined = [];
foreach (db()['participations'] as $p) {
    if ($p['memberId'] !== $me['id'] || !in_array($p['status'], ['accepted', 'pending'], true)) continue;
    $a = activity_by_id($p['activityId']);
    if ($a && $a['hostId'] !== $me['id'] && activity_status($a) === 'published') $joined[] = ['a' => $a, 'p' => $p];
}
usort($joined, fn($x, $y) => start_of($x['a']) <=> start_of($y['a']));
$joinedAccepted = array_values(array_filter($joined, fn($j) => $j['p']['status'] === 'accepted'));

$toRate = pending_rating_activities($me['id']);
$notifs = array_slice(notifications_for($me['id']), 0, 3);
$unread = unread_count($me['id']);
$waiting = array_sum(array_map(fn($a) => count(pending_of($a['id'])), $hosting));

function hosted_card(array $a, array $me): void
{
    $status = activity_status($a);
    $pending = count(pending_of($a['id']));
    ?>
<article class="card">
  <div class="hosted">
    <?php activity_image($a, 'thumb'); ?>
    <div>
      <div class="row">
        <span class="tag amber"><?= h(category_by_id($a['categoryId'])['name']) ?></span>
        <?= policy_tag($a['joinPolicy']) ?>
        <?php if ($status !== 'published'): ?><span class="tag <?= $status === 'cancelled' ? 'err' : '' ?>"><?= $status === 'cancelled' ? 'Cancelled' : 'Completed' ?></span><?php endif; ?>
        <?php if ($status === 'published' && $pending): ?><span class="tag crimson"><?= $pending ?> waiting</span><?php endif; ?>
      </div>
      <h3><a href="<?= url('activity.php', ['id' => $a['id']]) ?>"><?= h($a['title']) ?></a></h3>
      <p class="muted"><?= h(place_name($a['placeId'])) ?> · <?= fmt_day($a['date']) ?> · <?= fmt_range($a) ?></p>
      <p class="muted" style="margin-bottom:12px"><?= going_count($a) ?> / <?= (int)$a['capacity'] ?> going, including you as host</p>
      <div class="row">
        <a href="<?= url('activity.php', ['id' => $a['id']]) ?>" class="btn btn-primary btn-sm">View activity</a>
        <?php if ($status === 'published'): ?>
          <a href="<?= url('activity-form.php', ['id' => $a['id']]) ?>" class="btn btn-outline btn-sm">Edit</a>
          <button type="button" class="btn btn-outline btn-sm" data-open="cancel-<?= h($a['id']) ?>">Cancel</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</article>
<?php
    if ($status === 'published') {
        confirm_dialog('cancel-' . $a['id'], 'Cancel “' . $a['title'] . '”?', 'Everyone who joined or requested a place will be notified. This can’t be undone.',
            'cancel', ['activity' => $a['id']], 'Cancel activity', 'Keep it', 'Note for participants (optional)');
    }
}

page_header('Dashboard', 'dashboard.php');
?>
<div class="container">
  <div class="two-col">
    <div>
      <div class="page-head" style="margin-bottom:0">
        <div>
          <h1>Your plans, <?= h(explode(' ', $me['name'])[0]) ?></h1>
          <p><?= fmt_long(new DateTimeImmutable()) ?> · A little community for your week.</p>
        </div>
        <a href="<?= url('activity-form.php') ?>" class="btn btn-primary">+ Create Activity</a>
      </div>
      <div class="tabs" role="tablist" aria-label="Your activities">
        <a role="tab" class="tab" href="<?= url('dashboard.php', ['tab' => 'hosting']) ?>" aria-selected="<?= $tab === 'hosting' ? 'true' : 'false' ?>">Hosting · <?= count($hosting) ?></a>
        <a role="tab" class="tab" href="<?= url('dashboard.php', ['tab' => 'joined']) ?>" aria-selected="<?= $tab === 'joined' ? 'true' : 'false' ?>">Joined · <?= count($joined) ?></a>
      </div>

      <?php if ($tab === 'hosting'): ?>
        <div role="tabpanel" class="stack">
          <?php if (!$hosting): ?>
            <div class="card"><?php empty_state('You’re not hosting anything yet', 'A walk, a picnic, a board game afternoon — small plans work best.', '<a href="' . url('activity-form.php') . '" class="btn btn-primary">Create an activity</a>'); ?></div>
          <?php endif; ?>
          <?php foreach ($hosting as $a) hosted_card($a, $me); ?>
          <?php if ($hosting): ?>
            <section class="card" aria-labelledby="pjr">
              <div class="card-head"><h2 id="pjr" style="font-size:22px">Pending join requests</h2><span class="tag"><?= $waiting ?> waiting</span></div>
              <?php foreach ($hosting as $a): if (!pending_of($a['id'])) continue; ?>
                <div style="margin-bottom:12px">
                  <?php if (count($hosting) > 1): ?><p class="small" style="font-weight:700;color:var(--crimson-ink);margin-bottom:4px"><?= h($a['title']) ?></p><?php endif; ?>
                  <?php request_list($a); ?>
                </div>
              <?php endforeach; ?>
              <?php if (!$waiting): ?><p class="muted small">No requests waiting. New ones appear here and in your notifications.</p><?php endif; ?>
            </section>
          <?php endif; ?>
          <?php if ($past): ?>
            <details class="card">
              <summary style="cursor:pointer;font-weight:700">Past &amp; cancelled (<?= count($past) ?>)</summary>
              <div class="stack" style="margin-top:16px"><?php foreach ($past as $a) hosted_card($a, $me); ?></div>
            </details>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div role="tabpanel" class="stack">
          <?php if (!$joined): ?>
            <div class="card"><?php empty_state('No joined plans yet', 'Find something nearby this week.', '<a href="' . url('explore.php') . '" class="btn btn-primary">Explore activities</a>'); ?></div>
          <?php endif; ?>
          <?php foreach ($joined as ['a' => $a, 'p' => $p]): ?>
            <article class="card">
              <div class="hosted">
                <?php activity_image($a, 'thumb'); ?>
                <div>
                  <div class="row">
                    <span class="tag amber"><?= h(category_by_id($a['categoryId'])['name']) ?></span>
                    <span class="tag <?= $p['status'] === 'accepted' ? 'ok' : '' ?>"><?= $p['status'] === 'accepted' ? 'Joined' : 'Request pending' ?></span>
                  </div>
                  <h3><a href="<?= url('activity.php', ['id' => $a['id']]) ?>"><?= h($a['title']) ?></a></h3>
                  <p class="muted"><?= h(place_name($a['placeId'])) ?> · <?= fmt_day($a['date']) ?> · <?= fmt_range($a) ?></p>
                  <p class="muted" style="margin-bottom:12px">Hosted by <?= h(user_by_id($a['hostId'])['name']) ?> · <?= going_count($a) ?> / <?= (int)$a['capacity'] ?> going</p>
                  <a href="<?= url('activity.php', ['id' => $a['id']]) ?>" class="btn btn-primary btn-sm"><?= $p['status'] === 'accepted' ? 'Open chat & details' : 'View activity' ?></a>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <aside class="stack">
      <section class="card" aria-labelledby="nh">
        <div class="card-head"><h2 id="nh" style="font-size:22px">Notifications</h2><?php if ($unread): ?><span class="tag amber"><?= $unread ?> new</span><?php endif; ?></div>
        <?php if (!$notifs): ?><p class="muted small">You’re all caught up.</p><?php endif; ?>
        <?php foreach ($notifs as $n): ?>
          <form method="post" action="<?= url('action.php') ?>">
            <?= csrf_field() ?><input type="hidden" name="do" value="read"><input type="hidden" name="notification" value="<?= h($n['id']) ?>"><input type="hidden" name="link" value="<?= h($n['link']) ?>">
            <button type="submit" class="notif notif-btn <?= $n['isRead'] ? '' : 'unread' ?>">
              <strong><?= h($n['content']) ?></strong><p><?= h($n['detail']) ?></p><span class="tiny muted"><?= fmt_when($n['at']) ?></span>
            </button>
          </form>
        <?php endforeach; ?>
        <a href="<?= url('notifications.php') ?>" class="link-btn" style="display:inline-block;margin-top:12px">View all notifications →</a>
      </section>
      <section class="card" aria-labelledby="ch">
        <h2 id="ch" style="font-size:22px;margin-bottom:8px">Also on your calendar</h2>
        <?php foreach (array_slice($joinedAccepted, 0, 3) as ['a' => $a]): ?>
          <div class="cal-item">
            <?php activity_image($a); ?>
            <div class="info"><a href="<?= url('activity.php', ['id' => $a['id']]) ?>"><?= h($a['title']) ?></a><?= fmt_day($a['date']) ?> · <?= fmt_range($a) ?> · <?= h(place_name($a['placeId'])) ?><br>Accepted · Participant chat available</div>
            <span class="tag amber">Joined</span>
          </div>
        <?php endforeach; ?>
        <?php foreach ($toRate as $a): ?>
          <div class="cal-item">
            <div class="info"><a href="<?= url('activity.php', ['id' => $a['id']]) ?>"><?= h($a['title']) ?> · Completed</a>You attended. Give feedback by <?= fmt_when(rating_window_end($a)) ?>.</div>
            <a href="<?= url('rate.php', ['id' => $a['id']]) ?>" class="btn btn-primary btn-sm">Rate participants</a>
          </div>
        <?php endforeach; ?>
        <?php if (!$joinedAccepted && !$toRate): ?><p class="muted small">Nothing else this week.</p><?php endif; ?>
      </section>
    </aside>
  </div>
</div>
<?php page_footer();
