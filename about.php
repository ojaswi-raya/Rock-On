<?php
require __DIR__ . '/includes/bootstrap.php';
page_header('About', 'about.php');
?>
<div class="container" style="max-width:900px">
  <h1>Made by newcomers, for newcomers</h1>
  <p class="muted" style="font-size:17px;margin:12px 0 28px">
    RockOn started with three international students who arrived in a new city and found it hard to meet people for simple plans.
    Group chats and social media aren’t built for “park walk at 5 pm, who’s in?” — so we built something that is.
  </p>
  <div class="two-col even">
    <section class="card">
      <h3>How trust works</h3>
      <ul style="padding-left:18px;display:grid;gap:8px;margin-bottom:0">
        <li>Only people who attended an activity can rate it, within 7 days.</li>
        <li>Ratings stay hidden until both people have rated each other, so nobody can “revenge rate”.</li>
        <li>Your reputation is the mean of released ratings on punctuality, respect and fun.</li>
        <li>Hosts can ask for a minimum reputation to join.</li>
      </ul>
    </section>
    <section class="card">
      <h3>Staying safe</h3>
      <ul style="padding-left:18px;display:grid;gap:8px;margin-bottom:0">
        <li>RockOn is for adults aged 18 and over.</li>
        <li>Meet in public places, and tell a friend where you’re going.</li>
        <li>Chat opens only for accepted participants.</li>
        <li>Report or block anyone who makes you uncomfortable. Moderators review every report.</li>
      </ul>
    </section>
  </div>
  <section class="card" style="margin-top:24px">
    <h3>The team</h3>
    <p class="muted" style="margin-top:8px">Smriti G C, Karl and Ojaswi Rayamajhi · Software Architecture, fall 2026 (instructor: Camara Omar).</p>
  </section>
</div>
<?php page_footer();
