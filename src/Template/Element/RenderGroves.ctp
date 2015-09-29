<?php
  $hasGrove = $app['hasGrove'];
?>

<section class="ff-grove-box">
  <?php if ($hasGrove) : ?>
    <h1>User ID [<?= $theGrove ?>]</h1>
  <?php else : ?>
    <h1>Forest Nodes</h1>
  <?php endif; ?>

  <?php
    foreach ($nodes as $node) :
      echo $this->element('NodeBox', ['app' => $app, 'theGrove' => $theGrove, 'node' => $node]);
    endforeach;
  ?>
</section>
