<?php
  $formEntity = $app['formEntity'];
  $hasGrove   = $app['hasGrove'];
  $modelName  = $app['modelName'];
?>
<h1>Add Root Node<?php if ($hasGrove) : ?><br>(of User ID)<?php endif; ?></h1>
<?= $this->Form->create($formEntity, ['url' => ['action' => 'save' . $modelName]]); ?>
  <?php
    echo $this->Form->input('title',      ['label' => 'Node Title', 'value' => '', 'required' => true, 'placeholder' => 'Root Node Title']);
    echo $this->Form->input('ff_base_id', ['type' => 'hidden', 'value' => 0]);
    if ($hasGrove) {
      echo $this->Form->input('user_id', ['label' => 'User ID', 'type' => 'number', 'min' => 1, 'value' => 1, 'required' => true]);
    }
  ?>
  <?= $this->Form->submit("Add Root Node"); ?>
<?= $this->Form->end(); ?>
