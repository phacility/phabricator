<div class="oblivious-post-list">
  <?php

  foreach ($posts as $post) {
    echo $post->renderWithSummary();
  }

  ?>
</div>
<div class="oblivious-pager">
  <?php echo $older; ?>
  <?php echo $newer; ?>
</div>
