<?php
/** Screen 2a — Login (FR-01). One generic error that never reveals which field was wrong. */
require __DIR__ . '/includes/bootstrap.php';

$me = me();
if ($me) redirect($me['kind'] === 'admin' ? 'admin/index.php' : 'dashboard.php');

$next = safe_next($_GET['next'] ?? $_POST['next'] ?? null);
$error = null;
$email = '';

if (is_post()) {
    csrf_check();
    $email = post('email');
    $r = svc_login($email, (string)($_POST['password'] ?? ''));
    if ($r['ok']) {
        flash('Welcome back, ' . explode(' ', $r['user']['name'])[0] . '!');
        if ($r['user']['kind'] === 'admin') redirect('admin/index.php');
        header('Location: ' . base() . ($next ?? 'dashboard.php'));
        exit;
    }
    $error = $r['error'];
}

page_header('Log in');
?>
<div class="container auth">
  <div>
    <span class="tag amber">Your next plan starts here</span>
    <h1 style="margin-top:20px">Good company;<br>Closer than you think.</h1>
    <img class="photo" src="<?= asset('img/party-login.jpg') ?>" alt="People dancing at a colourful party">
    <p class="muted" style="font-size:17px">A park walk, a local outing, a new familiar face. Log in to join the conversation before you meet.</p>
  </div>
  <div class="card">
    <span class="tag">Welcome back</span>
    <h2 style="font-size:36px;color:var(--crimson);margin:14px 0 10px">Log in to RockOn</h2>
    <p class="muted" style="margin-bottom:20px;font-size:16px">Find your plans, requests and people in one place.</p>
    <form class="form-grid" method="post" novalidate>
      <?= csrf_field() ?>
      <?php if ($next): ?><input type="hidden" name="next" value="<?= h($next) ?>"><?php endif; ?>
      <div class="field">
        <label for="email">Email address</label>
        <input id="email" name="email" class="input" type="email" autocomplete="email" placeholder="nom.prenom@example.com" value="<?= h($email) ?>" required <?= $error ? 'aria-invalid="true" aria-describedby="login-err"' : '' ?>>
      </div>
      <div class="field">
        <label for="pw">Password</label>
        <div class="input-wrap">
          <input id="pw" name="password" class="input" type="password" autocomplete="current-password" required data-caps <?= $error ? 'aria-invalid="true" aria-describedby="login-err"' : '' ?>>
          <button type="button" class="link-btn show" data-toggle-pw="pw" aria-pressed="false" aria-label="Show password">Show</button>
        </div>
        <span class="hint" data-caps-hint hidden>Caps Lock is on.</span>
      </div>
      <?php if ($error): ?><div id="login-err" class="alert err" role="alert">Error: <?= h($error) ?></div><?php endif; ?>
      <div><button class="btn btn-primary btn-lg" type="submit">Log in →</button></div>
    </form>
    <p class="small muted" style="margin-top:16px">Need help? Check your email spelling and whether Caps Lock is on.</p>
    <p style="margin-top:12px"><a href="<?= url('register.php', $next ? ['next' => $next] : []) ?>" class="link-btn">New here? Create an account →</a></p>
    <p class="tiny muted" style="margin-top:12px">RockOn is for adults aged 18 and over.</p>
    <div class="alert info small" style="margin-top:18px">
      <strong>Demo accounts</strong> (password <code><?= DEMO_PASSWORD ?></code>): maya@rockon.app (host), ojaswi@rockon.app (member), karl@rockon.app (moderator).
    </div>
  </div>
</div>
<?php page_footer();
