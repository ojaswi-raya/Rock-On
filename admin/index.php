<?php
/** Screen 8 — Admin moderation (FR-17): report queue, evidence, remove / suspend / close with confirmation. */
require __DIR__ . '/../includes/bootstrap.php';

$me = require_admin();
$target = (string)($_GET['target'] ?? 'all');
$order = ($_GET['order'] ?? 'newest') === 'oldest' ? 'oldest' : 'newest';
$showResolved = !empty($_GET['resolved']);
$confirm = (string)($_GET['confirm'] ?? '');

$reports = array_filter(db()['reports'], fn($r) => ($showResolved || $r['status'] !== 'resolved') && ($target === 'all' || $r['targetType'] === $target));
usort($reports, fn($a, $b) => $order === 'newest' ? strcmp($b['date'], $a['date']) : strcmp($a['date'], $b['date']));
$openCount = count(array_filter(db()['reports'], fn($r) => $r['status'] !== 'resolved'));

$sel = null;
if (!empty($_GET['report'])) foreach (db()['reports'] as $r) if ($r['id'] === $_GET['report']) $sel = $r;
$sel = $sel ?? ($reports[0] ?? null);
// Opening a report moves it from "open" to "in review".
if ($sel && $sel['status'] === 'open' && !empty($_GET['report'])) { svc_set_report_status($sel['id'], 'inReview'); $sel['status'] = 'inReview'; }

$STATUS = ['open' => 'Open', 'inReview' => 'In review', 'resolved' => 'Resolved'];
$targetName = fn($r) => ($r['targetType'] === 'activity' ? (activity_by_id($r['targetId'])['title'] ?? null) : (user_by_id($r['targetId'])['name'] ?? null)) ?? 'Unknown';
$qs = fn(array $c) => url('admin/index.php', array_filter(array_merge(['target' => $target, 'order' => $order, 'resolved' => $showResolved ? '1' : '', 'report' => $sel['id'] ?? ''], $c), fn($v) => $v !== '' && $v !== 'all'));

page_header('Report queue', 'admin/index.php');
?>
<div class="container">
  <div class="page-head">
    <div><h1>Review with care</h1><p class="small">Reports are allegations. Check context before taking action.</p></div>
    <span class="tag">Administrator · <?= h($me['name']) ?></span>
  </div>
  <div class="two-col even">
    <div class="stack">
      <section class="card" aria-labelledby="q-h">
        <div class="card-head"><h2 id="q-h">Report queue</h2><span class="tag amber"><?= $openCount ?> open</span></div>
        <form class="row" method="get" style="margin-bottom:16px" data-autosubmit>
          <label class="sr-only" for="tf">Target</label>
          <select id="tf" name="target" class="pill-select">
            <option value="all">All targets</option>
            <option value="activity" <?= $target === 'activity' ? 'selected' : '' ?>>Activities</option>
            <option value="member" <?= $target === 'member' ? 'selected' : '' ?>>Members</option>
          </select>
          <label class="sr-only" for="of">Order</label>
          <select id="of" name="order" class="pill-select">
            <option value="newest">Newest first</option><option value="oldest" <?= $order === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
          </select>
          <label class="check small"><input type="checkbox" name="resolved" value="1" <?= $showResolved ? 'checked' : '' ?>> Show resolved</label>
          <noscript><button class="btn btn-outline btn-sm">Apply</button></noscript>
        </form>
        <div class="queue-head" aria-hidden="true"><span>Target / reason</span><span>Reported</span><span>Status</span></div>
        <?php if (!$reports) empty_state('Queue is clear', 'No reports match these filters.'); ?>
        <?php foreach ($reports as $r): ?>
          <a class="queue-row" href="<?= $qs(['report' => $r['id']]) ?>" <?= $sel && $sel['id'] === $r['id'] ? 'aria-current="true"' : '' ?>>
            <span><strong><?= h($targetName($r)) ?></strong><br><span class="type"><?= $r['targetType'] === 'activity' ? 'Activity' : 'Member' ?></span><br><span class="muted"><?= h($r['reason']) ?></span></span>
            <span><?= (new DateTimeImmutable($r['date']))->format('j M Y') ?></span>
            <span><span class="tag <?= $r['status'] === 'resolved' ? 'ok' : '' ?>"><?= $STATUS[$r['status']] ?></span></span>
          </a>
        <?php endforeach; ?>
      </section>
      <section class="card">
        <h2 style="margin-bottom:8px">Categories management</h2>
        <p class="muted" style="margin-bottom:14px">Review names and icons, including Outdoors and Weird &amp; Fun.</p>
        <a href="<?= url('admin/categories.php') ?>" class="btn btn-outline btn-sm">Manage categories →</a>
      </section>
    </div>

    <div>
      <?php if (!$sel): ?>
        <div class="card"><?php empty_state('Nothing selected', 'Choose a report from the queue.'); ?></div>
      <?php else:
        $act = $sel['targetType'] === 'activity' ? activity_by_id($sel['targetId']) : null;
        $member = $sel['targetType'] === 'member' ? user_by_id($sel['targetId']) : ($act ? user_by_id($act['hostId']) : null);
        $reporter = user_by_id($sel['reporterId']);
        $done = $sel['status'] === 'resolved'; ?>
        <section class="card" aria-labelledby="sel-h">
          <span class="tag">Selected report · <?= h($sel['id']) ?></span>
          <h2 id="sel-h" style="margin:14px 0 8px;font-size:28px"><?= h($targetName($sel)) ?></h2>
          <p class="small muted">
            <?= $sel['targetType'] === 'activity' ? 'Activity target · Host: ' . h($member['name'] ?? '?') : 'Member target · ' . h(rep_text($member['id'])) ?>
            <?= !empty($member['suspended']) ? ' · Suspended' : '' ?><?= $act && activity_status($act) === 'cancelled' ? ' · Listing hidden' : '' ?>
          </p>
          <p class="small muted">Reported by <?= h($reporter['name'] ?? '?') ?> · <?= fmt_when($sel['date']) ?></p>
          <hr class="divider">
          <h3 style="margin-bottom:8px">Reason &amp; evidence summary</h3>
          <p><strong><?= h($sel['reason']) ?>.</strong> <?= h($sel['summary']) ?></p>
          <?php if ($sel['excerpt']): ?><blockquote class="quote" style="margin:14px 0 0">“<?= h($sel['excerpt']) ?>”</blockquote><?php endif; ?>
          <div class="row" style="margin-top:12px">
            <?php if ($act): ?><a class="link-btn" href="<?= url('activity.php', ['id' => $act['id']]) ?>">Open listing →</a><?php endif; ?>
            <?php if ($member): ?><a class="link-btn" href="<?= url('profile.php', ['id' => $member['id']]) ?>">Open <?= $sel['targetType'] === 'activity' ? 'host' : 'member' ?> profile →</a><?php endif; ?>
          </div>

          <h3 style="margin:20px 0 10px">Moderation actions</h3>
          <?php if ($done): ?>
            <p class="alert ok">Resolved · <?= h($sel['resolution']) ?></p>
          <?php else: ?>
            <div class="row">
              <?php if ($act && activity_status($act) !== 'cancelled'): ?><a class="btn btn-primary btn-sm" href="<?= $qs(['confirm' => 'remove']) ?>#confirm">Remove content</a><?php endif; ?>
              <?php if ($member && empty($member['suspended'])): ?><a class="btn btn-outline btn-sm" href="<?= $qs(['confirm' => 'suspend']) ?>#confirm">Suspend account</a><?php endif; ?>
              <a class="btn btn-outline btn-sm" href="<?= $qs(['confirm' => 'close']) ?>#confirm">Close with no action</a>
            </div>
            <?php if (in_array($confirm, ['remove', 'suspend', 'close'], true)): ?>
              <form class="confirm-box" id="confirm" method="post" action="<?= url('action.php') ?>" style="margin-top:14px" aria-label="Confirm action">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="<?= $confirm === 'close' ? 'close-report' : h($confirm) ?>">
                <input type="hidden" name="report" value="<?= h($sel['id']) ?>">
                <?php if ($member): ?><input type="hidden" name="member" value="<?= h($member['id']) ?>"><?php endif; ?>
                <input type="hidden" name="back" value="admin/index.php?report=<?= h($sel['id']) ?>&resolved=1">
                <p class="kicker">Confirmation</p>
                <h3 style="margin:6px 0"><?= $confirm === 'remove' ? 'Remove this activity listing?' : ($confirm === 'suspend' ? 'Suspend ' . h($member['name']) . '?' : 'Close this report with no action?') ?></h3>
                <p class="small">
                  <?php if ($confirm === 'remove'): ?>This hides <?= h($act['title']) ?>. Record a reason and notify affected members. It does not automatically suspend the host.
                  <?php elseif ($confirm === 'suspend'): ?><?= h($member['name']) ?> will be logged out and unable to sign in, host or join until reinstated. You can reverse this from Accounts.
                  <?php else: ?>The report is marked resolved. Nobody is notified.<?php endif; ?>
                </p>
                <?php if ($confirm === 'remove'): ?>
                  <div class="field" style="margin-top:10px">
                    <label for="mod-note">Reason (shared with the host)</label>
                    <input id="mod-note" name="reason" class="input" placeholder="e.g. Meeting location changed without notice">
                  </div>
                <?php endif; ?>
                <div class="row" style="margin-top:12px">
                  <button class="btn btn-primary btn-sm" type="submit"><?= $confirm === 'remove' ? 'Confirm removal' : ($confirm === 'suspend' ? 'Confirm suspension' : 'Close report') ?></button>
                  <a class="btn btn-outline btn-sm" href="<?= $qs(['confirm' => '']) ?>">Keep reviewing</a>
                </div>
              </form>
            <?php endif; ?>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php page_footer();
