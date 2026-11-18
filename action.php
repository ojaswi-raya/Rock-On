<?php
/**
 * Handles the small POST actions used across pages (join, accept, chat, report…).
 * Every action checks the CSRF token, runs one service function, sets a flash message
 * and redirects back (Post/Redirect/Get).
 */
require __DIR__ . '/includes/bootstrap.php';

if (!is_post()) redirect('explore.php');
csrf_check();

$do = post('do');
$me = me();
$say = function (array $r, string $okText, string $fallback = 'explore.php') {
    flash($r['ok'] ? $okText : $r['error'], $r['ok'] ? 'ok' : 'err');
    redirect_back($fallback);
};

// ---------- Actions anyone can take ----------
switch ($do) {
    case 'logout':
        logout();
        flash('You’re logged out. See you soon!');
        redirect_back('index.php');
    case 'demo-login':
        $r = svc_login(post('email'), DEMO_PASSWORD);
        if (!$r['ok']) { flash($r['error'], 'err'); redirect_back(); }
        flash('Signed in as ' . $r['user']['name']);
        redirect($r['user']['kind'] === 'admin' ? 'admin/index.php' : 'dashboard.php');
    case 'demo-reset':
        db_reset();
        logout();
        flash('Demo data reset');
        redirect('index.php');
}

// ---------- Member actions (use cases that include "Log in") ----------
$memberActions = ['join', 'leave', 'decide', 'cancel', 'message', 'report', 'block', 'read', 'read-all'];
if (in_array($do, $memberActions, true)) {
    if (!$me) {
        flash('Log in to continue.', 'err');
        redirect('login.php', ['next' => safe_next(post('back')) ?? 'explore.php']);
    }
    if ($me['kind'] !== 'member') { flash('Administrators can’t do that.', 'err'); redirect('admin/index.php'); }
    $id = $me['id'];

    switch ($do) {
        case 'join':
            $r = svc_request_join($id, post('activity'));
            $say($r, ($r['status'] ?? '') === 'accepted' ? 'You’re in! The group chat is open.' : 'Request sent. You’ll be notified when the host replies.');
        case 'leave':
            $r = svc_leave($id, post('activity'));
            $say($r, ($r['was'] ?? '') === 'pending' ? 'Request withdrawn.' : 'You left the activity. The host was notified.');
        case 'decide':
            $accept = post('decision') === 'accept';
            $r = svc_decide($id, post('participation'), $accept);
            $say($r, $accept ? ($r['name'] ?? 'They') . ' is in. They’ve been notified.' : 'Request from ' . ($r['name'] ?? 'member') . ' declined.', 'dashboard.php');
        case 'cancel':
            $say(svc_cancel_activity($id, post('activity'), post('reason')), 'Activity cancelled. Participants were notified.', 'dashboard.php');
        case 'message':
            $r = svc_send_message($id, post('activity'), post('content'));
            if (!$r['ok']) flash($r['error'], 'err');
            redirect_back();
        case 'report':
            $say(svc_report($id, post('targetType'), post('targetId'), post('reason'), post('details')), 'Thanks. A moderator will review your report.');
        case 'block':
            $r = svc_toggle_block($id, post('member'));
            $name = user_by_id(post('member'))['name'] ?? 'Member';
            $say($r, ($r['blocked'] ?? false) ? "$name blocked." : "$name unblocked.");
        case 'read':
            svc_mark_read($id, post('notification'));
            $link = post('link');
            if ($link !== '' && safe_next($link)) { header('Location: ' . base() . $link); exit; }
            redirect_back('notifications.php');
        case 'read-all':
            svc_mark_read($id);
            flash('All notifications marked as read.');
            redirect_back('notifications.php');
    }
}

// ---------- Administrator actions (FR-17) ----------
$adminActions = ['review', 'remove', 'suspend', 'reinstate', 'close-report', 'category-save', 'category-delete'];
if (in_array($do, $adminActions, true)) {
    if (!$me || $me['kind'] !== 'admin') { flash('Moderators only.', 'err'); redirect('login.php'); }
    switch ($do) {
        case 'remove':
            $say(svc_remove_content(post('report'), post('reason')), 'Done. The listing was removed and the report resolved.', 'admin/index.php');
        case 'suspend':
            $say(svc_set_suspended(post('member'), true, post('report') ?: null), 'Account suspended.', 'admin/index.php');
        case 'reinstate':
            $say(svc_set_suspended(post('member'), false), 'Account reinstated.', 'admin/accounts.php');
        case 'close-report':
            $say(svc_set_report_status(post('report'), 'resolved', 'Closed with no action'), 'Report closed with no action.', 'admin/index.php');
        case 'category-save':
            $say(svc_save_category(post('id') ?: null, post('name'), post('icon')), 'Category saved.', 'admin/categories.php');
        case 'category-delete':
            $say(svc_delete_category(post('id')), 'Category deleted.', 'admin/categories.php');
    }
}

flash('Unknown action.', 'err');
redirect_back();
