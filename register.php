<?php
/** Screen 2b — Registration (FR-01): four fields + required 18+ self-declaration. */
require __DIR__ . '/includes/bootstrap.php';

if (me()) redirect('dashboard.php');

$next = safe_next($_GET['next'] ?? $_POST['next'] ?? null);
$v = ['name' => '', 'email' => '', 'adult' => false];
$errors = [];

if (is_post()) {
    csrf_check();
    $v = [
        'name' => post('name'), 'email' => post('email'),
        'password' => (string)($_POST['password'] ?? ''), 'confirm' => (string)($_POST['confirm'] ?? ''),
        'adult' => !empty($_POST['adult']),
    ];
    $r = svc_register($v);
    if ($r['ok']) {
        flash('Account created. Welcome to RockOn!');
        header('Location: ' . base() . ($next ?? 'profile.php?id=me'));
        exit;
    }
    $errors = $r['errors'];
}

$err = fn($k) => !empty($errors[$k]) ? '<span class="err" id="reg-' . $k . '-err">' . h($errors[$k]) . '</span>' : '';
$inv = fn($k) => !empty($errors[$k]) ? 'aria-invalid="true" aria-describedby="reg-' . $k . '-err"' : '';
$firstError = $errors ? array_key_first($errors) : null;

page_header('Create your account');
?>
<div class="container auth">
  <div>
    <span class="tag amber">A local circle, one plan at a time</span>
    <h1 style="margin-top:20px">New city.<br>New connections.</h1>
    <p class="muted" style="font-size:17px;margin-top:16px">Join students, newcomers and young professionals making room for a little more community.</p>
    <img class="photo" src="<?= asset('img/friends.jpg') ?>" alt="A cheerful group selfie">
    <p class="slogan">“Bored? Meet new people. Socialize!!”</p>
  </div>
  <div class="card">
    <h2 style="font-size:36px;color:var(--crimson);margin-bottom:10px">Create your account</h2>
    <p class="muted" style="margin-bottom:20px">A few details now. Your interests and bio can come later.</p>
    <form class="form-grid" method="post" novalidate <?= $firstError ? 'data-focus="reg-' . h($firstError) . '"' : '' ?>>
      <?= csrf_field() ?>
      <?php if ($next): ?><input type="hidden" name="next" value="<?= h($next) ?>"><?php endif; ?>
      <div class="field">
        <label for="reg-name">Name</label>
        <input id="reg-name" name="name" class="input" autocomplete="name" placeholder="Nom Prenom" value="<?= h($v['name']) ?>" <?= $inv('name') ?>>
        <?= $err('name') ?>
      </div>
      <div class="field">
        <label for="reg-email">Email address</label>
        <input id="reg-email" name="email" class="input" type="email" autocomplete="email" placeholder="nom.prenom@example.com" value="<?= h($v['email']) ?>" <?= $inv('email') ?>>
        <?= $err('email') ?>
      </div>
      <div class="field">
        <label for="reg-password">Password</label>
        <div class="input-wrap">
          <input id="reg-password" name="password" class="input" type="password" autocomplete="new-password" aria-describedby="reg-pw-hint" <?= !empty($errors['password']) ? 'aria-invalid="true"' : '' ?> data-pw-meter>
          <button type="button" class="link-btn show" data-toggle-pw="reg-password" aria-pressed="false" aria-label="Show password">Show</button>
        </div>
        <span class="hint" id="reg-pw-hint">Use at least <?= MIN_PASSWORD ?> characters, ideally a unique passphrase.<span data-pw-meter-out></span></span>
        <?= $err('password') ?>
      </div>
      <div class="field">
        <label for="reg-confirm">Confirm password</label>
        <div class="input-wrap">
          <input id="reg-confirm" name="confirm" class="input" type="password" autocomplete="new-password" <?= $inv('confirm') ?>>
          <button type="button" class="link-btn show" data-toggle-pw="reg-confirm" aria-pressed="false" aria-label="Show password">Show</button>
        </div>
        <?= $err('confirm') ?>
      </div>
      <div class="field">
        <label class="check" for="reg-adult">
          <input id="reg-adult" name="adult" type="checkbox" value="1" <?= $v['adult'] ? 'checked' : '' ?> aria-describedby="reg-adult-hint" <?= !empty($errors['adult']) ? 'aria-invalid="true"' : '' ?>>
          I confirm I am 18 or older *
        </label>
        <span class="hint" id="reg-adult-hint">Required before account creation. This is a self-declaration, not identity verification.</span>
        <?= $err('adult') ?>
      </div>
      <div><button class="btn btn-primary btn-lg" type="submit">Create account →</button></div>
      <p class="small">Already have an account? <a href="<?= url('login.php') ?>" class="link-btn">Log in</a></p>
    </form>
  </div>
</div>
<?php page_footer();
