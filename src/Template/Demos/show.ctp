<?php
  $hasGrove = $app['hasGrove'];
?>
<section style="padding:.5rem 1rem;">
  <h1><?= $hasGrove ? 'Multiple Woods (To Use Grove Field)' : 'Single Woods'; ?></h1>

  <div style="padding:.5rem 5rem;"><?=
    $this->Html->link(
      "Go to Index",
      ['controller' => 'Demos', 'action' => 'index']
  ); ?></div>

</section>

<?= $this->element('ForestDemo', ['app' => $app]); ?>
