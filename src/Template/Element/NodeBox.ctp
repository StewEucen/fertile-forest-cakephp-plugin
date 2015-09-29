<?php
  $formEntity = $app['formEntity'];
  $modelName  = $app['modelName'];
  $allIDs = $app['allIDs'];

  $id = (int)$node->id;
  $depth = (int)$node->ff_depth;
  $queue = (int)$node->ff_queue;
  $title = $node->title;

  $parent = $node->nestParentID();
  $children = join(',', $node->nestChildIDs());

  $allIDsString = join('&amp;',
      array_map(
          function($id) {return "p2[]={$id}";},
          $allIDs
      )
  );

  $isCommandID = $id === $app['commandBaseNodeID'];
  $isRootID = $depth === 0;
?>
<div class="ff-node-box
  <?= $isCommandID ? 'ff-highlight-node' : '' ?>
  <?= $isRootID ? 'ff-root-node' : '' ?>
" style="margin-left: <?= $depth ?>rem;">
  <div class="ff-node-box-wrap-label">
    <div style="-webkit-flex:1;flex:1;">[<?= $id ?>] <?= $title ?></div>
    <div style="width: 150px;">Q(<?= $queue ?>)</div>
    <div style="width: 80px;">D(<?= $depth ?>)</div>
  </div>
  <?= $this->Form->create($formEntity, ['url' => ['action' => 'save' . $modelName]]); ?>
    <?= $this->Form->input('user_id', ['type' => 'hidden', 'value' => $theGrove]); ?>
    <?= $this->Form->input('ff_base_id', ['type' => 'hidden', 'value' => $id]); ?>

    <div class="ff-node-box-form-layout">
      <?=
        $this->Form->input('title',
          [
            'label' => '',
            'value' => '',
            'required' => true,
            'placeholder' => 'Title of Child Node to add'
          ]
        );
      ?>
      <?= $this->Form->submit("Add Child Node"); ?>
      <?= $this->Form->input('ff_kinship',
        ['label' => 'as sibling', 'type' => 'checkbox', 'value' => 'true']
      ); ?>
    </div>
  <?= $this->Form->end(); ?>
  <div class="ff-node-box-commands">
    <h2>Menu of Node Medhods</h2><br>
    <div class="ff-node-box-commands-links">
      <a href="?command=ancestors&amp;p1=<?= $id ?>">ancestors</a>
      <a href="?command=root&amp;p1=<?= $id ?>">root</a>
      <a href="?command=genitor&amp;p1=<?= $id ?>">genitor</a>
      <a href="?command=grandparent&amp;p1=<?= $id ?>">grandparent</a>
      <hr>
      <a href="?command=children&amp;p1=<?= $id ?>">children</a>
      <a href="?command=descendants&amp;p1=<?= $id ?>">descendants</a>
      <a href="?command=leaves&amp;p1=<?= $id ?>">leaves</a>
      <a href="?command=siblings&amp;p1=<?= $id ?>">siblings</a>
      <a href="?command=nthChild&amp;p1=<?= $id ?>&amp;p2=0">nthChild</a>
      <a href="?command=grandchildren&amp;p1=<?= $id ?>">grandchildren</a>
      <hr>
      <a href="?command=isRoot&amp;p1=<?= $id ?>">isRoot</a>
      <a href="?command=hasDescendant&amp;p1=<?= $id ?>">hasDescendant</a>
      <a href="?command=isLeaf&amp;p1=<?= $id ?>">isLeaf</a>
      <a href="?command=isInternal&amp;p1=<?= $id ?>">isInternal</a>
      <a href="?command=hasSibling&amp;p1=<?= $id ?>">hasSibling</a>
      <a href="?command=isOnlyChild&amp;p1=<?= $id ?>">isOnlyChild</a>
      <a href="?command=isDescendant&amp;p1=<?= $id ?>&amp;<?= $allIDsString ?>">isDescendant</a>
      <a href="?command=isAncestor&amp;p1=<?= $id ?>&amp;<?= $allIDsString ?>">isAncestor</a>
      <hr>
      <a href="?command=extinguish&amp;p1=<?= $id ?>">extinguish</a>
      <a href="?command=remove&amp;p1=<?= $id ?>">remove</a>
      <a href="?command=terminalize&amp;p1=<?= $id ?>">terminalize</a>
      <hr>
      <a href="?command=height&amp;p1=<?= $id ?>">height</a>
      <a href="?command=size&amp;p1=<?= $id ?>">size</a>
    </div>
  </div>
</div>
