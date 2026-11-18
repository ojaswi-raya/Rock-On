<?php
/** Community — browse members and their reputation (FR-03). */
require __DIR__ . '/includes/bootstrap.php';

$me = me();
$q = trim((string)($_GET['q'] ?? ''));
$interest = (string)($_GET['interest'] ?? 'all');

$members = array_filter(db()['users'], function ($u) use ($me, $q, $interest) {
    if ($u['kind'] !== 'member' || !empty($u['suspended'])) return false;
    if ($me && ($u['id'] === $me['id'] || in_array($u['id'], $me['blocked'] ?? [], true))) return false;
    if ($interest !== 'all' && !in_array($interest, $u['interests'] ?? [], true)) return false;
    if ($q !== '' && !str_contains(mb_strtolower($u['name'] . ' ' . $u['bio'] . ' ' . implode(' ', $u['interests'] ?? [])), mb_strtolower($q))) return false;
    return true;
});
$members = array_map(fn($u) => ['u' => $u, 'rep' => reputation($u['id'])], $members);
usort($members, fn($a, $b) => $b['rep']['n'] <=> $a['rep']['n']);
$all = array_unique(array_merge(...array_map(fn($u) => $u['interests'] ?? [], db()['users'])));
sort($all);

page_header('Community', 'community.php');
?>
<div class="container">
  <div class="page-head"><div><h1>Your neighbourhood</h1><p>People who host and join plans nearby. Reputation comes from released peer ratings.</p></div></div>
  <form class="filters" method="get" data-autosubmit>
    <label class="sr-only" for="cq">Search members</label>
    <input id="cq" name="q" class="input search" placeholder="Search by name or interest" value="<?= h($q) ?>">
    <label class="sr-only" for="ci">Interest</label>
    <select id="ci" name="interest" class="pill-select">
      <option value="all">All interests</option>
      <?php foreach ($all as $i): ?><option value="<?= h($i) ?>" <?= $i === $interest ? 'selected' : '' ?>><?= h($i) ?></option><?php endforeach; ?>
    </select>
    <noscript><button class="btn btn-outline btn-sm" type="submit">Apply</button></noscript>
  </form>
  <?php if (!$members): ?>
    <div class="card"><?php empty_state('No one matches', 'Try another search.'); ?></div>
  <?php else: ?>
    <div class="people-grid">
      <?php foreach ($members as ['u' => $u, 'rep' => $rep]): ?>
        <a href="<?= url('profile.php', ['id' => $u['id']]) ?>" class="card tight person">
          <?php avatar($u, 56); ?>
          <div style="min-width:0">
            <h3 style="font-size:18px"><?= h($u['name']) ?></h3>
            <p class="small muted"><?= $rep['score'] !== null ? number_format($rep['score'], 1) . ' ★ · ' . $rep['n'] . ' ratings' : 'New member' ?></p>
            <span class="tag" style="margin-top:6px"><?= h(badge_for($rep)['label']) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php page_footer();
