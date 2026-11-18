<?php
/** Screen 4 — Create / edit activity (FR-04, FR-08, FR-10, FR-14). Live preview + host checklist. */
require __DIR__ . '/includes/bootstrap.php';

$me = require_member();
$id = isset($_GET['id']) ? (string)$_GET['id'] : null;
$existing = $id ? activity_by_id($id) : null;
if ($id) {
    if (!$existing || $existing['hostId'] !== $me['id']) { flash('Only the host can edit this activity.', 'err'); redirect('dashboard.php'); }
    if (activity_status($existing) !== 'published') { flash('This activity can no longer be edited.', 'err'); redirect('activity.php', ['id' => $id]); }
}

/** Validate and store an uploaded photo; returns [path|null, error|null]. */
function handle_upload(): array
{
    $f = $_FILES['photo'] ?? null;
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) return [null, null];
    if ($f['error'] !== UPLOAD_ERR_OK) return [null, 'The photo could not be uploaded. Try a smaller image.'];
    if ($f['size'] > 2.5 * 1024 * 1024) return [null, 'Choose an image under 2.5 MB.'];
    $info = @getimagesize($f['tmp_name']);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
    if (!$ext) return [null, 'Use a JPG, PNG or WebP image.'];
    $dir = APP_ROOT . '/assets/uploads';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) return [null, 'The photo could not be saved.'];
    return ["assets/uploads/$name", null];
}

$defaults = ['title' => '', 'categoryId' => '', 'tagline' => '', 'description' => '', 'date' => iso_day(3), 'startTime' => '18:00',
    'endTime' => '20:00', 'placeId' => '', 'meetingPoint' => '', 'capacity' => 8, 'minReputation' => null, 'joinPolicy' => 'hostApproval', 'photo' => null];
$v = $existing ? array_merge($defaults, $existing) : $defaults;
$errors = [];

if (is_post()) {
    csrf_check();
    [$uploaded, $uploadError] = handle_upload();
    $photo = $uploaded ?? (post('photoExisting') !== '' && preg_match('#^assets/(img|uploads)/[a-z0-9\-]+\.(jpg|png|webp)$#i', post('photoExisting')) ? post('photoExisting') : null);
    if (post('removePhoto') === '1') $photo = null;
    $cap = post('capacity');
    $minRep = post('minReputation');
    $v = [
        'title' => post('title'), 'categoryId' => post('categoryId'), 'tagline' => mb_substr(post('tagline'), 0, 70),
        'description' => mb_substr(post('description'), 0, 1200), 'date' => post('date'), 'startTime' => post('startTime'),
        'endTime' => post('endTime'), 'placeId' => post('placeId'), 'meetingPoint' => mb_substr(post('meetingPoint'), 0, 80),
        'capacity' => ctype_digit($cap) ? (int)$cap : -1,
        'minReputation' => $minRep === '' ? null : (is_numeric($minRep) ? round((float)$minRep, 1) : 0.0),
        'joinPolicy' => post('joinPolicy'), 'photo' => $photo,
    ];
    $r = svc_save_activity($me['id'], $v, $id);
    if ($r['ok'] && !$uploadError) {
        flash($id ? 'Changes saved. Participants were notified of any time or place change.' : 'Your activity is live!');
        redirect('activity.php', ['id' => $r['id']]);
    }
    if ($r['ok'] && $uploadError) {   // saved, but the photo failed
        flash('Saved, but ' . lcfirst($uploadError), 'err');
        redirect('activity-form.php', ['id' => $r['id']]);
    }
    $errors = $r['errors'] ?? [];
    if ($uploadError) $errors['photo'] = $uploadError;
    if ($v['capacity'] === -1) $v['capacity'] = $cap;
}

$going = $id ? going_count($existing) : 1;
$firstError = $errors ? 'f-' . array_key_first($errors) : null;
$val = fn($k) => h($v[$k] ?? '');

page_header($id ? 'Edit activity' : 'Create activity', 'dashboard.php');
?>
<div class="container">
  <?php crumbs([['Dashboard', 'dashboard.php'], [$id ? 'Edit activity' : 'Create activity', null]]); ?>
  <h1 style="margin-bottom:20px"><?= $id ? 'Update your plan.' : 'Make a little plan. Meet new people.' ?></h1>
  <?php if ($errors): ?><div class="alert err" role="alert" style="margin-bottom:16px">Please fix the <?= count($errors) ?> highlighted <?= count($errors) === 1 ? 'field' : 'fields' ?>.</div><?php endif; ?>
  <div class="two-col">
    <form class="card form-grid" method="post" enctype="multipart/form-data" novalidate aria-labelledby="ad" data-preview-form <?= $firstError ? 'data-focus="' . h($firstError) . '"' : '' ?>>
      <?= csrf_field() ?>
      <input type="hidden" name="photoExisting" value="<?= h($v['photo'] ?? '') ?>">
      <h2 id="ad">Activity details</h2>

      <div class="field">
        <label for="f-title">Title *</label>
        <input class="input" <?= field_attrs('title', $errors) ?> value="<?= $val('title') ?>" maxlength="80" placeholder="Blind, Mute and Deaf Game">
        <?= field_error('title', $errors) ?>
      </div>
      <div class="field">
        <label for="f-categoryId">Category *</label>
        <select class="select" <?= field_attrs('categoryId', $errors) ?>>
          <option value="">Choose a category…</option>
          <?php foreach (db()['categories'] as $c): ?><option value="<?= h($c['id']) ?>" <?= $v['categoryId'] === $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?>
        </select>
        <?= field_error('categoryId', $errors) ?>
      </div>
      <div class="field">
        <label for="f-tagline">Short tagline</label>
        <input class="input" <?= field_attrs('tagline', $errors, 'f-tagline-hint') ?> value="<?= $val('tagline') ?>" maxlength="70" placeholder="Let’s enjoy food with a beautiful view :)">
        <span class="hint" id="f-tagline-hint">Optional · shown above the description</span>
      </div>
      <div class="field">
        <label for="f-description">Description *</label>
        <textarea class="textarea" <?= field_attrs('description', $errors) ?> maxlength="1200" placeholder="What will you do? What should people bring? Anything to know about accessibility?"><?= $val('description') ?></textarea>
        <?= field_error('description', $errors) ?>
      </div>
      <div class="grid-3">
        <div class="field"><label for="f-date">Date *</label><input type="date" class="input" <?= field_attrs('date', $errors) ?> value="<?= $val('date') ?>" min="<?= iso_day(0) ?>"><?= field_error('date', $errors) ?></div>
        <div class="field"><label for="f-startTime">Start time *</label><input type="time" class="input" <?= field_attrs('startTime', $errors) ?> value="<?= $val('startTime') ?>"><?= field_error('startTime', $errors) ?></div>
        <div class="field"><label for="f-endTime">End time *</label><input type="time" class="input" <?= field_attrs('endTime', $errors) ?> value="<?= $val('endTime') ?>"><?= field_error('endTime', $errors) ?></div>
      </div>
      <div class="grid-2">
        <div class="field">
          <label for="f-placeId">Area *</label>
          <select class="select" <?= field_attrs('placeId', $errors) ?>>
            <option value="">Choose an area…</option>
            <?php foreach (PLACES as $pid => $p): ?><option value="<?= $pid ?>" <?= $v['placeId'] === $pid ? 'selected' : '' ?>><?= h($p['name']) ?></option><?php endforeach; ?>
          </select>
          <?= field_error('placeId', $errors) ?>
        </div>
        <div class="field"><label for="f-meetingPoint">Meeting point *</label><input class="input" <?= field_attrs('meetingPoint', $errors) ?> value="<?= $val('meetingPoint') ?>" maxlength="80" placeholder="Fountain entrance"><?= field_error('meetingPoint', $errors) ?></div>
      </div>
      <div class="grid-2">
        <div class="field">
          <label for="f-capacity">Capacity *</label>
          <input type="number" min="2" max="500" class="input" <?= field_attrs('capacity', $errors, 'f-capacity-hint') ?> value="<?= $val('capacity') ?>">
          <?= field_error('capacity', $errors) ?: '<span class="hint" id="f-capacity-hint">Includes the host</span>' ?>
        </div>
        <div class="field">
          <label for="f-minReputation">Min. reputation</label>
          <input type="number" step="0.1" min="1" max="5" class="input" <?= field_attrs('minReputation', $errors, 'f-minrep-hint') ?> value="<?= $v['minReputation'] === null ? '' : h(number_format((float)$v['minReputation'], 1)) ?>" placeholder="No minimum">
          <?= field_error('minReputation', $errors) ?: '<span class="hint" id="f-minrep-hint">Optional · e.g. 4.0 ★ or higher</span>' ?>
        </div>
      </div>
      <div class="field">
        <label for="f-photo">Photo</label>
        <input type="file" id="f-photo" name="photo" accept="image/jpeg,image/png,image/webp" class="input" style="padding:9px" aria-describedby="f-photo-hint">
        <span class="hint" id="f-photo-hint">Optional · JPG, PNG or WebP under 2.5 MB<?= !empty($v['photo']) ? ' · leave empty to keep the current photo' : '' ?></span>
        <?php if (!empty($v['photo'])): ?><label class="check small"><input type="checkbox" name="removePhoto" value="1"> Remove the current photo</label><?php endif; ?>
        <?= field_error('photo', $errors) ?>
      </div>
      <fieldset style="border:0;padding:0;margin:0">
        <legend><h3 style="margin-bottom:10px">How can people join?</h3></legend>
        <div class="choice-cards">
          <?php foreach ([['open', 'Open joining', 'People join immediately if a place is available.'], ['hostApproval', 'Host approval', 'Review each request before accepting.']] as [$pv, $t, $d]): ?>
            <label class="choice <?= $v['joinPolicy'] === $pv ? 'selected' : '' ?>">
              <strong><input type="radio" name="joinPolicy" value="<?= $pv ?>" <?= $v['joinPolicy'] === $pv ? 'checked' : '' ?>><?= $t ?></strong>
              <span><?= $d ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <div class="row" style="margin-top:6px">
        <button class="btn btn-primary btn-lg" type="submit"><?= $id ? 'Save changes →' : 'Publish Activity →' ?></button>
        <a href="<?= $id ? url('activity.php', ['id' => $id]) : url('dashboard.php') ?>" class="btn btn-outline btn-lg">Cancel</a>
      </div>
    </form>

    <aside class="stack">
      <section class="card" aria-label="Live preview" data-preview>
        <span class="tag">Live preview · Illustrative</span>
        <div data-pv-image style="margin:14px 0">
          <?php activity_image(array_merge($v, ['title' => $v['title'] ?: 'Your activity', 'categoryId' => $v['categoryId'] ?: 'weird']), '', 'width:100%;height:200px;object-fit:cover;border-radius:14px;font-size:60px'); ?>
        </div>
        <span class="tag amber" data-pv-cat <?= $v['categoryId'] ? '' : 'hidden' ?>><?= h($v['categoryId'] ? category_by_id($v['categoryId'])['name'] : '') ?></span>
        <h2 style="margin:10px 0 8px" data-pv-title><?= h($v['title'] ?: 'Your activity title') ?></h2>
        <p class="muted" data-pv-meta></p>
        <p style="margin:8px 0"><span data-pv-going><?= $going ?></span> / <span data-pv-cap><?= h($v['capacity']) ?></span> going · You are the host</p>
        <div class="row"><span class="tag" data-pv-policy><?= $v['joinPolicy'] === 'open' ? 'Open joining' : 'Host approval' ?></span><span class="tag" data-pv-minrep hidden></span></div>
      </section>
      <section class="card">
        <h3 style="margin-bottom:10px">A thoughtful host checklist</h3>
        <ul style="margin:0;padding-left:18px;display:grid;gap:4px">
          <li>Choose a clear meeting point.</li>
          <li>Mention accessibility needs or constraints.</li>
          <li>Respect dietary preferences and boundaries.</li>
          <li>Update the group if your plan changes.</li>
        </ul>
      </section>
    </aside>
  </div>
</div>
<script type="application/json" id="pv-data"><?= json_encode(['places' => array_map(fn($p) => $p['name'], PLACES), 'categories' => array_column(db()['categories'], 'name', 'id')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php page_footer();
