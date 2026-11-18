<?php
/** Page fragments used on more than one screen. */

function activity_card(array $a, ?int $index = null): void
{
    $me = me();
    $cat = category_by_id($a['categoryId']);
    $host = user_by_id($a['hostId']);
    $mine = $me ? participation_for($me['id'], $a['id']) : null;
    ?>
<article class="act-card" data-act="<?= h($a['id']) ?>">
  <?php activity_image($a, 'thumb'); ?>
  <div>
    <div class="row">
      <?php if ($index !== null): ?><span class="num" aria-hidden="true"><?= $index + 1 ?></span><?php endif; ?>
      <span class="tag amber"><?= h($cat['name']) ?></span>
      <?= policy_tag($a['joinPolicy']) ?>
      <?php if ($mine): ?><span class="tag ok"><?= $a['hostId'] === $me['id'] ? 'Hosting' : ($mine['status'] === 'pending' ? 'Requested' : 'Joined') ?></span><?php endif; ?>
    </div>
    <h3><a href="<?= url('activity.php', ['id' => $a['id']]) ?>"><?= h($a['title']) ?></a></h3>
    <p class="meta"><?= h(place_name($a['placeId'])) ?> · <?= fmt_day($a['date']) ?> · <?= fmt_range($a) ?></p>
    <div class="row">
      <a href="<?= url('activity.php', ['id' => $a['id']]) ?>" class="btn btn-primary btn-sm" aria-label="View activity: <?= h($a['title']) ?>">View activity</a>
      <span class="small"><?= h($host['name'] ?? '') ?> · <?= rep_text($a['hostId']) ?> · <?= going_count($a) ?> / <?= (int)$a['capacity'] ?> going</span>
    </div>
  </div>
</article>
<?php
}

/** Pins that share an area are fanned out so they never overlap. */
function pin_positions(array $activities): array
{
    $byPlace = [];
    $out = [];
    foreach ($activities as $a) {
        $p = place($a['placeId']);
        $i = $byPlace[$a['placeId']] = ($byPlace[$a['placeId']] ?? -1) + 1;
        if ($i === 0) { $out[] = [$p['x'], $p['y']]; continue; }
        $ang = $i * 2.1;
        $r = 34 + 8 * intdiv($i, 3);
        $out[] = [round($p['x'] + cos($ang) * $r), round($p['y'] + sin($ang) * $r)];
    }
    return $out;
}

/**
 * Illustrative city map (as in the mockup), drawn as SVG so hundreds of pins stay fast (NFR-04).
 * Zoom, pan and pin pop-ups are handled by assets/js/app.js.
 */
function map_view(array $activities): void
{
    $pos = pin_positions($activities);
    ?>
<div class="map sticky" data-map>
  <svg viewBox="0 0 1000 600" preserveAspectRatio="xMidYMid slice" role="group" aria-label="Map of <?= count($activities) ?> activities. Use Tab to move between pins and Enter to select.">
    <rect width="1000" height="600" fill="#e3ead9"/>
    <rect x="610" y="90" width="200" height="170" rx="24" fill="#c3dcb2"/>
    <ellipse cx="300" cy="225" rx="62" ry="40" fill="#c3dcb2"/>
    <rect x="660" y="400" width="130" height="80" rx="18" fill="#d7e6c7"/>
    <path d="M0 470 C 180 430, 330 455, 500 490 S 820 520, 1000 470 L1000 600 L0 600 Z" fill="#c9dfe6"/>
    <g stroke="#fbf6ea" stroke-width="14" fill="none" stroke-linecap="round">
      <line x1="0" y1="330" x2="1000" y2="330"/><line x1="430" y1="0" x2="430" y2="600"/>
      <line x1="800" y1="0" x2="800" y2="600"/><line x1="120" y1="0" x2="120" y2="600"/>
      <line x1="0" y1="430" x2="1000" y2="400"/><line x1="430" y1="200" x2="640" y2="200"/>
      <line x1="260" y1="330" x2="260" y2="210"/><line x1="600" y1="330" x2="600" y2="420"/>
    </g>
    <g font-family="Inter, sans-serif" font-size="15" fill="#5c4630">
      <?php foreach (PLACES as $p): ?>
        <text x="<?= $p['x'] ?>" y="<?= $p['y'] + 66 ?>" text-anchor="middle" paint-order="stroke" stroke="#e3ead9" stroke-width="4"><?= h($p['name']) ?></text>
      <?php endforeach; ?>
      <text x="600" y="545" fill="#4d6b75">Lake</text>
    </g>
    <g aria-hidden="true">
      <circle cx="<?= HOME_X ?>" cy="<?= HOME_Y ?>" r="9" fill="#1d5fd1" stroke="#fff" stroke-width="3"/>
      <text x="<?= HOME_X + 14 ?>" y="<?= HOME_Y - 10 ?>" font-size="13" fill="#1d5fd1" font-family="Inter, sans-serif">You</text>
    </g>
    <?php foreach ($activities as $i => $a): [$x, $y] = $pos[$i]; ?>
      <g class="pin" transform="translate(<?= $x ?> <?= $y ?>)" tabindex="0" role="button" aria-pressed="false"
         aria-label="<?= $i + 1 ?>. <?= h($a['title']) ?>, <?= fmt_day($a['date']) ?>"
         data-id="<?= h($a['id']) ?>" data-x="<?= $x ?>" data-y="<?= $y ?>"
         data-title="<?= h($a['title']) ?>" data-href="<?= h(url('activity.php', ['id' => $a['id']])) ?>"
         data-sub="<?= h(fmt_day($a['date']) . ' · ' . places_left($a) . ' places available') ?>">
        <circle class="ring" r="19" fill="#fff"/>
        <circle class="dot" r="16" fill="#c4304a"/>
        <text text-anchor="middle" dy="0.35em" font-size="15" font-weight="700" fill="#fff" font-family="Inter, sans-serif"><?= $i + 1 ?></text>
      </g>
    <?php endforeach; ?>
  </svg>
  <span class="tag map-label">Map view · Illustrative</span>
  <div class="map-zoom">
    <button type="button" data-zoom="in" aria-label="Zoom in">+</button>
    <button type="button" data-zoom="out" aria-label="Zoom out">−</button>
  </div>
  <div class="map-pop" hidden><a href="#"></a><p></p></div>
</div>
<?php
}

/** FR-07: the host's pending requests for one activity, with Accept / Decline. */
function request_list(array $a): void
{
    $reqs = pending_of($a['id']);
    $left = places_left($a);
    if (!$reqs) {
        echo '<p class="muted small">No requests waiting. New ones will appear here and in your notifications.</p>';
        return;
    }
    foreach ($reqs as $p) {
        $u = user_by_id($p['memberId']);
        ?>
<div class="request">
  <?php avatar($u, 42); ?>
  <div class="info">
    <strong><a href="<?= url('profile.php', ['id' => $u['id']]) ?>" style="color:inherit;text-decoration:none"><?= h($u['name']) ?></a> · <?= rep_text($u['id']) ?></strong>
    <div class="tiny muted"><?= h($u['bio'] !== '' ? mb_strimwidth($u['bio'], 0, 62, '…') : 'No bio yet') ?></div>
  </div>
  <div class="row">
    <?= action_button('decide', ['participation' => $p['id'], 'decision' => 'accept'], 'Accept', 'btn btn-primary btn-sm', 'aria-label="Accept ' . h($u['name']) . '"') ?>
    <?= action_button('decide', ['participation' => $p['id'], 'decision' => 'decline'], 'Decline', 'btn btn-outline btn-sm', 'aria-label="Decline ' . h($u['name']) . '"') ?>
  </div>
</div>
<?php
    }
    echo '<p class="tiny muted" style="margin-top:10px">' . $left . ' ' . ($left === 1 ? 'place remains' : 'places remain') . '. Requests do not count as accepted participants; capacity is checked on acceptance.</p>';
}
