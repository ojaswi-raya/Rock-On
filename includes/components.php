<?php
/**
 * Shared layout and UI components (PHP functions that echo HTML).
 */

/** Navigation links per actor (report §4.1). */
function nav_links(string $kind): array
{
    return match ($kind) {
        'admin' => [['admin/index.php', 'Report queue'], ['admin/categories.php', 'Categories'], ['admin/accounts.php', 'Accounts']],
        'member' => [['explore.php', 'Explore'], ['dashboard.php', 'Dashboard'], ['community.php', 'Community'], ['about.php', 'About']],
        default => [['index.php', 'Home'], ['explore.php', 'Explore'], ['community.php', 'Community'], ['about.php', 'About']],
    };
}

function page_header(string $title, string $active = ''): void
{
    $me = me();
    $kind = $me ? ($me['kind'] === 'admin' ? 'admin' : 'member') : 'guest';
    $unread = $kind === 'member' ? unread_count($me['id']) : 0;
    $home = $kind === 'admin' ? 'admin/index.php' : ($kind === 'member' ? 'explore.php' : 'index.php');
    ?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="RockOn — create or join casual local activities and meet new people safely.">
  <meta name="theme-color" content="#c4304a">
  <title><?= h($title) ?> · RockOn</title>
  <link rel="icon" href="<?= asset('img/logo.png') ?>">
  <link rel="stylesheet" href="<?= asset('css/styles.css') ?>">
  <script src="<?= asset('js/app.js') ?>" defer></script>
</head>
<body>
<a href="#main" class="skip-link">Skip to content</a>
<header class="nav">
  <div class="container nav-inner">
    <a href="<?= url($home) ?>" class="brand"><img src="<?= asset('img/logo.png') ?>" alt=""> RockOn<?php if ($kind === 'admin'): ?> <small>/ Moderation</small><?php endif; ?></a>
    <nav aria-label="Main" style="display:contents">
      <ul class="nav-links" id="main-links">
        <?php foreach (nav_links($kind) as [$href, $label]): ?>
          <li><a href="<?= url($href) ?>" class="<?= $active === $href ? 'active' : '' ?>" <?= $active === $href ? 'aria-current="page"' : '' ?>><?= h($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <div class="nav-right">
      <?php if ($kind === 'guest'): ?>
        <a href="<?= url('login.php') ?>" class="btn btn-ghost-light btn-sm">Log in</a>
        <a href="<?= url('register.php') ?>" class="btn btn-amber btn-sm hide-sm">Sign up</a>
      <?php elseif ($kind === 'member'): ?>
        <a href="<?= url('notifications.php') ?>" class="nav-bell" aria-label="Notifications, <?= $unread ?> unread">
          <span class="hide-sm">Notifications</span><span class="count" aria-hidden="true"><?= $unread ?></span>
        </a>
      <?php else: ?>
        <span class="hide-sm">Moderator</span>
      <?php endif; ?>
      <?php if ($me): ?>
        <div class="menu" data-menu>
          <button class="menu-btn" aria-haspopup="true" aria-expanded="false" aria-label="Account menu"><?php avatar($me, 34); ?></button>
          <div class="menu-pop" hidden>
            <div class="who"><strong><?= h($me['name']) ?></strong><div class="tiny muted"><?= h($me['email']) ?></div></div>
            <?php if ($kind === 'member'): ?>
              <a href="<?= url('profile.php', ['id' => $me['id']]) ?>">My public profile</a>
              <a href="<?= url('profile.php', ['id' => 'me']) ?>">Edit profile</a>
              <a href="<?= url('activity-form.php') ?>">Create an activity</a>
            <?php endif; ?>
            <form method="post" action="<?= url('action.php') ?>"><?= csrf_field() ?><input type="hidden" name="do" value="logout"><button type="submit">Log out</button></form>
          </div>
        </div>
      <?php endif; ?>
      <button class="nav-burger" aria-expanded="false" aria-controls="main-links"><span aria-hidden="true">☰</span><span class="sr-only">Menu</span></button>
    </div>
  </div>
</header>
<?php demo_strip($me); ?>
<main id="main" tabindex="-1">
<?php
}

function demo_strip(?array $me): void
{
    $btn = function (string $label, string $email) {
        echo '<form method="post" action="' . url('action.php') . '" class="inline">' . csrf_field()
            . '<input type="hidden" name="do" value="demo-login"><input type="hidden" name="email" value="' . h($email) . '">'
            . '<button class="link-btn" type="submit">' . h($label) . '</button></form>';
    };
    ?>
<div class="demo-strip" role="region" aria-label="Demo controls" data-demo-strip>
  <div class="container">
    <strong>Demo:</strong>
    <span><?= $me ? 'signed in as ' . h($me['name']) : 'browsing as a guest' ?>.</span>
    <span>Switch to</span>
    <?php $btn('Maya (host)', 'maya@rockon.app'); $btn('Ojaswi (member)', 'ojaswi@rockon.app'); $btn('Karl (moderator)', 'karl@rockon.app'); ?>
    <?php if ($me): ?>
      <form method="post" action="<?= url('action.php') ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="logout"><input type="hidden" name="back" value="explore.php"><button class="link-btn" type="submit">Guest</button></form>
    <?php endif; ?>
    <span style="flex:1"></span>
    <form method="post" action="<?= url('action.php') ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="demo-reset"><button class="link-btn" type="submit">Reset demo data</button></form>
    <button class="link-btn" type="button" data-hide-demo aria-label="Hide demo bar">✕</button>
  </div>
</div>
<?php
}

function page_footer(): void
{
    ?>
</main>
<footer class="footer">
  <div class="container">
    <span>© 2026 RockOn · “Bored? Meet new people. Socialize!!”</span>
    <span>For adults aged 18 and over · <a href="<?= url('about.php') ?>">About &amp; safety</a></span>
  </div>
</footer>
<div class="toasts" role="status" aria-live="polite">
  <?php foreach (take_flashes() as $f): ?>
    <div class="toast <?= $f['tone'] === 'err' ? 'err' : '' ?>"><span><?= h($f['text']) ?></span><button type="button" aria-label="Dismiss" data-dismiss>✕</button></div>
  <?php endforeach; ?>
</div>
</body>
</html>
<?php
}

// ---------- Small components ----------

const AVATAR_COLORS = ['#ffd27a', '#f5b3a6', '#c9e4b4', '#b9d4f0', '#e5c6f0', '#f2d0a4'];

function avatar(?array $u, int $size = 40): void
{
    if (!$u) return;
    $style = "width:{$size}px;height:{$size}px;font-size:" . round($size * 0.4) . 'px';
    if (!empty($u['photo'])) {
        echo '<img class="avatar" src="' . h(base() . $u['photo']) . '" alt="" width="' . $size . '" height="' . $size . '" style="' . $style . '">';
        return;
    }
    $initials = '';
    foreach (array_slice(preg_split('/\s+/', trim($u['name'])), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1));
    $bg = AVATAR_COLORS[array_sum(array_map('ord', str_split($u['id']))) % count(AVATAR_COLORS)];
    echo '<span class="avatar" aria-hidden="true" style="' . $style . ';background:' . $bg . '">' . h($initials) . '</span>';
}

const CATEGORY_TINT = ['outdoors' => '#cfe6c0', 'local' => '#f6dcb8', 'party' => '#2d2140', 'weird' => '#ffe08a'];

function activity_image(array $a, string $class = '', string $style = ''): void
{
    if (!empty($a['photo'])) {
        echo '<img class="' . h($class) . '" src="' . h(base() . $a['photo']) . '" alt="Photo for ' . h($a['title']) . '" style="' . h($style) . '">';
        return;
    }
    $c = category_by_id($a['categoryId']);
    $bg = CATEGORY_TINT[$a['categoryId']] ?? '#efe2cf';
    echo '<div class="placeholder-img ' . h($class) . '" style="background:' . $bg . ';' . h($style) . '" role="img" aria-label="' . h($c['name']) . ' activity"><span aria-hidden="true">' . h($c['icon']) . '</span></div>';
}

function policy_tag(string $policy): string
{
    return '<span class="tag">' . ($policy === 'open' ? 'Open joining' : 'Host approval') . '</span>';
}

function crumbs(array $items): void
{
    echo '<nav class="crumbs" aria-label="Breadcrumb">';
    foreach ($items as $i => [$label, $href]) {
        if ($i > 0) echo ' / ';
        echo $href ? '<a href="' . h(url($href)) . '">' . h($label) . '</a>' : '<span aria-current="page">' . h($label) . '</span>';
    }
    echo '</nav>';
}

function empty_state(string $title, string $text, string $actionsHtml = ''): void
{
    echo '<div class="empty"><h3>' . h($title) . '</h3><p>' . h($text) . '</p>' . ($actionsHtml ? '<div class="row" style="justify-content:center;margin-top:16px">' . $actionsHtml . '</div>' : '') . '</div>';
}

/** A one-button POST form to action.php (join, accept, mark read…). */
function action_button(string $do, array $fields, string $label, string $class = 'btn btn-primary btn-sm', string $extraAttrs = ''): string
{
    $html = '<form method="post" action="' . url('action.php') . '" class="inline">' . csrf_field()
        . '<input type="hidden" name="do" value="' . h($do) . '"><input type="hidden" name="back" value="' . h(current_path()) . '">';
    foreach ($fields as $k => $v) $html .= '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '">';
    return $html . '<button type="submit" class="' . h($class) . '" ' . $extraAttrs . '>' . h($label) . '</button></form>';
}

/**
 * Confirmation dialog (native <dialog>, opened by any element with data-open="ID").
 * Posts to action.php with the given fields; optional free-text reason.
 */
function confirm_dialog(string $id, string $title, string $body, string $do, array $fields, string $confirmLabel, string $cancelLabel = 'Keep it', ?string $reasonLabel = null): void
{
    ?>
<dialog class="modal" id="<?= h($id) ?>" aria-labelledby="<?= h($id) ?>-t">
  <form method="post" action="<?= url('action.php') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="<?= h($do) ?>">
    <input type="hidden" name="back" value="<?= h(current_path()) ?>">
    <?php foreach ($fields as $k => $v): ?><input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>"><?php endforeach; ?>
    <h2 id="<?= h($id) ?>-t"><?= h($title) ?></h2>
    <p class="muted"><?= h($body) ?></p>
    <?php if ($reasonLabel): ?>
      <div class="field" style="margin-top:16px">
        <label for="<?= h($id) ?>-reason"><?= h($reasonLabel) ?></label>
        <textarea id="<?= h($id) ?>-reason" name="reason" class="textarea" maxlength="300"></textarea>
      </div>
    <?php endif; ?>
    <div class="actions">
      <button class="btn btn-primary" type="submit"><?= h($confirmLabel) ?></button>
      <button class="btn btn-outline" type="button" data-close><?= h($cancelLabel) ?></button>
    </div>
  </form>
</dialog>
<?php
}

const REPORT_REASONS = [
    'member' => ['Unwanted contact', 'Harassment or hate', 'Fake profile', 'Unsafe behaviour at an activity', 'Spam', 'Other'],
    'activity' => ['Misleading meeting location', 'Unsafe activity', 'Spam or advertising', 'Inappropriate content', 'Other'],
];

function report_dialog(string $targetType, string $targetId, string $targetName): void
{
    ?>
<dialog class="modal" id="report-dialog" aria-labelledby="report-t">
  <form method="post" action="<?= url('action.php') ?>" class="form-grid" data-require-radio="reason">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="report">
    <input type="hidden" name="back" value="<?= h(current_path()) ?>">
    <input type="hidden" name="targetType" value="<?= h($targetType) ?>">
    <input type="hidden" name="targetId" value="<?= h($targetId) ?>">
    <h2 id="report-t">Report <?= $targetType === 'member' ? 'member' : 'activity' ?></h2>
    <p class="muted small">You’re reporting <strong><?= h($targetName) ?></strong>. A moderator reviews every report. The person isn’t told who reported them.</p>
    <fieldset class="form-grid" style="border:0;padding:0;margin:0">
      <legend class="label" style="margin-bottom:8px">What’s wrong?</legend>
      <?php foreach (REPORT_REASONS[$targetType] as $r): ?>
        <label class="check"><input type="radio" name="reason" value="<?= h($r) ?>"> <?= h($r) ?></label>
      <?php endforeach; ?>
      <p class="err small" data-radio-error hidden style="color:var(--error-ink)">Choose a reason.</p>
    </fieldset>
    <div class="field">
      <label for="report-details">Details (optional)</label>
      <textarea id="report-details" name="details" class="textarea" maxlength="600" placeholder="What happened? Stick to facts."></textarea>
    </div>
    <div class="actions" style="margin-top:4px">
      <button class="btn btn-primary" type="submit">Send report</button>
      <button class="btn btn-outline" type="button" data-close>Cancel</button>
    </div>
  </form>
</dialog>
<?php
}

/** Form-field helpers that keep old input and show errors after a redirect. */
function field_attrs(string $name, array $errors, ?string $hintId = null): string
{
    $attrs = 'id="f-' . h($name) . '" name="' . h($name) . '"';
    if (!empty($errors[$name])) $attrs .= ' aria-invalid="true" aria-describedby="f-' . h($name) . '-err"';
    elseif ($hintId) $attrs .= ' aria-describedby="' . h($hintId) . '"';
    return $attrs;
}

function field_error(string $name, array $errors): string
{
    return !empty($errors[$name]) ? '<span class="err" id="f-' . h($name) . '-err">' . h($errors[$name]) . '</span>' : '';
}
