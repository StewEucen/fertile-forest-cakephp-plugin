<?php

namespace FertileForest\Model\Table;

use Cake\ORM\Table;

class SingleWoodsTable extends Table
{
  public function initialize(array $config) {
    $this->addBehavior('FertileForest.FertileForest');
  }
}
