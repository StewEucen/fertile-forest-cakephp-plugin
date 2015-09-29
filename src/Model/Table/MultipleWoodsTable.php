<?php

namespace FertileForest\Model\Table;

use Cake\ORM\Table;

class MultipleWoodsTable extends Table
{
  public function initialize(array $config) {
    $this->addBehavior('FertileForest.FertileForest',
      [
        'FertileForest' => [
          'grove' => 'user_id',

          'errors' => [
            'append.emptyColumn'      => '(overwritten at Table) When has grove field, must set this fields.',
            'append.canNotScootsOver' => '(overwritten at Table) No space to append at queue, and can not scoots over.',
            'append.baseNodeIsNull'   => '(overwritten at Table) Not found base node to append.',
          ],
        ],
      ]
    );
  }
}
