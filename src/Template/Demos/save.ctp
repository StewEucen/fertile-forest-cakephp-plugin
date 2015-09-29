<?php
  $isGroveType = $app['modelName'] === 'MultipleWoods';

  $linkText = $isGroveType ? 'Multiple Woods' : 'Single Woods';
  $action = $isGroveType ? 'multipleWoods' : 'singleWoods';
?>
<div style="padding:2rem 5rem;">
  <div><?= $this->Html->link(
    "Back to {$linkText}",
    ['controller' => 'Demos', 'action' => $action]
  ); ?></div>
</div>
