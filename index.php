<?php
require __DIR__ . '/includes/bootstrap.php';

$me = me();
if ($me) redirect($me['kind'] === 'admin' ? 'admin/index.php' : 'explore.php');

$next = array_slice(sort_by_start(array_filter(db()['activities'], 'is_upcoming')), 0, 3);
page_header('Meet new people', 'index.php');
?>
<div class="container">
  <section class="hero">
    <div>
      <span class="tag amber">Bored? Meet new people. Socialize!!</span>
      <h1 style="margin:16px 0">Good company,<br>closer than you think.</h1>
      <p class="muted" style="font-size:18px;max-width:520px">
        RockOn helps students, newcomers and young professionals find casual plans nearby — a park walk, a picnic,
        a slightly weird game — and meet the people behind them. Peer ratings after every meetup keep it friendly and safe.
      </p>
      <div class="row" style="margin-top:24px">
        <a href="<?= url('explore.php') ?>" class="btn btn-primary btn-lg">Browse activities</a>
        <a href="<?= url('register.php') ?>" class="btn btn-outline btn-lg">Create a free account</a>
      </div>
    </div>
    <div class="collage">
      <img src="<?= asset('img/picnic.jpg') ?>" alt="A picnic set up on a hill overlooking a lake at sunset">
      <img src="<?= asset('img/sunrise.jpg') ?>" alt="A group watching the sunrise">
      <img src="<?= asset('img/blindgame.jpg') ?>" alt="Friends playing a blindfolded tasting game">
    </div>
  </section>

  <section class="steps" aria-label="How it works" style="margin-bottom:40px">
    <?php foreach ([
        ['1', 'Find or host a plan', 'Browse the list or the map, or publish your own in under two minutes.'],
        ['2', 'Request, chat, meet', 'Join instantly or ask the host. Chat with the group before you meet.'],
        ['3', 'Rate the company', 'Afterwards, attendees rate each other. Ratings stay hidden until both have rated.'],
    ] as [$n, $t, $d]): ?>
      <div class="card step"><div class="n"><?= $n ?></div><h3 style="margin:8px 0 6px"><?= h($t) ?></h3><p class="muted"><?= h($d) ?></p></div>
    <?php endforeach; ?>
  </section>

  <section aria-labelledby="soon">
    <div class="row between" style="margin-bottom:16px">
      <h2 id="soon">Coming up soon</h2>
      <a href="<?= url('explore.php') ?>" class="link-btn">See all on Explore →</a>
    </div>
    <div class="act-list"><?php foreach ($next as $a) activity_card($a); ?></div>
  </section>
</div>
<?php page_footer();
