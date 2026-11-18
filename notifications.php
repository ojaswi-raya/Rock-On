<?php
/** Notifications (FR-18): requests, changes, reminders, rating prompts, moderation outcomes. */
require __DIR__ . '/includes/bootstrap.php';

$me = require_member();
$list = notifications_for($me['id']);
$unread = count(array_filter($list, fn($n) => !$n['isRead']));
const NOTIF_ICON = ['request' => '🙋', 'joined' => '✅', 'accepted' => '🎉', 'declined' => '✉️', 'changed' => '✏️', 'cancelled' => '⛔', 'rating' => '⭐',
    'released' => '🔓', 'reminder' => '⏰', 'moderation' => '🛡️', 'welcome' => '👋', 'left' => '👋'];

page_header('Notifications');
?>
<div class="container" style="max-width:820px">
  <div class="page-head">
    <div><h1>Notifications</h1><p>Requests, changes, reminders and rating prompts.</p></div>
    <?php if ($unread): ?><?= action_button('read-all', [], 'Mark all as read', 'btn btn-outline') ?><?php endif; ?>
  </div>
  <section class="card">
    <?php if (!$list) empty_state('Nothing yet', 'When something happens with your plans, it shows up here.'); ?>
    <?php foreach ($list as $n): ?>
      <form method="post" action="<?= url('action.php') ?>">
        <?= csrf_field() ?><input type="hidden" name="do" value="read"><input type="hidden" name="notification" value="<?= h($n['id']) ?>"><input type="hidden" name="link" value="<?= h($n['link']) ?>">
        <button type="submit" class="notif notif-btn <?= $n['isRead'] ? '' : 'unread' ?>" style="display:flex;gap:14px">
          <span aria-hidden="true" style="font-size:22px"><?= NOTIF_ICON[$n['type']] ?? '🔔' ?></span>
          <span style="flex:1"><strong><?= h($n['content']) ?></strong><p><?= h($n['detail']) ?></p><span class="tiny muted"><?= fmt_when($n['at']) ?><?= $n['isRead'] ? '' : ' · New' ?></span></span>
        </button>
      </form>
    <?php endforeach; ?>
  </section>
</div>
<?php page_footer();
