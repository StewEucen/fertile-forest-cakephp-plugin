<?= $this->Html->css('FertileForest.FertileForest'); ?>

<div class="ff-layout-frame">
  <div class="ff-interactive-pane">
    <div class="ff-result-box"><?= $this->element('ResultOfMethods', ['app' => $app]); ?></div>
    <section class="ff-input-box"><?= $this->element('AddRootNodeForm', ['app' => $app]); ?></section>
  </div>
  <div class="ff-tree-data-pane"><?= $this->element('RendrForestNodes', ['app' => $app]); ?></div>
</div>
