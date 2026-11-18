<?php
/** Screen 7 — Member profile (FR-02, FR-03, FR-13, FR-15, FR-16). ?id=me shows your own editor. */
require __DIR__ . '/includes/bootstrap.php';

const INTEREST_OPTIONS = ['Outdoors', 'Local outings', 'House parties', 'Weird & Fun', 'Street art', 'Food', 'Music', 'Sports', 'Games', 'Art & museums'];

$me = me();
$id = (string)($_GET['id'] ?? 'me');
if ($id === 'me') {
    $me = require_member();
    $id = $me['id'];
}

// FR-02: save own profile
if (is_post() && $me && $me['id'] === $id) {
    csrf_check();
    $patch = [
        'bio' => mb_substr(post('bio'), 0, 400),
        'interests' => array_values(array_intersect(INTEREST_OPTIONS, (array)($_POST['interests'] ?? []))),
        'languages' => array_values(array_filter(array_map('trim', explode(',', post('languages'))))),
    ];
    $f = $_FILES['photo'] ?? null;
    if ($f && $f['error'] === UPLOAD_ERR_OK) {
        $info = @getimagesize($f['tmp_name']);
        $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
        if (!$ext || $f['size'] > 2 * 1024 * 1024) {
            flash('Profile saved, but the photo must be a JPG, PNG or WebP under 2 MB.', 'err');
        } else {
            $dir = APP_ROOT . '/assets/uploads';
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $name = 'avatar-' . bin2hex(random_bytes(6)) . '.' . $ext;
            if (move_uploaded_file($f['tmp_name'], "$dir/$name")) $patch['photo'] = "assets/uploads/$name";
        }
    }
    svc_update_profile($me['id'], $patch);
    flash('Profile saved.');
    redirect('profile.php', ['id' => 'me']);
}

$u = user_by_id($id);
if (!$u || $u['kind'] !== 'member') {
    http_response_code(404);
    page_header('Member not found', 'community.php');
    echo '<div class="container"><div class="card"><h1>Member not found</h1><a href="' . url('community.php') . '" class="btn btn-outline" style="margin-top:16px">Back to Community</a></div></div>';
    page_footer();
    exit;
}

$isMe = $me && $me['id'] === $u['id'];
$rep = reputation($u['id']);
$badge = badge_for($rep);
$blocked = $me && in_array($u['id'], $me['blocked'] ?? [], true);
$hosting = sort_by_start(array_filter(db()['activities'], fn($a) => $a['hostId'] === $u['id'] && is_upcoming($a)));
$canAct = $me && !$isMe && $me['kind'] === 'member';

page_header($u['name'], 'community.php');
?>
<div class="container">
  <?php crumbs([['Community', 'community.php'], [$isMe ? 'Your profile' : 'Public member profile', null]]); ?>
  <h1><?= $isMe ? 'This is how others see you' : 'A face from your neighbourhood' ?></h1>
  <p class="small muted" style="margin:6px 0 24px">Public profile view<?= $me ? ' · Signed in as ' . h($me['name']) . ($isMe ? '' : ', viewing ' . h($u['name'])) : '' ?>.</p>
  <div class="two-col">
    <div class="stack">
      <section class="card">
        <div class="profile-top">
          <?php avatar($u, 108); ?>
          <div>
            <h2><?= h($u['name']) ?></h2>
            <p class="muted" style="margin:6px 0 8px">Member · Joined <?= fmt_month($u['joinedAt']) ?></p>
            <span class="tag <?= $badge['amber'] ? 'amber' : '' ?>"><?= h($badge['label']) ?></span>
            <?php if (!empty($u['suspended'])): ?><span class="tag err" style="margin-left:6px">Suspended</span><?php endif; ?>
          </div>
        </div>
        <p style="margin:20px 0 14px;font-size:16px"><?= $u['bio'] !== '' ? h($u['bio']) : '<span class="muted">No bio yet.</span>' ?></p>
        <div class="row"><?php foreach ($u['interests'] ?? [] as $i => $int): ?><span class="tag <?= $i < 2 ? 'amber' : '' ?>"><?= h($int) ?></span><?php endforeach; ?></div>
        <p style="margin:14px 0">Languages · <?= h(implode(', ', $u['languages'] ?? []) ?: '—') ?></p>
        <?php if ($canAct): ?>
          <div class="row">
            <button type="button" class="btn btn-outline btn-sm" data-open="report-dialog">Report member</button>
            <?php if ($blocked): ?>
              <?= action_button('block', ['member' => $u['id']], 'Unblock member', 'btn btn-outline btn-sm') ?>
            <?php else: ?>
              <button type="button" class="btn btn-outline btn-sm" data-open="block-dialog">Block member</button>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if ($blocked): ?><p class="alert info small" style="margin-top:12px">You blocked <?= h($u['name']) ?>. You won’t see their activities, and they can’t join yours.</p><?php endif; ?>
      </section>

      <section class="card" aria-labelledby="rep-h">
        <div class="row between">
          <h2 id="rep-h">Peer reputation</h2>
          <span class="score-big"><?= $rep['score'] !== null ? number_format($rep['score'], 1) . ' / 5' : 'New' ?></span>
        </div>
        <p class="tiny muted" style="margin:6px 0 12px"><?= $rep['n'] ?> released <?= $rep['n'] === 1 ? 'rating' : 'ratings' ?> · Equal-weight mean of the three criteria below, rounded to one decimal.</p>
        <?php if ($rep['n'] > 0): foreach (CRITERIA as $c): $val = $rep[$c['key']]; ?>
          <div class="rep-row">
            <span><?= $c['label'] ?></span>
            <div class="bar" role="img" aria-label="<?= $c['label'] ?>: <?= $val ?> out of 5"><span style="width:<?= $val / 5 * 100 ?>%"></span></div>
            <strong><?= number_format($val, 1) ?></strong>
          </div>
        <?php endforeach; else: ?>
          <p class="muted small">No released ratings yet. Ratings appear after an activity once both people have rated each other.</p>
        <?php endif; ?>
        <span class="tag <?= $badge['amber'] ? 'amber' : '' ?>" style="margin-top:10px"><?= h($badge['label']) ?> · Reputation badge</span>
      </section>
    </div>

    <aside class="stack">
      <?php if ($isMe): ?>
        <form class="card form-grid" method="post" enctype="multipart/form-data" action="<?= url('profile.php', ['id' => 'me']) ?>" aria-labelledby="edit-h">
          <?= csrf_field() ?>
          <span class="tag" style="justify-self:start">Your profile editor</span>
          <div class="row" style="gap:12px">
            <?php avatar($u, 52); ?>
            <div><h3 id="edit-h"><?= h($u['name']) ?></h3><span class="tiny muted">Only visible to you</span></div>
          </div>
          <div class="field">
            <label for="photo">Change profile photo</label>
            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" class="input" style="padding:9px">
          </div>
          <div class="field">
            <label for="bio">Bio</label>
            <textarea id="bio" name="bio" class="textarea" rows="4" maxlength="400" placeholder="New in the city. I love early walks…" data-count="bio-count"><?= h($u['bio']) ?></textarea>
            <span class="hint" id="bio-count"><?= 400 - mb_strlen($u['bio']) ?> characters left</span>
          </div>
          <fieldset class="field" style="border:0;padding:0;margin:0">
            <legend class="label" style="margin-bottom:6px">Interests</legend>
            <div class="row">
              <?php foreach (INTEREST_OPTIONS as $int): ?>
                <label class="chip-check"><input type="checkbox" name="interests[]" value="<?= h($int) ?>" <?= in_array($int, $u['interests'] ?? [], true) ? 'checked' : '' ?>><span><?= h($int) ?></span></label>
              <?php endforeach; ?>
            </div>
          </fieldset>
          <div class="field">
            <label for="langs">Languages</label>
            <input id="langs" name="languages" class="input" value="<?= h(implode(', ', $u['languages'] ?? [])) ?>" placeholder="English, French">
            <span class="hint">Separate with commas</span>
          </div>
          <div><button class="btn btn-primary btn-lg" type="submit">Save profile changes</button></div>
        </form>
      <?php else: ?>
        <section class="card">
          <h3 style="margin-bottom:12px">Hosting next</h3>
          <?php if (!$hosting): ?><p class="muted small"><?= h(explode(' ', $u['name'])[0]) ?> isn’t hosting anything right now.</p><?php endif; ?>
          <?php foreach ($hosting as $a): ?>
            <div class="cal-item"><div class="info"><a href="<?= url('activity.php', ['id' => $a['id']]) ?>"><?= h($a['title']) ?></a><?= fmt_day($a['date']) ?> · <?= h($a['startTime']) ?></div></div>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>
    </aside>
  </div>
</div>
<?php
if ($canAct) {
    report_dialog('member', $u['id'], $u['name']);
    if (!$blocked) {
        $fn = explode(' ', $u['name'])[0];
        confirm_dialog('block-dialog', 'Block ' . $u['name'] . '?', "You won’t see activities $fn hosts, and they won’t be able to join yours. Pending requests from them are declined. They aren’t notified.",
            'block', ['member' => $u['id']], 'Block member', 'Cancel');
    }
}
page_footer();
