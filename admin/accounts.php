<?php
/** Admin — suspend or reinstate member accounts (FR-17). */
require __DIR__ . '/../includes/bootstrap.php';

require_admin();
$q = trim((string)($_GET['q'] ?? ''));
$filter = (string)($_GET['status'] ?? 'all');
$members = array_filter(db()['users'], function ($u) use ($q, $filter) {
    if ($u['kind'] !== 'member') return false;
    if ($filter === 'suspended' && empty($u['suspended'])) return false;
    if ($filter === 'active' && !empty($u['suspended'])) return false;
    return $q === '' || str_contains(mb_strtolower($u['name'] . ' ' . $u['email']), mb_strtolower($q));
});
$reportsAgainst = fn($id) => count(array_filter(db()['reports'], fn($r) => $r['targetType'] === 'member' && $r['targetId'] === $id));

page_header('Accounts', 'admin/accounts.php');
?>
<div class="container">
  <div class="page-head"><div><h1>Accounts</h1><p>Suspend or reinstate members. Suspension blocks login, hosting and joining.</p></div></div>
  <form class="filters" method="get" data-autosubmit>
    <label class="sr-only" for="aq">Search accounts</label>
    <input id="aq" name="q" class="input search" placeholder="Search name or email" value="<?= h($q) ?>">
    <label class="sr-only" for="af">Status</label>
    <select id="af" name="status" class="pill-select">
      <option value="all">All members</option>
      <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Active</option>
      <option value="suspended" <?= $filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
    </select>
    <noscript><button class="btn btn-outline btn-sm">Apply</button></noscript>
  </form>
  <section class="card" style="overflow-x:auto">
    <table class="table">
      <thead><tr><th scope="col">Member</th><th scope="col">Email</th><th scope="col">Reputation</th><th scope="col">Reports</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
      <tbody>
        <?php foreach ($members as $u): $rep = reputation($u['id']); $s = !empty($u['suspended']); ?>
          <tr>
            <td><div class="row" style="flex-wrap:nowrap"><?php avatar($u, 30); ?><a href="<?= url('profile.php', ['id' => $u['id']]) ?>"><?= h($u['name']) ?></a></div></td>
            <td class="muted"><?= h($u['email']) ?></td>
            <td><?= $rep['score'] !== null ? number_format($rep['score'], 1) . ' ★ (' . $rep['n'] . ')' : 'New' ?></td>
            <td><?= $reportsAgainst($u['id']) ?: '—' ?></td>
            <td><span class="tag <?= $s ? 'err' : 'ok' ?>"><?= $s ? 'Suspended' : 'Active' ?></span></td>
            <td style="text-align:right"><button type="button" class="btn btn-outline btn-sm" data-open="acc-<?= h($u['id']) ?>"><?= $s ? 'Reinstate' : 'Suspend' ?></button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</div>
<?php
foreach ($members as $u) {
    $s = !empty($u['suspended']);
    confirm_dialog('acc-' . $u['id'], ($s ? 'Reinstate ' : 'Suspend ') . $u['name'] . '?',
        $s ? 'They will be able to log in, host and join again.' : 'They will be logged out and unable to log in, host or join until reinstated.',
        $s ? 'reinstate' : 'suspend', ['member' => $u['id']], $s ? 'Reinstate' : 'Suspend account', 'Cancel');
}
page_footer();
