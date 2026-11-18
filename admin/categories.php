<?php
/** Admin — manage categories (FR-10, FR-17). */
require __DIR__ . '/../includes/bootstrap.php';

require_admin();
$editing = (string)($_GET['edit'] ?? '');
$cur = null;
foreach (db()['categories'] as $c) if ($c['id'] === $editing) $cur = $c;
$count = fn($id) => count(array_filter(db()['activities'], fn($a) => $a['categoryId'] === $id && activity_status($a) === 'published'));

page_header('Categories', 'admin/categories.php');
?>
<div class="container" style="max-width:860px">
  <div class="page-head">
    <div><h1>Categories</h1><p>Hosts pick one when creating an activity. Members filter Explore by them.</p></div>
    <a class="btn btn-primary" href="<?= url('admin/categories.php', ['edit' => 'new']) ?>#cat-form">+ New category</a>
  </div>
  <section class="card" style="overflow-x:auto">
    <table class="table">
      <thead><tr><th scope="col">Icon</th><th scope="col">Name</th><th scope="col">Upcoming activities</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
      <tbody>
        <?php foreach (db()['categories'] as $c): ?>
          <tr>
            <td style="font-size:22px"><?= h($c['icon']) ?></td>
            <td><strong><?= h($c['name']) ?></strong></td>
            <td><?= $count($c['id']) ?></td>
            <td style="text-align:right">
              <div class="row" style="justify-content:flex-end">
                <a class="btn btn-outline btn-sm" href="<?= url('admin/categories.php', ['edit' => $c['id']]) ?>#cat-form">Edit</a>
                <button type="button" class="btn btn-outline btn-sm" data-open="del-<?= h($c['id']) ?>">Delete</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if ($editing !== ''): ?>
      <form id="cat-form" class="form-grid" method="post" action="<?= url('action.php') ?>" style="margin-top:20px;padding-top:20px;border-top:1px solid var(--line)">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="category-save">
        <input type="hidden" name="id" value="<?= h($cur['id'] ?? '') ?>">
        <input type="hidden" name="back" value="admin/categories.php">
        <h3><?= $cur ? 'Edit category' : 'New category' ?></h3>
        <div class="grid-loc">
          <div class="field"><label for="cn">Name</label><input id="cn" name="name" class="input" value="<?= h($cur['name'] ?? '') ?>" maxlength="30" required autofocus></div>
          <div class="field"><label for="ci2">Icon (emoji)</label><input id="ci2" name="icon" class="input" value="<?= h($cur['icon'] ?? '') ?>" maxlength="4"></div>
        </div>
        <div class="row"><button class="btn btn-primary" type="submit">Save</button><a class="btn btn-outline" href="<?= url('admin/categories.php') ?>">Cancel</a></div>
      </form>
    <?php endif; ?>
  </section>
</div>
<?php
foreach (db()['categories'] as $c) {
    confirm_dialog('del-' . $c['id'], 'Delete “' . $c['name'] . '”?', 'Past activities keep their label. You can’t delete a category that still has upcoming activities.',
        'category-delete', ['id' => $c['id']], 'Delete category', 'Keep it');
}
page_footer();
