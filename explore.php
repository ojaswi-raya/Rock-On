<?php
/** Screen 1 — Explore (FR-05, FR-10): list + map with search, category, date and distance filters. */
require __DIR__ . '/includes/bootstrap.php';

$me = me();
$q = trim((string)($_GET['q'] ?? ''));
$cat = (string)($_GET['cat'] ?? 'all');
$when = (string)($_GET['when'] ?? 'week');
$dist = (string)($_GET['dist'] ?? '5');
$view = ($_GET['view'] ?? 'list') === 'map' ? 'map' : 'list';

$WHEN = ['today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'any' => 'Any date'];
$DIST = ['2' => 'Within 2 km', '5' => 'Within 5 km', '10' => 'Within 10 km', 'any' => 'Any distance'];
if (!isset($WHEN[$when])) $when = 'week';
if (!isset($DIST[$dist])) $dist = '5';
$limit = ['today' => iso_day(0), 'week' => iso_day(7), 'month' => iso_day(31), 'any' => '9999-12-31'][$when];
$needle = mb_strtolower($q);

$results = array_filter(db()['activities'], function ($a) use ($me, $cat, $limit, $dist, $needle) {
    if (!is_upcoming($a)) return false;
    if ($me && in_array($a['hostId'], $me['blocked'] ?? [], true)) return false;   // FR-16
    if (!empty(user_by_id($a['hostId'])['suspended'])) return false;
    if ($cat !== 'all' && $a['categoryId'] !== $cat) return false;
    if ($a['date'] > $limit) return false;
    if ($dist !== 'any' && distance_km($a['placeId']) > (float)$dist) return false;
    if ($needle !== '') {
        $hay = mb_strtolower(implode(' ', [$a['title'], $a['description'], place_name($a['placeId']), $a['meetingPoint'], category_by_id($a['categoryId'])['name']]));
        if (!str_contains($hay, $needle)) return false;
    }
    return true;
});
$results = sort_by_start($results);
$n = count($results);

/** Build a link that keeps the current filters and changes one. */
$link = function (array $change) use ($q, $cat, $when, $dist, $view) {
    $params = array_merge(['q' => $q, 'cat' => $cat, 'when' => $when, 'dist' => $dist, 'view' => $view], $change);
    return url('explore.php', array_filter($params, fn($v) => $v !== '' && $v !== 'all'));
};

page_header('Explore', 'explore.php');
?>
<div class="container">
  <div class="page-head">
    <div>
      <h1>Find your next plan</h1>
      <p><?= $n ?> <?= $n === 1 ? 'activity' : 'activities' ?> near you <?= mb_strtolower($WHEN[$when]) ?>. Browse the list or the map.</p>
    </div>
    <a class="btn btn-primary btn-lg" href="<?= $me ? url('activity-form.php') : url('login.php', ['next' => 'activity-form.php']) ?>">+ Create Activity</a>
  </div>

  <form class="filters" role="search" method="get" action="<?= url('explore.php') ?>" data-autosubmit>
    <label class="sr-only" for="q">Search activities or places</label>
    <input id="q" name="q" class="input search" placeholder="Search activities or places" value="<?= h($q) ?>">
    <input type="hidden" name="cat" value="<?= h($cat) ?>">
    <input type="hidden" name="view" value="<?= h($view) ?>">
    <div class="row" role="group" aria-label="Category">
      <a class="chip" href="<?= $link(['cat' => 'all']) ?>" aria-pressed="<?= $cat === 'all' ? 'true' : 'false' ?>">All</a>
      <?php foreach (db()['categories'] as $c): ?>
        <a class="chip" href="<?= $link(['cat' => $c['id']]) ?>" aria-pressed="<?= $cat === $c['id'] ? 'true' : 'false' ?>"><?= h($c['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <span class="spacer"></span>
    <label class="sr-only" for="when">Date</label>
    <select id="when" name="when" class="pill-select">
      <?php foreach ($WHEN as $v => $l): ?><option value="<?= $v ?>" <?= $v === $when ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
    </select>
    <label class="sr-only" for="dist">Distance</label>
    <select id="dist" name="dist" class="pill-select">
      <?php foreach ($DIST as $v => $l): ?><option value="<?= $v ?>" <?= (string)$v === $dist ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
    </select>
    <noscript><button class="btn btn-outline btn-sm" type="submit">Apply</button></noscript>
    <div class="view-toggle" role="group" aria-label="View">
      <a class="chip" href="<?= $link(['view' => 'list']) ?>" aria-pressed="<?= $view === 'list' ? 'true' : 'false' ?>">List</a>
      <a class="chip" href="<?= $link(['view' => 'map']) ?>" aria-pressed="<?= $view === 'map' ? 'true' : 'false' ?>">Map</a>
    </div>
  </form>

  <div class="explore-grid" data-view="<?= $view ?>">
    <section class="list-col" aria-label="Activity list">
      <?php if ($results): ?>
        <div class="act-list"><?php foreach ($results as $i => $a) activity_card($a, $i); ?></div>
      <?php else: ?>
        <div class="card"><?php empty_state('No plans match yet', 'Try a wider date range or distance, or start something new.',
            '<a class="btn btn-outline" href="' . url('explore.php') . '">Clear filters</a><a class="btn btn-primary" href="' . ($me ? url('activity-form.php') : url('login.php', ['next' => 'activity-form.php'])) . '">Host one yourself</a>'); ?></div>
      <?php endif; ?>
    </section>
    <section class="map-col" aria-label="Map"><?php map_view($results); ?></section>
  </div>
</div>
<?php page_footer();
