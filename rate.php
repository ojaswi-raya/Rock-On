<?php
/** Screen 6 — Rate participants (FR-11, FR-12, NFR-02, NFR-03). */
require __DIR__ . '/includes/bootstrap.php';

$me = require_member();
$a = activity_by_id((string)($_GET['id'] ?? ''));
if (!$a) { flash('Activity not found.', 'err'); redirect('dashboard.php'); }

$error = null;
$draft = ['punctuality' => 0, 'respect' => 0, 'fun' => 0, 'comment' => ''];

if (is_post()) {
    csrf_check();
    $ratedId = post('rated');
    $draft = ['punctuality' => (int)post('punctuality', '0'), 'respect' => (int)post('respect', '0'), 'fun' => (int)post('fun', '0'), 'comment' => post('comment')];
    $r = svc_submit_rating($me['id'], $a['id'], $ratedId, $draft, $draft['comment']);
    if ($r['ok']) {
        $first = explode(' ', user_by_id($ratedId)['name'])[0];
        flash($r['released'] ? "Rating released — you and $first have both rated each other." : "Rating saved. It stays hidden until $first rates you too.");
        // Go to the next person still to rate
        $next = null;
        foreach (attendees_to_rate($me['id'], $a) as $x) if (!$x['rating']) { $next = $x['member']['id']; break; }
        redirect('rate.php', ['id' => $a['id'], 'member' => $next ?? $ratedId]);
    }
    $error = $r['error'];
    $_GET['member'] = $ratedId;
}

[$eligible, $why] = rating_eligibility($me['id'], $a);
page_header('Rate participants', 'dashboard.php');

if (!$eligible): ?>
<div class="container">
  <div class="card" style="max-width:640px">
    <h1>How was the company?</h1>
    <p class="alert info" style="margin:16px 0"><?= h($why) ?></p>
    <a href="<?= url('activity.php', ['id' => $a['id']]) ?>" class="btn btn-outline">Back to the activity</a>
  </div>
</div>
<?php page_footer(); exit; endif;

$people = attendees_to_rate($me['id'], $a);
$selId = (string)($_GET['member'] ?? '');
$current = null;
foreach ($people as $x) if ($x['member']['id'] === $selId) $current = $x;
if (!$current) { foreach ($people as $x) if (!$x['rating']) { $current = $x; break; } }
$current = $current ?? ($people[0] ?? null);
$first = $current ? explode(' ', $current['member']['name'])[0] : '';
$anyLeft = (bool)array_filter($people, fn($x) => !$x['rating']);
?>
<div class="container">
  <?php crumbs([['Dashboard', 'dashboard.php'], ['Completed activities', null], ['Give feedback', null]]); ?>
  <h1>How was the company?</h1>
  <p class="muted" style="margin:6px 0 24px"><?= h($a['title']) ?> · Completed <?= fmt_long($a['date']) ?>, <?= h($a['endTime']) ?> · <?= h(place_name($a['placeId'])) ?></p>
  <div class="two-col narrow-left">
    <aside class="stack">
      <section class="card">
        <h3 style="margin-bottom:6px">Your fellow attendees</h3>
        <p class="small muted" style="margin-bottom:12px">Select a participant. You cannot rate yourself.</p>
        <?php foreach ($people as $x): $m = $x['member']; $sel = $current && $m['id'] === $current['member']['id']; ?>
          <a class="attendee" href="<?= url('rate.php', ['id' => $a['id'], 'member' => $m['id']]) ?>" <?= $sel ? 'aria-current="true"' : '' ?>>
            <?php avatar($m, 36); ?>
            <div>
              <strong><?= h($m['name']) ?></strong>
              <span><?= $sel ? 'Selected · ' : '' ?><?= $x['rating'] ? ($x['rating']['isReleased'] ? 'Submitted · Released' : 'Submitted · Hidden') : 'Not submitted' ?></span>
            </div>
          </a>
        <?php endforeach; ?>
      </section>
      <div class="alert info small" style="border-radius:var(--radius);padding:16px 20px;font-weight:600">
        Only attendees can rate, within 7 days (until <?= fmt_when(rating_window_end($a)) ?>). Your rating stays hidden until you both rate each other.
      </div>
    </aside>

    <?php if ($current): ?>
      <section class="card" aria-labelledby="rate-h">
        <div class="row between">
          <div class="row" style="gap:14px">
            <?php avatar($current['member'], 52); ?>
            <div>
              <h2 id="rate-h">Rate <?= h($current['member']['name']) ?></h2>
              <p class="small muted">Think about this activity, not their identity or background.</p>
            </div>
          </div>
          <span class="tag"><?= $current['rating'] ? ($current['rating']['isReleased'] ? 'Released' : 'Submitted') : 'Draft' ?></span>
        </div>
        <hr class="divider">
        <?php if ($current['rating']): $r = $current['rating']; ?>
          <div class="stack">
            <p class="alert ok">
              You rated <?= h($first) ?>: punctuality <?= (int)$r['punctuality'] ?>, respect <?= (int)$r['respect'] ?>, fun <?= (int)$r['fun'] ?>.
              <?= $r['isReleased'] ? 'Both ratings are now released and count towards reputation.' : 'It stays hidden until ' . h($first) . ' also rates you.' ?>
            </p>
            <?php if ($anyLeft): ?><p class="muted">Pick someone else from the list.</p>
            <?php else: ?><a href="<?= url('dashboard.php') ?>" class="btn btn-primary" style="justify-self:start">All done · Back to dashboard</a><?php endif; ?>
          </div>
        <?php else: ?>
          <form method="post" class="form-grid" novalidate data-rating-form>
            <?= csrf_field() ?>
            <input type="hidden" name="rated" value="<?= h($current['member']['id']) ?>">
            <?php foreach (CRITERIA as $c): $val = (int)$draft[$c['key']]; ?>
              <fieldset class="criterion" style="border:0;padding:4px 0 8px;margin:0">
                <div class="top"><legend class="label" id="lbl-<?= $c['key'] ?>"><?= $c['label'] ?></legend><b data-scale-out><?= $val ? "$val / 5" : '– / 5' ?></b></div>
                <div class="scale radio-scale" aria-describedby="hint-<?= $c['key'] ?>">
                  <?php for ($n = 1; $n <= 5; $n++): ?>
                    <label><input type="radio" name="<?= $c['key'] ?>" value="<?= $n ?>" <?= $val === $n ? 'checked' : '' ?>><span><?= $n ?></span></label>
                  <?php endfor; ?>
                </div>
                <p class="tiny muted" id="hint-<?= $c['key'] ?>" style="margin-top:6px">1 = <?= $c['low'] ?> · 5 = <?= $c['high'] ?></p>
              </fieldset>
            <?php endforeach; ?>
            <div class="field">
              <label for="comment">Comment (optional)</label>
              <textarea id="comment" name="comment" class="textarea" rows="4" maxlength="500" aria-describedby="comment-hint" placeholder="<?= h($first) ?> was on time and helped everyone feel included."><?= h($draft['comment']) ?></textarea>
              <span class="hint" id="comment-hint">Keep feedback relevant and respectful. Don’t share private information.</span>
            </div>
            <?php if ($error): ?><p class="alert err" role="alert"><?= h($error) ?></p><?php endif; ?>
            <div class="row">
              <button class="btn btn-primary btn-lg" type="submit">Submit rating</button>
              <span class="small muted">Hidden until <?= h($first) ?> also rates you.</span>
            </div>
          </form>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </div>
</div>
<?php page_footer();
