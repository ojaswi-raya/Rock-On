<?php
require __DIR__ . '/includes/bootstrap.php';
http_response_code(404);
page_header('Page not found');
?>
<div class="container"><div class="card" style="max-width:600px">
  <h1>That page wandered off</h1>
  <p class="muted" style="margin:10px 0 18px">The link may be old, or the page was moved.</p>
  <a href="<?= url('explore.php') ?>" class="btn btn-primary">Go to Explore</a>
</div></div>
<?php page_footer();
