<?php
/**
 * Fertile Forest: The new model to store hierarchical data in a database.
 * Copyright (c) 2015 Stew Eucen (http://lab.kochlein.com)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    StewEucen
 * @category  CakePHP Behavior
 * @copyright Copyright (c) 2015 Stew Eucen (http://lab.kochlein.com)
 * @license   http://www.opensource.org/licenses/mit-license.php  MIT License
 * @link      http://lab.kochlein.com/FertileForest
 * @package   FertileForest\Model\Behavior
 * @since     File available since Release 1.0.0
 * @version   Release 1.0.0
 */

namespace FertileForest\Model\Behavior;

use Cake\Datasource\ConnectionManager;
use Cake\Event\Event;
use Cake\ORM\Behavior;
use Cake\ORM\Entity;

use RuntimeException;

class FertileForestBehavior extends Behavior
{
  const CONFIG_KEY = 'FertileForest';

  const ROOT_DEPTH = 0;
  const ORDER_BY_QUEUE_INDEX = false;
  const ORDER_BY_DEPTH_INDEX = true;

  const APPEND_BASE_ID_FIELD = 'ff_base_id';
  const APPEND_KINSHIP_FIELD = 'ff_kinship';
  const APPEND_NODE_RELATION_AS_LAST_CHILD    = -1;
  const APPEND_NODE_RELATION_AS_ELDER_SIBLING = false;

  // result to scoot over
  const SPROUT_VACANT_QUEUE_KEY   = 'vacantQueue';   // new queue to sprout
  const EVENIZE_AFFECTED_ROWS_KEY = 'affectedRows';  // number of scooted over nodes

  const ANCESTOR_ONLY_PARENT = 1;
  const ANCESTOR_ONLY_ROOT   = -1;
  const ANCESTOR_ALL         = 0;

  const DESCENDANTS_ALL        = 0;
  const DESCENDANTS_ONLY_CHILD = 1;

  const SUBTREE_DESCENDANTS_ONLY = false;
  const SUBTREE_WITH_TOP_NODE    = true;

  // recommended queue interval for sprouting node
  const QUEUE_DEFAULT_INTERVAL  = 0x8000;
  const QUEUE_MAX_VALUE         = 0x7fffffff;   // 2147483647
  //const QUEUE_DEFAULT_INTERVAL  = 3;      // if you want to see an effect of queue interval, use this values.
  //const QUEUE_MAX_VALUE         = 15;

  protected $_id;
  protected $_queue;
  protected $_depth;
  protected $_grove;
  protected $_softDelete;

  protected $_hasGrove;          // has grove column in table (set in initialize())
  protected $_hasSoftDelete;     // has soft delete column in table
  protected $_enableGroveDelete; // enable to use soft delete by grove

  /**
   * $_defaultConfig will be merged automatically in Table::initialize().
   * Merged config data can be getten by $this->_config
   * Cf. RoR: ff_parse_options!()
   *
   * How to change config in each Table::initialize()
   *    $this->addBehavior('FertileForest',
   *      [
   *        'FertileForest' => [
   *          'depth' => 'dd',
   *          'queue' => 'qq',
   *          'grove' => 'user_id',
   *        ],
   *      ]
   *    );
   */
  protected $_defaultConfig = [
    self::CONFIG_KEY => [
      'id'    => 'id',        // default field name of id
      'grove' => 'ff_grove',  // default field name of grove
      'depth' => 'ff_depth',  // default field name of depth
      'queue' => 'ff_queue',  // default field name of queue

      'softDelete'  => 'deleted',   // default field name of soft delete
      'enableValue' => 0,           // enable value of soft delete
      'deleteValue' => 1,           // deleted value of soft delete

      'enableGroveDelete' => true,

      'subtreeLimitSize' => 1000,

      // recommended queue interval for appending node
      'queueInterval' => self::QUEUE_DEFAULT_INTERVAL,

      'connection' => 'default',    // Need connection for issuing raw query.

      'errors' => [
        'append.emptyColumn'      => 'When has grove field, must set this fields.',
        'append.canNotScootsOver' => 'No space to append at queue, and can not scoots over.',
        'append.baseNodeIsNull'   => 'Not found base node to append.',

        'restructure.emptyColumn'     => 'When has grove field, must set this fields.',
        'restructure.defferentGroves' => 'defferent groves.',
        'restructure.graftIntoOwn'    => 'graft into own subtree.',

        'restructure.areNotSiblings' => 'There is no sibling.',
      ],
      // $this->_setErrors($postedNode, $this->_grove, 'restructure.emptyColumn');
    ],

    'implementedMethods' => [
      // accessors系
      'hasGrove'          => 'hasGrove',
      'hasSoftDelete'     => 'hasSoftDelete',
      'enableGroveDelete' => 'enableGroveDelete',

      // append node actions
      'sprout' => 'sprout',

      // delete node actions
      'remove'     => 'remove',
      'prune'      => 'prune',
      'extinguish' => 'extinguish',
      'pollard'    => 'pollard',

      // restructure
      'graft'   => 'graft',
      'permute' => 'permute',

      'moveTo' => 'moveTo',
      'moveBy' => 'moveBy',

      'normalize'   => 'normalize',
      'normalizeDepth' => 'normalizeDepth',

      // boolean methods
      'areSiblings'   => 'areSiblings',
      'isRoot'        => 'isRoot',
      'hasDescendant' => 'hasDescendant',
      'isLeaf'        => 'isLeaf',
      'isInternal'    => 'isInternal',
      'isDescendant'  => 'isDescendant',
      'isAncestor'    => 'isAncestor',
      'hasSibling'    => 'hasSibling',
      'isOnlyChild'   => 'isOnlyChild',

      // general finding.
      'groveNodes' => 'groveNodes',
      'groves'  => 'groves',
      'roots'   => 'roots',

      // To find ancestors.
      'trunk'       => 'trunk',
      'ancestors'   => 'ancestors',
      'genitor'     => 'genitor',
      'root'        => 'root',
      'grandparent' => 'grandparent',

      // To find descendants.
      'subtree'     => 'subtree',
      'descendants' => 'descendants',
      'children'    => 'children',
      'siblings'    => 'siblings',
      'nthChild'    => 'nthChild',
      'leaves'      => 'leaves',
      'internals'   => 'internals',

      'grandchildren'  => 'grandchildren',
      'elderSibling'   => 'elderSibling',
      'youngerSibling' => 'youngerSibling',
      'offsetSibling'  => 'offsetSibling',
      'nthSibling'     => 'nthSibling',

      // tools
      'nestedNodes' => 'nestedNodes',
      'nestedIDs'   => 'nestedIDs',

      'height' => 'height',
      'size'   => 'size',

      // a.k.a.
      'append'      => 'sprout',
      'terminalize' => 'pollard',
      'transfer'    => 'graft',
      'reorder'     => 'permute',
      'superiors'   => 'ancestors',
      'forebears'   => 'ancestors',
      'inferiors'   => 'descendants',
      'afterbears'  => 'descendants',
      'externals'   => 'leaves',
      'terminals'   => 'leaves',
      'parent'      => 'genitor',
    ],

    'implementedFinders' => [
    ],
  ];

  //////////////////////////////////////////////////////////////////////////////

  /**
   * Override for interrupt processing in initialize method.
   *
   * @param  array $config Assigned configurations of behavior.
   * @return void
   * @since  Release 1.0.0
   */
  public function initialize(array $config) {
    parent::initialize($config);
    $this->_initialize();
  }

  /**
   * Initialize hook method for Fertile Forest behavior.
   * Implement this method to avoid having to overwrite the initialize.
   *
   * @return void
   */
  protected function _initialize() {
    $this->_resolveAliasFields();
    $this->_setUpStatuses();
  }

  /**
   * Resolve aliases of Fertile Forest required fields.
   * Get field names from $this->settings[$model->alias][self::CONFIG_KEY].
   * Avoid to use extract, because security.
   *
   * @return void
   */
  protected function _resolveAliasFields() {
    $keys = [
      'id',
      'queue',
      'depth',
      'grove',
      'softDelete',
    ];
    foreach ($keys as $key) {
      $this->{"_{$key}"} = $this->_getConfig($key);
    }
  }

  /**
   * Set up some configurations to properties.
   *
   * @return void
   */
  protected function _setUpStatuses() {
    $this->_hasGrove          = $this->hasGrove();
    $this->_hasSoftDelete     = $this->hasSoftDelete();
    $this->_enableGroveDelete = $this->enableGroveDelete();
  }

  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////
  // Configs
  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////

  /**
   * Exists grove field in table.
   *
   * @return boolean true:has/false:none
   */
  public function hasGrove() {
    return $this->_hasField($this->_grove);
  }

  /**
   * Exists soft-delete field in table.
   *
   * @return boolean true:has/false:none
   */
  public function hasSoftDelete() {
    return $this->_hasField($this->_softDelete);
  }

  /**
   * Is enable to use soft-delete by grove field.
   *
   * @return boolean true:enable/false:disable
   */
  public function enableGroveDelete() {
    return $this->hasGrove()
      && !$this->hasSoftDelete()
      && $this->_getConfig('enableGroveDelete')
    ;
  }

  /**
   * Exists the filed in table of model.
   *
   * @return boolean true:has/false:none
   */
  protected function _hasField($field) {
    return $this->_table->hasField($field);
  }

  /**
   * Get recommended queue interval for appending node.
   * Can overwrite value of queue interval at setup()/initialize().
   *
   * @return int Queue interval value for appending.
   */
  protected function _getQueueInterval() {
    return $this->_getConfig('queueInterval');
  }

  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////
  // Utilities
  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////

  /**
   * Get the value from the configuration by specified key.
   *
   * @param  string $key Key of configuration.
   * @return mixed Value of configuration by specified key.
   */
  protected function _getConfig($key) {
    return $this->_config[self::CONFIG_KEY][$key];
  }

  /**
   * Get a database connection for raw query in Fertile Forest.
   *
   * @see    \Cake\Datasource\ConnectionManager::get()
   * @return \Cake\Database\Connection A connection object.
   * @throws \Cake\Datasource\Exception\MissingDatasourceConfigException When config data is missing.
   */
  protected function _getConnection() {
    return ConnectionManager::get($this->_getConfig('connection'));
  }

  /**
   * Transaction wrapper for finding and saving.
   * When return false in $func, $this->_table->connection()->transactional gives to rollback.
   *
   * @param  function $func Callback function to execute in transactional.
   * @return mixed Query result.
   */
  protected function _transactionWrapper($func) {
    return $this->_table->connection()->transactional($func);
  }

  /**
   * update all
   * solution of bug in CakePHP 3.0
   *
   * In case follows, There is a bug that table name can not has alias in UPDATE SQL.
   * $this->_table->updateAll($fields, $conditions)
   * Therefore, if alias of table name is defferent from simple case ignore,
   * UPDATE query will get error.
   *
   * This is the method for solution to make wrapper method for creating raw SQL.
   * When bug will be fixed in future, we obsolete this method.
   *
   * ORM\Query::updateAll() {
   *   $query = $this->query();
   *   $query->update()
   *       ->set($fields)
   *       ->where($conditions);
   *   $statement = $query->execute();
   *   $statement->closeCursor();
   *   $res = $statement->rowCount();
   * }
   *
   * We sent pull request to GitHub of CakePHP 3.x, and it has been adopted.
   * https://github.com/cakephp/cakephp/pull/6460
   *
   * Hereby, param of ORM\Query::query()->update() is worked.
   * Therefore, we had obsolete the method to create raw UPDATE SQL.
   *
   * @param array $predicates A hash of predicates for SET clause.
   * @param mixed $conditions Conditions to be used, accepts anything Query::where() can take.
   * @return int|boolean Count Returns the affected rows | false.
   */
  protected function _updateAll($predicates, $conditions) {
    $table = sprintf('%s %s', $this->_table->table(), $this->_table->alias());

    $statement = $this->_table->query()
      ->update($table)
      ->set($predicates)
      ->where($conditions)
      ->execute()
    ;

    $res = false;
    if ($statement->errorCode() === '00000') {
      $res = $statement->rowCount() ?: false;
    }
    $statement->closeCursor();

    return $res;
  }

  /**
   * Update all with ORDER BY clause.
   * Update() do not use order() in CakePHP 3.x.
   * This method is the solution to workaround it.
   *
   * @param array $predicates A hash of predicates for SET clause.
   * @param mixed $conditions Conditions to be used, accepts anything Query::where() can take.
   * @param array $order      Order clause.
   * @param array $prequeries Execute Prequeries before UPDATE for setting the specified local variables.
   * @return int|boolean Count Returns the affected rows | false.
   */
  protected function _updateAllInOrder($predicates, $conditions, $order, $prequeries = null) {
    // get connection to executeraw query (CakePHP 3.x)
    {
      $conn = $this->_getConnection();
      if (empty($conn)) {
        return false;
      }
    }

    $aimAlias = $this->_table->alias();
    $aimTable = $this->_table->table();

    $updateFields = [];
    foreach ($predicates as $key => $value) {
      if (is_numeric($key)) {
        $updateFields[] = $value;
      } else {
        $updateFields[] = "{$key} = {$value}";
      }
    }

    $updateConditions = [];
    foreach ($conditions as $key => $value) {
      if (is_numeric($key)) {
        $updateConditions[] = $value;
      } else {
        if (is_array($value)) {
          $value = join(',', $value);
          $updateConditions[] = "{$key} IN ({$value})";
        } else {
          if (1 < count(preg_split('/\s/', $key))) {
            $updateConditions[] = "{$key} {$value}";
          } else {
            $updateConditions[] = "{$key} = {$value}";
          }
        }
      }
    }

    $updateOrder = [];
    foreach ($order as $key => $value) {
      if (is_numeric($key)) {
        $updateOrder[] = "{$value} ASC";
      } else {
        $updateOrder[] = "{$key} {$value}";
      }
    }

    // pre-query to SET @xxx := value
    // can execuete two query() at once, however can not get affectedRows.
    foreach ((array)$prequeries as $query) {
      $statement = $conn->query($query);
    }

    // use raw query, because can not use ORDER BY in standard updateAll().
    $statement = $conn->query(
      join(
        ' ',
        [
          'UPDATE',
            "{$aimTable} AS {$aimAlias}",
          'SET',
            join(', ', $updateFields),
          'WHERE',
            join(' AND ', array_map(function($item) { return "({$item})"; }, $updateConditions)),
          'ORDER BY',
            join(', ', $updateOrder),
        ]
      )
    );

    $res = false;
    if ($statement->errorCode() === '00000') {
      $res = $statement->rowCount() ?: false;
    }
    $statement->closeCursor();

    return $res;
  }

  /**
   * Resolve func_get_args() as flatten array.
   *
   * @param array   $args         Invoker's func_get_args().
   * @param boolean $preserveKeys Keep hash keys in $args.
   * @return array Created flatten array from $args.
   */
  protected function _resolveArgs(array $args, $preserveKeys = false) {
    $values = [];
    array_walk_recursive($args,
      function($v, $k, $preserveKeys) use(&$values) {
        if ($preserveKeys) {
          $values[$k] = $v;
        } else {
          $values[] = $v;
        }
      },
      $preserveKeys
    );

    return $values;
  }

  /**
   * Resolve node from Entity|int params.
   *
   * @param  Entity|int|array $nodes To identify the nodes.
   * @param boolean $refresh true:Refind each Entity by id.
   * @return Entity|array When $nodes is array, return value is array too.
   */
  protected function _resolveNodes($nodes, $refresh = false) {
    if (empty($nodes)) {
      return is_array($nodes) ? [] : null;
    }

    // (array)Entity can not create [Entity]
    $isPlural = is_array($nodes);
    $nodeList = $isPlural ? $nodes : [$nodes];

    $idKey = $this->_id;

    $resEntities = [];
    $refindIDs = [];
    foreach ($nodeList as $item) {
      $isEntity = $item instanceof Entity;
      if ($isEntity) {
        $theID = $item->{$idKey};
      } else {
        $theID = $item;
      }

      if (!$isEntity || $refresh) {
        $refindIDs[] = $theID;
        $resEntities += [$theID => null];  // avert to overwrite by +=
      } else {
        $resEntities[$theID] = $item;
      }
    }

    /**
     * get node ordered by id
     *
     * Cake\Database\Expression\QueryExpression
     * public function in($field, $values, $type = null)
     * $this->_table->query()->func()->in()
     */
    if (!empty($refindIDs)) {
      $conditions = $this->_conditionsScope(
        null,     // not use grove
        ["{$this->_id} IN" => $refindIDs]
      );

      $aimQuery = $this->_table->find()
        ->select($this->_fieldsScope())
        ->where($conditions)
      ;

      foreach ($aimQuery as $node) {
        $resEntities[$node->{$idKey}] = $node;
      }
    }

    return $isPlural ? $resEntities : reset($resEntities);
  }

  /**
   * Create conditions scope.
   *
   * @param  int   $grove         Grove id.
   * @param  array $addConditions Additional conditions.
   * @return array Required conditions for WHERE clause.
   */
  protected function _conditionsScope($grove = null, $addConditions = []) {
    $resConditions = [];

    if ($this->_hasSoftDelete) {
      $resConditions[$this->_softDelete] = $this->_getConfig('enableValue');
    }

    if ($this->_hasGrove) {
      if (!empty($grove)) {
        $resConditions[$this->_grove] = $grove;
      } elseif ($this->_enableGroveDelete) {
        $resConditions["{$this->_grove} >"] = 0;
      }
    }

    return array_merge($addConditions, $resConditions);
  }

  /**
   * Create order scope by SQL indexes
   * self::ORDER_BY_QUEUE_INDEX  false     softDelete, grove, queue
   * self::ORDER_BY_DEPTH_INDEX  true      softDetete, grove, depth, queue
   *
   * @param boolean $isDescendant true:ASC/false:DESC
   * @param boolean $isDepthIndex Unsing index true:ff_depth_index/false:ff_queue_index
   * @return array Required fields to use index of DB table for ORDER BY clause.
   */
  protected function _orderScope(
    $isDescendant = false,
    $isDepthIndex = self::ORDER_BY_QUEUE_INDEX
  ) {

    $direction = $isDescendant ? 'DESC' : 'ASC';
    return array_filter([
      $this->_softDelete => $this->_hasSoftDelete ? $direction : false,
      $this->_grove      => $this->_hasGrove      ? $direction : false,
      $this->_depth      => $isDepthIndex         ? $direction : false,
      $this->_queue      =>                         $direction,
    ]);
  }

  /**
   * Create values of SELECT clause with required fields for nesting.
   *
   * @param array $optionFields Option fields for SELET clause.
   * @return array Values of SELECT clause with required fields for nesting.
   */
  protected function _fieldsScope($optionFields = []) {
    if (is_null($optionFields) || $optionFields === '*') {
      return $this->_table->schema()->columns();    // all fields
    }

    $requiredFileds = [$this->_id, $this->_queue, $this->_depth];
    if ($this->_hasGrove) {
      $requiredFileds[] = $this->_grove;
    }

    // Required fields have priority for nesting.
    return array_unique(
      array_merge((array)$optionFields, $requiredFileds),
      SORT_STRING
    );
  }

  /**
   * Set errors for saving Entity.
   *
   * @param Entity $postedNode The Entity which the error occurred.
   * @param array $field       The filed related to error.
   * @param array $messageKeys Keys of error message.
   * @return array|$this See \Cake\Datasource::errors().
   */
  protected function _setErrors($postedNode, $field, $messageKeys = []) {
    $addMessages = [];
    foreach ((array)$messageKeys as $key) {
      $errors = $this->_getConfig('errors');
      if (isset($errors[$key])) {
        $addMessages[] = $errors[$key];
      } else {
        $addMessages[] = $key;
      }
    }

    return $postedNode->errors($field, $addMessages);
  }

  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////
  // Savers
  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////

  /**
   * Sprout is alias of Table::save().
   *
   * @see     \Cake\ORM\Table::save().
   * @return  result of append
   */
  public function sprout(Entity $postedNode, $options = []) {
    return $this->_table->save($postedNode);
  }

  /**
   * Before save listener.
   * Transparently manages setting the required fields for FertileForestBehavior
   * if the parent field is included in the parameters to be saved.
   *
   * @param \Cake\Event\Event $event The beforeSave event that was fired.
   * @param \Cake\ORM\Entity $entity The entity that is going to be saved.
   * @return boolean true:Continue to save./false:Abort to save.
   * @throws \RuntimeException if the parent to set for the node is invalid
   */
  public function beforeSave(Event $event, Entity $postedNode) {
    if ($postedNode->isNew()) {
      return $this->_beforeSaveToSprout($event, $postedNode);
    } else {
      return $this->_beforeSaveToUpdate($event, $postedNode);
    }
  }

  /**
   * After save listener.
   * Do nothing here for FertileForestBehavior.
   *
   * @param \Cake\Event\Event $event The beforeSave event that was fired
   * @param \Cake\ORM\Entity $postedNode the entity that is going to be saved
   * @return void
   */
  public function afterSave(Event $event, Entity $postedNode) {
  }

  /**
   * before save as sprout (append a new node)
   *
   * @param \Cake\Event\Event $event The beforeSave event that was fired.
   * @param \Cake\ORM\Entity $postedNode the entity that is going to be saved.
   * @return boolean true:Continue to save./false:Abort to save.
   */
  protected function _beforeSaveToSprout(Event $event, Entity $postedNode) {
    // ff_grove check
    if ($this->_hasGrove) {
      // When has grove, must set ff_grove to save.
      $ff_grove = (int)$postedNode->{$this->_grove};
      if (empty($ff_grove)) {
        $this->_setErrors($postedNode, $this->_grove, 'append.emptyColumn');
        // throw new RuntimeException("Cannot set a node's parent as itself");
        return false;
      }
    }

    return $this->_fillRequiredFiledsToAppend($postedNode);
  }

  /**
   * before save as update
   *
   * Never change required fields of Fertile Forest to save().
   * Because FF-fields are often changed in other sessions.
   * FF-fields in Entity are not credit.
   *
   * Must keep ff_depth and ff_queue.
   * However can change ff_grove. because it is as user_id usually.
   * If want to validate ff_grove, must write code in Model::beforeSave().
   *
   * @param \Cake\Event\Event $event The beforeSave event that was fired.
   * @param \Cake\ORM\Entity $postedNode the entity that is going to be saved.
   * @return boolean true:Continue to save./false:Abort to save.
   */
  protected function _beforeSaveToUpdate(Event $event, Entity $postedNode) {
    // never change ff-fields by updating
    return is_null($postedNode->{$this->_queue}) && is_null($postedNode->{$this->_depth});
  }

  /**
   * Parse kinship from param.
   *
   * @param string $kinshipString When it is "true" or "false" it parsed as boolean. otherwise as int.
   */
  protected function _parseKinship($kinshipString = null) {
    $kinship = $kinshipString ?: self::APPEND_NODE_RELATION_AS_LAST_CHILD;
    if (preg_match('/true|false/i', $kinship)) {
      return strtolower($kinship) === 'true';
    } else {
      return (int)$kinship;
    }
  }

  /**
   * Fill required fields to append (for derived class of this).
   *
   * @param \Cake\ORM\Entity $postedNode the entity that is going to be saved.
   * @return boolean true:Continue to save./false:Abort to save.
   */
  protected function _fillRequiredFiledsToAppend(Entity $postedNode) {
    $baseID = (int)$postedNode->{self::APPEND_BASE_ID_FIELD};

    // calculate depth and queue for appending.
    // If no interval, try to scoot over queue.
    if (empty($baseID)) {
      $childNodeFillInfo = $this->_calcRequiredFieldsForAppendingAsRoot($postedNode);
    } else {
      $childNodeFillInfo = $this->_calcRequiredFieldsForAppendingAsInternal($postedNode);
    }

    // When fail to calc, can not save.
    if (empty($childNodeFillInfo)) {
      return false;     // no need to set error message here.
    }

    // not need to set ff_grove, because $postedNode has it already.
    $postedNode->{$this->_queue} = $childNodeFillInfo[$this->_queue];
    $postedNode->{$this->_depth} = $childNodeFillInfo[$this->_depth];

    return true;
  }

  /**
   * Calculate depth and queue as root node to append.
   *
   * @param \Cake\ORM\Entity $postedNode the entity that is going to be saved.
   * @return array|false Hash of required fields for posted node. When can not save, return false.
   */
  protected function _calcRequiredFieldsForAppendingAsRoot(Entity $postedNode) {
    $grove = (int)$postedNode->{$this->_grove};   // can be null

    // When append as root, need to post ff_grove.
    if ($this->_hasGrove && empty($grove)) {
      $this->_setErrors($postedNode, $this->_grove, 'append.emptyColumn');
      return false;
    }

    // depth is fixed value
    $childNodeFillInfo = [$this->_depth => self::ROOT_DEPTH];

    // calculate queue
    {
      // get max queue in grove
      $lastQueue = $this->_getLastQueue($grove);   // can be null

      if (is_null($lastQueue)) {
        $appendQueue = 0;
      } elseif (self::QUEUE_MAX_VALUE <= $lastQueue) {
        // try to scoot over pre-nodes.
        $evenizeRes = $this->_evenize($grove, null, null, 1);   // 1: append node count

        // When fail to evenize, filled all id.
        if (empty($evenizeRes)) {
          $this->_setErrors($postedNode, $this->_grove, 'append.canNotScootsOver');
          return false;
        }

        $appendQueue = $evenizeRes[self::SPROUT_VACANT_QUEUE_KEY];
      } elseif (self::QUEUE_MAX_VALUE - $lastQueue < $this->_getQueueInterval()) {
        $appendQueue = self::QUEUE_MAX_VALUE;
      } else {
        $appendQueue = $lastQueue + $this->_getQueueInterval();
      }

      $childNodeFillInfo[$this->_queue] = $appendQueue;
    }

    return $childNodeFillInfo;
  }

  /**
   * Calculate depth and queue as internal node to append.
   *  (1) has space before base-node, calc median queue.
   *  (2) When no space befre base node, try to evenize.
   *  (3) can not evenize, can not append.
   *
   * @param \Cake\ORM\Entity $postedNode the entity that is going to be saved.
   * @return array|false Hash of required fields for posted node. When can not save, return false.
   */
  protected function _calcRequiredFieldsForAppendingAsInternal(Entity $postedNode) {
    $baseID   = (int)$postedNode->{self::APPEND_BASE_ID_FIELD};
    $relation = $this->_parseKinship($postedNode->{self::APPEND_KINSHIP_FIELD});
    $grove    = (int)$postedNode->{$this->_grove};

    // get base node by ff_base_id
    // use ff_grove for find, because grove means USER_ID
    {
      $fields = $this->_fieldsScope();
      $conditions = $this->_conditionsScope($grove, [$this->_id => $baseID]);

      $baseNode = $this->_table->find()
        ->select($fields)
        ->where($conditions)
        ->first()
      ;

      // When has ff_base_id and the node is nothing, fail to append.
      if (empty($baseNode)) {
        $this->_setErrors($postedNode, self::APPEND_BASE_ID_FIELD, 'append.baseNodeIsNull');
        return false;
      }
    }

    $isSibling = is_bool($relation);

    $childNodeFillInfo = [$this->_depth => $baseNode->{$this->_depth} + ($isSibling ? 0 : 1)];

    // pick up node for wedged node to scoot over. (can be null)
    $wedgedNode = $this->_getWedgedNode($baseNode, $relation);

    // When wedged node is nothing, it means last queue.
    // In the case, calc appending queue is "lastQueue + INTERVAL"
    if (empty($wedgedNode)) {
      $lastQueue = $this->_getLastQueue($baseNode->{$this->_grove}, 0);

      if ($lastQueue < self::QUEUE_MAX_VALUE) {
        $queueInterval = $this->_getQueueInterval();

        if ($queueInterval <= self::QUEUE_MAX_VALUE - $lastQueue) {
          $calcQueue = $lastQueue + $queueInterval;
        } else {
          $calcQueue = self::QUEUE_MAX_VALUE;
        }

        return $childNodeFillInfo + [$this->_queue => $calcQueue];
      }
    } else {

      /**
       * When got wedged node, calc median queue.
       *  (1) get previous node of the wedge node.
       *  (2) calc median queue.
       */
      $appendQueue = $this->_calcMedianQueue($wedgedNode);
      if (!empty($appendQueue)) {
        return $childNodeFillInfo + [$this->_queue => $appendQueue];
      }
    }

    // When no space before wedged node, try to scoot over.
    {
      $appendQueue = $this->_evenizeForAppending($baseNode, $wedgedNode);
      if (!empty($appendQueue)) {
        return $childNodeFillInfo + [$this->_queue => $appendQueue];
      }
    }

    $this->_setErrors($postedNode, $this->_queue, 'append.canNotScootsOver');
    return false;
  }

  /**
   * Calculate median queue for appending.
   *
   * @param \Cake\ORM\Entity $wedgedNode Calculate median queue between wedged node and the before node.
   * @return int|null median queue.
   */
  protected function _calcMedianQueue(Entity $wedgedNode) {
    $tailNode = $this->_getPreviousNode($wedgedNode);

    // $tailNode never be null, because parent-node exists.
    if (empty($tailNode)) {
      return null;
    }

    $qq = $this->_queue;
    $tailQueue   = $tailNode->{$qq};
    $wedgedQueue = $wedgedNode->{$qq};

    if ($wedgedQueue - $tailQueue <= 1) {
      return null;
    }

    return (int)(($tailQueue + $wedgedQueue) / 2);
  }

  /**
   * Evenize for appending
   *  (1) Try to evenize between parent node and base node.
   *  (2) Try to evenize between root node and base node.
   *  (3) Try to evenize all pre-nodes before base node.
   *  (4) Try to evenize all post-nodes after base node.
   *  (5) Can not evenize.
   *
   * @param  \Cake\ORM\Entity|null $baseNode
   * @param  \Cake\ORM\Entity|null $wedgedNode
   * @return int|false Calculated queue for appending.|false:No space to append.
   */
  protected function _evenizeForAppending($baseNode, $wedgedNode) {
    $appendNodeCount = 1;

    $grove = empty($baseNode) ? null : $baseNode->{$this->_grove};

    $baseQueue   = empty($baseNode)   ? null : $baseNode  ->{$this->_queue};
    $wedgedQueue = empty($wedgedNode) ? null : $wedgedNode->{$this->_queue};

    // try to evenize all pre-nodes from this base node.
    {
      $evenizeRes = $this->_evenize($grove, $baseQueue, $wedgedQueue, $appendNodeCount);
      if (!empty($evenizeRes)) {
        if (0 < $evenizeRes[self::EVENIZE_AFFECTED_ROWS_KEY]) {
          return $evenizeRes[self::SPROUT_VACANT_QUEUE_KEY];
        }
      }
    }

    // try to evenize all pre-nodes from this root node.
    //{
    //  $rootNode = $this->root($baseNode);
    //  $evenizeRes = $this->_evenize($grove, $rootNode, $wedgedQueue, $appendNodeCount);
    //  if (!empty($evenizeRes)) {
    //    return $evenizeRes[self::SPROUT_VACANT_QUEUE_KEY];
    //  }
    //}

    // try to evenize all pre-nodes.
    {
      $evenizeRes = $this->_evenize($grove, null, $wedgedQueue, $appendNodeCount);
      if (!empty($evenizeRes)) {
        if (0 < $evenizeRes[self::EVENIZE_AFFECTED_ROWS_KEY]) {
          return $evenizeRes[self::SPROUT_VACANT_QUEUE_KEY];
        }
      }
    }

    // try to evenize all post-nodes.
    {
      $evenizeRes = $this->_evenize($grove, $wedgedQueue, null, $appendNodeCount, true);
      if (!empty($evenizeRes)) {
        if (0 < $evenizeRes[self::EVENIZE_AFFECTED_ROWS_KEY]) {
          return $evenizeRes[self::SPROUT_VACANT_QUEUE_KEY];
        }
      }
    }

    // can not evenize.
    return false;
  }

  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////
  // Finders
  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////

  /**
   * Find trunk (= ancestor) nodes from base node in ordered range.
   *
   * @param Entity|int $baseObj Base node|id to find.
   * @param int        $range   Ordered range of trunk nodes. -1:To designate as root node.
   * @param array      $fields  Fields for SELECT clause.
   * @return Query|null Basic query for finding trunk nodes.
   */
  public function trunk($baseObj, $range = self::ANCESTOR_ALL, $fields = null) {
    $baseNode = $this->_resolveNodes($baseObj);
    if (empty($baseNode)) {
      return null;
    }

    $aimGrove = $baseNode->{$this->_grove};
    $aimDepth = $baseNode->{$this->_depth};
    $aimQueue = $baseNode->{$this->_queue};

    if ($aimDepth == self::ROOT_DEPTH) {
      return null;
    }

    // create subquery to find queues of ancestor
    {
      $conditions = $this->_conditionsScope(
        $aimGrove,
        ["{$this->_queue} <" => $aimQueue]
      );

      if ($range < 0) {
        $conditions[$this->_depth] = self::ROOT_DEPTH;
      } else {
        $conditions["{$this->_depth} <"] = $aimDepth;
        if (0 < $range) {
          $conditions["{$this->_depth} >="] = $aimDepth - $range;
        }
      }

      $group = [$this->_depth];
      if ($this->_hasGrove) {
        array_unshift($group, $this->_grove);
      }
    }

    $aimQuery = $this->_table->find();
    $prevAncestorNodesSubquery = $aimQuery
      ->select(['ancestor_queue' => $aimQuery->func()->max($this->_queue)])
      ->where($conditions)
      ->group($group)
    ;

    // find nodes by ancestor queues
    {
      $resConditions = $this->_conditionsScope(
        $aimGrove,
        // must use IN(), because trunk() is for general purpose to find ancestors
        // When one row, can use "=". When plural, can not use "=".
        // Error: SQLSTATE[21000]: Cardinality violation: 1242 Subquery returns more than 1 row
        ["{$this->_queue} IN" => $prevAncestorNodesSubquery]
      );

      $resOrder = $this->_orderScope();
    }

    return $this->_table->find()
      ->select($this->_fieldsScope($fields))
      ->where($resConditions)
      ->order($resOrder)
    ;
  }

  /**
   * Find all ancestor nodes from base node (without base node)
   *
   * @param Entity|int $baseObj Base node|id to find.
   * @param array      $fields  Fields for SELECT clause.
   * @return Query|null Basic query for finding ancestor nodes.
   */
  public function ancestors($baseObj, $fields = null) {
    return $this->trunk($baseObj, self::ANCESTOR_ALL, $fields);
  }

  /**
   * Find genitor (= parent) node from base node.
   *
   * @param Entity|int $baseObj Base node|id to find.
   * @param array      $fields  Fields for SELECT clause.
   * @return Entity|null Genitor node.
   */
  public function genitor($baseObj, $fields = null) {
    $trunkQuery = $this->trunk($baseObj, self::ANCESTOR_ONLY_PARENT, $fields);
    if (empty($trunkQuery)) {
      return null;
    }

    return $trunkQuery->first();
  }

  /**
   * Find root node from base node.
   *
   * @param Entity|int $baseObj Base node|id to find.
   * @param array      $fields  Fields for SELECT clause.
   * @return Entity|null Root node. When base node is root, return base node.
   */
  public function root($baseObj, $fields = null) {
    $baseNode = $this->_resolveNodes($baseObj);
    if (empty($baseNode)) {
      return null;
    }

    if ($baseNode->{$this->_depth} == self::ROOT_DEPTH) {
      return $baseNode;
    }

    $trunkQuery = $this->trunk($baseNode, self::ANCESTOR_ONLY_ROOT, $fields);
    if (empty($trunkQuery)) {
      return null;
    }

    return $trunkQuery->first();
  }

  /**
   * Find grandparent node from base node.
   *
   * @param Entity|int $baseObj Base node|id to find.
   * @param array      $fields  Fields for SELECT clause.
   * @return Entity|null Grandparent node.
   */
  public function grandparent($baseObj, $fields = null) {
    $baseNode = $this->_resolveNodes($baseObj);
    if (empty($baseNode)) {
      return null;
    }

    $grandNumber = 2;

    $grandparentDepth = $baseNode->{$this->_depth} - $grandNumber;
    if ($grandparentDepth < self::ROOT_DEPTH) {
      return null;
    }

    $trunkQuery = $this->trunk($baseNode, $grandNumber, $fields);
    if (empty($trunkQuery)) {
      return null;
    }

    return $trunkQuery->first();
  }

  /**
   * Find subtree nodes from base node with ordered range.
    *
    * @param Entity|int $baseObj Base node|id to find.
    * @param int        $range   Ordered range of trunk nodes. -1:To designate as root node.
    * @param boolean    $withTop Include base node in return query.
    * @param array      $fields  Fields for SELECT clause.
    * @return Query|null Basic query for finding subtree nodes.
   */
  public function subtree(
    $baseObj,
    $range = 0,
    $withTop = self::SUBTREE_WITH_TOP_NODE,
    $fields = null
  ) {
    $baseNode = $this->_resolveNodes($baseObj);
    if (empty($baseNode)) {
      return [];
    }

    $conditions = $this->_createSubtreeConditions(
      $baseNode,
      $withTop,
      true      // use coalesce()
    );

    // paging for subtree
    $limited = $this->_limitedSubtreeDepth(
      $baseNode->{$this->_depth},
      $range,
      $conditions
    );

    if (0 < $limited) {
      $conditions["{$this->_depth} <="] = $limited;
    }

    return $this->_table->find()
      ->select($this->_fieldsScope($fields))
      ->where($conditions)
      ->order($this->_orderScope())
    ;
  }

  /**
   * Count each depth for pagination in finding subtree nodes.
   * @param int $baseDepth           Depth of base node.
   * @param int $range               Ordered depth offset.
   * @param Query $subtreeConditions WHERE clause for finding subtree to count.
   * @return int Max depth in query to find subtree nodes.
   */
  protected function _limitedSubtreeDepth($baseDepth, $range, $subtreeConditions) {
    $orderedDepth = ($range == 0
      ? 0
      : ($baseDepth + $range)
    );

    $depthCountKey = 'depth_count';

    $aimQuery = $this->_table->find();
    $countRes = $aimQuery
      ->select([
        $this->_depth,
        $depthCountKey => $aimQuery->newExpr('COUNT(*)'),
      ])
      ->where($subtreeConditions)
      ->group([$this->_depth])
    ;

    $limited = $this->_getConfig('subtreeLimitSize');

    $totalSize = 0;
    $limitedDepth = 0;
    foreach ($countRes as $depthEntity) {
      $aimDepth = $depthEntity->{$this->_depth};
      $aimCount = $depthEntity->{$depthCountKey};

      if ($limited < $totalSize += $aimCount) {
        break;
      }
      $limitedDepth = $aimDepth;
    }

    $limitedDepth = max($limitedDepth, $baseDepth + 1);

    if ($orderedDepth == 0) {
      return $limitedDepth;
    } else {
      return min($limitedDepth, $orderedDepth);
    }
  }

  /**
   * Find descendant nodes from base node.
   *
   * @param Entity|int $baseObj Base node|id to find.
   * @param array      $fields  Fields for SELECT clause.
   * @return Query|null Basic query for finding descendant nodes.
   */
  public function descendants($baseObj, $fields = null) {
    return $this->subtree(
      $baseObj,
      self::DESCENDANTS_ALL,
      self::SUBTREE_DESCENDANTS_ONLY,
      $fields
    );
  }

  /**
   * Find child nodes from base node.
   *
   * @param Entity|int $baseObj Base node|id to find.
   * @param array      $fields  Fields for SELECT clause.
   * @return Query|null Basic query for finding child nodes.
   */
  public function children($baseObj, $fields = null) {
    return $this->subtree(
      $baseObj,
      self::DESCENDANTS_ONLY_CHILD,
      self::SUBTREE_DESCENDANTS_ONLY,
      $fields
    );
  }

  /**
   * Find nth-child node from base node.
   *
   * @param Entity|int $baseObj Base node|id to find.
   * @param int        $nth     Order in child nodes.
   * @param array      $fields  Fields for SELECT clause.
   * @return Entity|null Nth-child node.
   */
  public function nthChild($baseObj, $nth = 0, $fields = null) {
    $childQuery = $this->children($baseObj, $fields);

    if ($nth < 0) {
      $nth = $childQuery->count() - 1;
      if ($nth < 0) {
        return null;
      }
    }

    return $childQuery->offset($nth)->first();
  }

  /**
   * Find grandchild nodes from base node.
   *
   * @param Entity|int $baseObj Base node|id to find.
   * @param array      $fields  Fields for SELECT clause.
   * @return Query|null Basic query for finding grandchild nodes.
   */
  public function grandchildren($baseObj, $fields = null) {
    $baseNode = $this->_resolveNodes($baseObj);
    if (empty($baseNode)) {
      return null;
    }

    $grandNumber = 2;

    return $this
      ->subtree(
        $baseNode,
        $grandNumber,
        self::SUBTREE_DESCENDANTS_ONLY,
        $fields
      )
      ->where([$this->_depth => $baseNode->{$this->_depth} + $grandNumber])
    ;
  }

  /**
   * Find sibling nodes from base node.
   *
   * @param Entity|int $baseObj Base node|id to find.
   * @param array      $fields  Fields for SELECT clause.
   * @return Query|null Basic query for finding sibling nodes.
   * @version 1.1.0 Update to one query style.
   */
  public function siblings($baseObj, $fields = null) {
    $baseNode = $this->_resolveNodes($baseObj);
    if (empty($baseNode)) {
      return null;
    }

    $aimGrove = $baseNode->{$this->_grove};
    $aimDepth = $baseNode->{$this->_depth};
    $aimQueue = $baseNode->{$this->_queue};

    if ($aimDepth == self::ROOT_DEPTH) {
      return null;
    }

    // create subquery to find queues of ancestor
    $aimQuery = $this->_table->find();
    $beforeNodesSubquery = $aimQuery
      ->select(['head_queue' => $aimQuery->newExpr("MAX({$this->_queue}) + 1")])
      ->where($this->_conditionsScope(
        $aimGrove,
        [
          "{$this->_queue} <" => $aimQueue,
          "{$this->_depth} <" => $aimDepth,
        ]
      ))
    ;

    $aimQuery = $this->_table->find();
    $afterNodesSubquery = $aimQuery
      ->select(['tail_queue' => $aimQuery->newExpr("MIN({$this->_queue}) - 1")])
      ->where($this->_conditionsScope(
        $aimGrove,
        [
          "{$this->_queue} >" => $aimQueue,
          "{$this->_depth} <" => $aimDepth,
        ]
      ))
    ;

    // find nodes by ancestor queues
    {
      $resConditions = $this->_conditionsScope(
        $aimGrove,
        [
          "{$this->_depth}" => $aimDepth,
          "{$this->_queue} >=" => $this->_table->query()->func()
                                    ->coalesce(
                                      [$beforeNodesSubquery, 0],
                                      ['literal', 'integer']
                                    ),
          "{$this->_queue} <=" => $this->_table->query()->func()
                                    ->coalesce(
                                      [$afterNodesSubquery, self::QUEUE_MAX_VALUE],
                                      ['literal', 'integer']
                                    ),
        ]
      );

      $resOrder = $this->_orderScope();
    }

    return $this->_table->find()
      ->select($this->_fieldsScope($fields))
      ->where($resConditions)
      ->order($resOrder)
    ;
  }

  /**
   * Find nth-sibling node from base node.
   *
   * @param Entity|int $baseObj Base node|id to find.
   * @param int        $nth     Order in child nodes.
   * @param array      $fields  Fields for SELECT clause.
   * @return Entity|null Nth-sibling node.
   */
  public function nthSibling($baseObj, $nth = 0, $fields = null) {
    $aimNode = $this->_resolveNodes($baseObj);
    if (empty($aimNode)) {
      // TODO: validate error
      return null;
    }

    $parentNode = $this->genitor($aimNode);
    if (empty($parentNode)) {
      // TODO: validate error
      return null;
    }

    return $this->nthChild($parentNode, $nth, $fields);
  }

  /**
   * Find elder sibling node from base node.
   *
   * @param Entity|int $baseObj Base node|id to find.
   * @param array      $fields  Fields for SELECT clause.
   * @return Entity|null Elder sibling node of base node.
   */
  public function elderSibling($baseObj, $fields = null) {
    return $this->offsetSibling($baseObj, -1, $fields);
  }

  /**
   * Find younger sibling node from base node.
   *
   * @param Entity|int $baseObj Base node|id to find.
   * @param array      $fields  Fields for SELECT clause.
   * @return Entity|null Younger sibling node of base node.
   */
  public function youngerSibling($baseObj, $fields = null) {
    return $this->offsetSibling($baseObj, 1, $fields);
  }

  /**
   * Find offsetted sibling node from base node.
   *
   * @param Entity|int $baseObj Base node|id to find.
   * @param int        $offset  Offset number from Base Node.
   * @param array      $fields  Fields for SELECT clause.
   * @return Entity|null Offsetted sibling node from base node.
   */
  public function offsetSibling($baseObj, $offset, $fields = null) {
    $aimNode = $this->_resolveNodes($baseObj);
    if (empty($aimNode)) {
      // TODO: validate error
      return null;
    }

    $parentNode = $this->genitor($aimNode);
    if (empty($parentNode)) {
      // TODO: validate error
      return null;
    }

    $siblingNodes = $this->children($parentNode, [$this->_id])->toArray();
    if (empty($siblingNodes)) {
      // TODO: validate error
      return null;
    }

    $aimID = $aimNode->{$this->_id};

    $nth = null;
    foreach ($siblingNodes as $i => $node) {
      if ($node->{$this->_id} == $aimID) {
        $nth = $i;
        break;
      }
    }

    if (is_null($nth)) {
      // TODO: validate error
      return null;
    }

    if ($nth + $offset < 0) {
      // OFFSET -1 make an error
      // Error: SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax;
      // check the manual that corresponds to your MySQL server version for the right syntax to use near '-1' at line 1
      return null;
    }

    return $this->nthChild($parentNode, $nth + $offset, $fields);
  }

  /**
   * Find leaf nodes from base node.
   *
   * @param  Entity|int $baseObj Base node|id to find.
   * @param  array      $fields  Fields for SELECT clause.
   * @return Query|null Basic query for finding leaf nodes.
   * @todo   Pagination, Create limit as desc.
   */
  public function leaves($baseObj, $fields = null) {
    return $this->_features($baseObj, false, $fields);
  }

  /**
   * Find internal nodes from base node.
   *
   * @param  Entity|int $baseObj Base node|id to find.
   * @param  array      $fields  Fields for SELECT clause.
   * @return Query|null Basic query for finding internal nodes.
   */
  public function internals($baseObj, $fields = null) {
    return $this->_features($baseObj, true, $fields);
  }

  /**
   * Find feature nodes in subtree from base node.
   * feature nodes
   *  (1) leaves
   *  (2) internals
   * @param  Entity|int $baseObj           Base node|id to find.
   * @param  boolean    $isFeatureInternal true:Internal nodes|false:Leaf nodes.
   * @param  array      $fields            Fields for SELECT clause.
   * @return array|null found nodes.
   */
  protected function _features($baseObj, $isFeatureInternal = false, $fields = null) {
    $baseNode = $this->_resolveNodes($baseObj);
    if (empty($baseNode)) {
      return null;
    }

    {
      $featureKey = 'ff_bingo';
      $compare = $isFeatureInternal ? '>' : '<=';

      $totalFields = $this->_fieldsScope($fields);
      // TODO: COALESCE(@compare_depth, <default value>)
      $totalFields[$featureKey] = $this->_table->query()->newExpr("@compare_depth {$compare} (@compare_depth := {$this->_depth})");

      $havingConditions = [$featureKey];

      // 2015/09/12 exists subquery, can not work @compare_depth (avert to use coalesce())
      $conditions = $this->_createSubtreeConditions($baseNode);   // without top
    }

    // get connection to executeraw query (CakePHP 3.x)
    {
      $conn = $this->_getConnection();
      if (empty($conn)) {
        return false;
      }
    }

    $statement = $conn->query('SET @compare_depth := ' . self::ROOT_DEPTH);

    $leafNodes = $this->_table->find()
      ->select($totalFields)
      ->where($conditions)
      ->having($havingConditions)
      ->order($this->_orderScope(true))    // DESC
      ->toArray()   // must use toArray(), because this query has @xxxx.
    ;

    return array_reverse($leafNodes);
  }

  /**
   * Find all nodes in grove.
   * @param  int   $grove  Grove ID to find.
   * @param  array $fields Fields for SELECT clause.
   * @return Query|null Query for finding nodes.
   * @todo   Pagination.
   */
  public function groveNodes($grove = null, $fields = null) {
    $conditions = $this->_conditionsScope();
    if ($this->_hasGrove && !empty($grove)) {
      $conditions["{$this->_grove} IN"] = (array)$grove;
    }

    $resQuery = $this->_table->find()
      ->select($this->_fieldsScope($fields))
      ->where($conditions)
      ->order($this->_orderScope())       // for nesting
    ;

    return $resQuery;
  }

  /**
   * Find all root nodes in grove.
   *
   * @param  int   $grove  Grove ID to find.
   * @param  array $fields Fields for SELECT clause.
   * @return Query|null Query for finding nodes.
   * @todo   Pagination.
   */
  public function roots($grove = null, $fields = null) {
    {
      $fields = $this->_fieldsScope($fields);

      $conditions = $this->_conditionsScope(
        $grove,
        [$this->_depth => self::ROOT_DEPTH]
      );
    }

    return $this->_table->find()
      ->select($fields)
      ->where($conditions)
    ;
  }

  /**
   * Find grove informations that has enable (not soft deleted and not grove deleted).
   *
   * @return Query|null Query for finding grove ids.
   */
  public function groves() {
    if (!$this->_hasGrove) {
      return null;
    }

    $fields = [
      $this->_grove,
      'ff_count' => $this->_table->query()->newExpr('COUNT(*)')
    ];
    $conditions = $this->_conditionsScope();

    return $this->_table->find()
      ->select($fields)
      ->where($conditions)
      ->group([$this->_grove])
    ;
  }

  /**
   * Create nested nodes from subtree nodes.
   *
   * @param ResultSet|Query|array $haystackNodes Iteratorable nodes data.
   * @return array Nested nodes hash data.
   */
  public function nestedNodes($haystackNodes) {
    if (empty($haystackNodes)) {
      return [];
    }

    /**
     * pick up nodes by iterator
     *   (1) array
     *   (2) query
     *   (3) ResultSet
     * return by hash [id => node]
     */
    $sortedNodes = $this->_queueSortedNodes($haystackNodes);
    if (empty($sortedNodes)) {
      return [];
    }

    // Root depth is not necessarily from zero.
    $rootDepth = reset($sortedNodes)->{$this->_depth};

    foreach ($sortedNodes as $node) {
      $node->nestUnsetParent();
      $node->nestUnsetChildren();
    }

    if ($this->_hasGrove) {
      foreach ($sortedNodes as $node) {
        $retroNodes[$node[$this->_grove]] = [];
      }
    } else {
      $retroNodes['singular'] = [];
    }

    $resNestedNodes = [];

    $ff_grove = 'singular';
    foreach ($sortedNodes as $node) {
      $id  = $node->{$this->_id};
      $depth = $node->{$this->_depth};

      if ($this->_hasGrove) {
        $ff_grove = $node->{$this->_grove};
      }

      $resNestedNodes[$id] = $node;     // keep node on this time.

      $depthIndex     = $depth - $rootDepth;     // Tentative root.
      $parentDepthIndex = $depthIndex - 1;

      // When this node has parent, save the relationship.
      if (isset($retroNodes[$ff_grove][$parentDepthIndex])) {
        $parentID = $retroNodes[$ff_grove][$parentDepthIndex];

        $resNestedNodes[$parentID]->nestSetChildID($id);    // FertileForestTrait::nestSetChildID()
        $resNestedNodes[$id]->nestSetParentID($parentID);   // FertileForestTrait::nestSetParentID()
      }

      // remove this depth from parents list, and save own node.
      $retroNodes[$ff_grove] = array_slice($retroNodes[$ff_grove], 0, $depthIndex);
      $retroNodes[$ff_grove][$depthIndex] = $id;
    }

    if (!$this->_hasGrove) {
      return $resNestedNodes;
    }

    $groveRes = [];
    foreach (array_keys($retroNodes) as $grove) {
      $groveRes[$grove] = [];
    }

    foreach ($resNestedNodes as $id => $node) {
      $groveRes[$node->{$this->_grove}][$id] = $node;
    }

    return $groveRes;
  }

  /**
   * Create nested IDs
   *
   * @param ResultSet|Query|array $haystackNodes Iteratorable nodes data.
   * @return array Nested IDs data.
   */
  public function nestedIDs($haystackNodes) {
     $nested = $this->nestedNodes($haystackNodes);

     function nest($id, &$idHash) {
       if (!isset($idHash[$id])) {
         return [];
       }
       $children = [];
       foreach ($idHash[$id]->nestChildIDs() as $childID) {
         $children += nest($childID, $idHash);
       }
       unset($idHash[$id]);
       return [$id => $children];
     }

     $res = [];
     if ($this->_hasGrove) {
       foreach ($nested as $grove => $info) {
         $res[$grove] = [];
         foreach ($info as $theID => $node) {
           $res[$grove] += nest($theID, $info);
         }
       }
     } else {
       foreach ($nested as $theID => $node) {
         $res += nest($theID, $nested);
       }
     }

     return $res;
  }

  //////////////////////////////////////////////////////////////////////////////

  protected function _getLastNode($grove = null) {
    if ($this->_hasGrove && empty($grove)) {
      return null;
    }

    $fields   = $this->_fieldsScope();
    $conditions = $this->_conditionsScope($grove);
    $order    = $this->_orderScope(true);     // DESC

    return $this->_table->find()
      ->select($fields)
      ->where($conditions)
      ->order($order)
      ->first()
    ;
  }

  protected function _getLastQueue($grove = null, $nullValue = null) {
    $lastNode = $this->_getLastNode($grove);
    if (empty($lastNode)) {
      return $nullValue;
    }

    return $lastNode->{$this->_queue};
  }

  protected function _getBoundaryNode(Entity $baseNode) {
    // create subquery conditions
    $boundaryQueueSubquery = $this->_createBoundaryQueueSubquery($baseNode);

    // create query to get boundary node.
    $boundaryConditions = $this->_conditionsScope(
      $baseNode->{$this->_grove},
      [$this->_queue => $boundaryQueueSubquery]
    );

    return $this->_table->find()
      ->select($this->_fieldsScope())
      ->where($boundaryConditions)
      ->first()
    ;
  }

  protected function _getPreviousNode(Entity $baseNode) {
    $conditions = $this->_conditionsScope(
      $baseNode->{$this->_grove},
      ["{$this->_queue} <" => $baseNode->{$this->_queue}]
    );

    return $this->_table->find()
      ->select($this->_fieldsScope())
      ->where($conditions)
      ->order($this->_orderScope(true))  // isDescendant
      ->first()
    ;
  }

  protected function _createBoundaryQueueSubquery(Entity $baseNode) {
    $aimQuery = $this->_table->find();
    {
      $fields = ['boundary_queue' => $aimQuery->func()->min($this->_queue)];

      $theExpr = $aimQuery->newExpr();
      $boundaryConditions = [
        // same depth can be boundary node, therefore use LTE(<=)
        $theExpr->lte($this->_depth, $baseNode->{$this->_depth}, 'integer'),
        $theExpr->gt ($this->_queue, $baseNode->{$this->_queue}, 'integer'),
      ];

      // can not use to find by first() with order().
    }

    return $aimQuery
      ->select($fields)
      ->where($this->_conditionsScope($baseNode->{$this->_grove}))
      ->where($boundaryConditions)
    ;
  }

  protected function _createTailQueueSubquery(Entity $baseNode) {
    $aimQuery = $this->_table->find();
    {
      $fields = ['boundary_queue' => $aimQuery->newExpr("MIN({$this->_queue}) - 1")];

      $theExpr = $aimQuery->newExpr();
      $boundaryConditions = [
        // same depth can be boundary node, therefore use LTE(<=)
        $theExpr->lte($this->_depth, $baseNode->{$this->_depth}, 'integer'),
        $theExpr->gt ($this->_queue, $baseNode->{$this->_queue}, 'integer'),
      ];
    }

    return $aimQuery
      ->select($fields)
      ->where($this->_conditionsScope($baseNode->{$this->_grove}))
      ->where($boundaryConditions)
    ;
  }

  protected function _getBoundaryQueue(Entity $baseNode) {
    $boundaryNode = $this->_getBoundaryNode($baseNode);

    if (empty($boundaryNode)) {
      return null;
    }

    return $boundaryNode->{$this->_queue};
  }

  protected function _getPreviousQueue(Entity $baseNode) {
    $previousNode = $this->_getPreviousNode($baseNode);

    if (empty($previousNode)) {
      return 0;
    }

    return $previousNode->{$this->_queue};
  }

  protected function _createSubtreeConditions(
    Entity $baseNode,
    $withTop = self::SUBTREE_DESCENDANTS_ONLY,
    $useCoalesce = false
  ) {
    $compair = $withTop ? '>=' : '>';
    $subConditions = ["{$this->_queue} {$compair}" => $baseNode->{$this->_queue}];

    if ($useCoalesce) {
      $subConditions[] = $this->_createCoalesceExpression($baseNode);
    } else {
      $boundaryQueue = $this->_getBoundaryQueue($baseNode);     // can be null
      if (empty($boundaryQueue)) {
        $subConditions["{$this->_queue} <="] = self::QUEUE_MAX_VALUE;     // this conditions for leaves @dd = ffdd.
      } else {
        $subConditions["{$this->_queue} <"] = $boundaryQueue;
      }
    }

    return $this->_conditionsScope(
      $baseNode->{$this->_grove},
      $subConditions
    );
  }

  protected function _createCoalesceExpression($aimNode) {
    // http://api.cakephp.org/3.0/class-Cake.Database.FunctionsBuilder.html
    $sq = $this->_createTailQueueSubquery($aimNode);
    $res = $this->_table->find()->newExpr(
      ["{$this->_queue} <=" => $this->_table->query()->func()
        ->coalesce(
          [$sq, self::QUEUE_MAX_VALUE],
          ['literal', 'integer']
        )
      ]
    );

    return $res;
  }

  protected function _sortWithQueue(&$haystackNodes) {
    if (empty($haystackNodes)) {
      return;
    }

    $gg = $this->_grove;
    $qq = $this->_queue;

    // do not need sort by grove.
    $sortFunc = function($aNode, $bNode) use($qq) {
      return $aNode->{$qq} - $bNode->{$qq};
    };

    usort($haystackNodes, $sortFunc);
  }

  protected function _queueSortedNodes($haystackNodes) {
    if (empty($haystackNodes)) {
      return [];
    }

    $res = [];
    foreach ($haystackNodes as $node) {
      if (!empty($node)) {
        $res[] = $node;
      }
    }

    // Can not use grove-queue index at ORDER BY, because to find with index of grove-depth-queue.
    // Therefore, need to sort by queue before nesting.
    // use queue index only, because dispatch to group by grove in nestedNodes()
    $this->_sortWithQueue($res);

    return $res;
  }

  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////
  // Reconstructers
  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////

  /**
   * Graft subtree nodes.
   *
   * @param Entity|int  $aimObj  Top node of subtree to graft.
   * @param Entity|int  $baseObj Base node to calc wedged queue.
   * @param boolean|int $relation numeric:As child|boolean:As sibling.
   * @return boolean true:Success|false:Failure.
   */
  public function graft($aimObj, $baseObj, $relation = -1) {
    return $this->_transactionWrapper(function() use($aimObj, $baseObj, $relation) {
      list($aimNode, $baseNode) = array_values($this->_resolveNodes([$aimObj, $baseObj] , true));   // refresh
      if (empty($aimNode) || empty($baseNode)) {
        return false;
      }

      // pick up node for wedged node to scoot over. (can be null)
      $wedgedNode = $this->_getWedgedNode($baseNode, $relation);

      {
        $isSibling = is_bool($relation);
        $depthOffset = $baseNode->{$this->_depth} - $aimNode->{$this->_depth} + ($isSibling ? 0 : 1);
      }

      $fitable = $this->_fitToGraft($aimNode, $wedgedNode, $depthOffset);
      if ($fitable) {
        return true;
      }

      return $this->_scootsOver($aimNode, $wedgedNode, $depthOffset);
    });
  }

  protected function _getWedgedNode(Entity $baseNode, $relation) {
    $isSibling = is_bool($relation);

    $pickWedgedNodeMethodName = $isSibling
      ? '_getWedgedNodeAsSibling'
      : '_getWedgedNodeAsChild'
    ;

    return $this->{$pickWedgedNodeMethodName}($baseNode, $relation);
  }

  protected function _getWedgedNodeAsChild(Entity $baseNode, $nth) {
    // pickup wedged node by order of children
    if (0 <= $nth) {
      $nthChild = $this->nthChild($baseNode, $nth);
      if (!empty($nthChild)) {
        return $nthChild;
      }
    }

    return $this->_getBoundaryNode($baseNode);   // can be null
  }

  protected function _getWedgedNodeAsSibling(Entity $baseNode, $afterSiblingNode) {
    if ($afterSiblingNode) {
      return $this->_getBoundaryNode($baseNode);
    } else {
      return $baseNode;
    }
  }

  protected function _fitToGraft(Entity $graftNode, $wedgedNode, $depthOffset) {
    $shiftQueue = $graftNode->{$this->_queue};
    $shiftGrove = $graftNode->{$this->_grove};

    if ($this->_hasGrove && empty($shiftGrove)) {
      $this->_setErrors($postedNode, $this->_grove, 'restructure.emptyColumn');
      return false;
    }

    // exists subquery, can not work @compare_depth (avert to use coalesce())
    $shiftSubtreeConditions = $this->_createSubtreeConditions($graftNode, true);    // with top

    // count grafting subtree span of queue
    {
      $shiftBoundaryQueue = $this->_getBoundaryQueue($graftNode);

      if (empty($shiftBoundaryQueue) || empty($wedgedNode)) {
        $maxQueue = $this->_getLastQueue($shiftGrove, 0);
      }

      if (empty($shiftBoundaryQueue)) {
        $shiftSpan = $maxQueue - $shiftQueue + 1;
      } else {
        $shiftSpan = $shiftBoundaryQueue - $shiftQueue;
      }

      if (empty($wedgedNode)) {
        $wedgeSpace = self::QUEUE_MAX_VALUE - $maxQueue;
      } else {
        $prevQueue = $this->_getPreviousQueue($wedgedNode);
        $wedgeSpace = $wedgedNode->{$this->_queue} - $prevQueue - 1;
      }
    }

    // If fit to graft as it is, execute to graft.
    if ($shiftSpan <= $wedgeSpace) {
      if (empty($wedgedNode)) {
        $queueOffset = $maxQueue - $shiftQueue + 1;
      } else {
        $queueOffset = $prevQueue - $shiftQueue + 1;
      }

      if ($queueOffset == 0 && $depthOffset == 0) {
        return false;
      }

      {
        $aimQuery = $this->_table->query();

        $fields = [];
        {
          if ($queueOffset != 0) {
            $fields[$this->_queue] = $aimQuery->newExpr("{$this->_queue} + {$queueOffset}");
          }
          if ($depthOffset != 0) {
            $fields[$this->_depth] = $aimQuery->newExpr("{$this->_depth} + {$depthOffset}");
          }
        }
      }

      $res = $this->_updateAll($fields, $shiftSubtreeConditions) ?: false;

      return $res;
    }

    // try to fit to shift with evenizing.
    {
      $nodesCount = $this->_table->find()
        ->where($shiftSubtreeConditions)
        ->count()
      ;
    }

    if ($nodesCount <= $wedgeSpace) {
      if ($nodesCount < 1) {
        return false;
      }

      if (empty($wedgedNode) && $depthOffset == 0) {
        return false;
      }

      $queueInterval = (int)min(self::QUEUE_DEFAULT_INTERVAL, $wedgeSpace / $nodesCount);

      $startQueue = (empty($wedgedNode) ? $maxQueue : $prevQueue) + 1 - $queueInterval;

      $updateFields[$this->_queue] = "@forest_queue := @forest_queue + {$queueInterval}";
      if ($depthOffset != 0) {
        $updateFields[$this->_depth] = "{$this->_depth} + {$depthOffset}";
      }

      $updateOrder = $this->_orderScope();

      // use raw query, because can not use ORDER BY in standard updateAll().
      $res = $this->_updateAllInOrder(
        $updateFields,
        $shiftSubtreeConditions,
        $updateOrder,
        "SET @forest_queue = {$startQueue}"
      );

      return $res;
    }

    // can not fit to shift
    return false;
  }

  protected function _scootsOver(Entity $shiftNode, $wedgedNode, $depthOffset) {
    // find boundary node of shift node (can be null)
    $aimBoundaryNode = $this->_getBoundaryNode($shiftNode);

    //////////////////////////////////////////////////////////////////////////

    if (!$this->_canGraftByNode($shiftNode, $aimBoundaryNode, $wedgedNode)) {
      return false;
    }

    //////////////////////////////////////////////////////////////////////////

    {
      $aimGrove = $shiftNode->{$this->_grove};
      $aimQueue = $shiftNode->{$this->_queue};
    }

    {
      if (empty($aimBoundaryNode) || empty($wedgedNode)) {
        $maxQueue = $this->_getLastQueue($aimGrove, 0);
      }

      if (empty($aimBoundaryNode)) {
        $aimTailQueue = $maxQueue;
      } else {
        $aimTailQueue = $aimBoundaryNode->{$this->_queue} - 1;
      }

      if (empty($wedgedNode)) {
        $wedgedTailQueue = $maxQueue;
      } else {
        $wedgedTailQueue = $wedgedNode->{$this->_queue} - 1;
      }
    }

    //////////////////////////////////////////////////////////////////////////

    /**
     * moving direction progress/retrogress
     * when same queue, as retrogress. Therefore use "<=".
     */
    $isRetrogression = $wedgedTailQueue <= $aimQueue;

    /**
     * moving distance
     */
    $moveOffset = $isRetrogression
      ? $wedgedTailQueue - $aimQueue + 1
      : $wedgedTailQueue - $aimTailQueue
    ;
    $involvedOffset = ($aimTailQueue - $aimQueue + 1) * ($isRetrogression ? 1 : -1);

    {
      $queueOffsetCase = $isRetrogression
        ? "CASE WHEN {$this->_queue} < {$aimQueue} THEN {$involvedOffset} ELSE {$moveOffset} END"
        : "CASE WHEN {$this->_queue} <= {$aimTailQueue} THEN {$moveOffset} ELSE {$involvedOffset} END"
      ;
      $aimQuery = $this->_table->query();

      $fields = [];
      {
        // To set depth must be firstly, because it include condition of queue.
        // If to set queue firstly, depth condition is changed before set.
        if ($depthOffset != 0) {
          $depthOffsetCase = "CASE WHEN {$aimQueue} <= {$this->_queue} AND {$this->_queue} <= {$aimTailQueue} THEN {$depthOffset} ELSE 0 END";
          $fields[$this->_depth] = $aimQuery->newExpr("{$this->_depth} + {$depthOffsetCase}");
        }
        $fields[$this->_queue] = $aimQuery->newExpr("{$this->_queue} + {$queueOffsetCase}");
      }

      $conditions = $this->_conditionsScope(
        $aimGrove,
        [
          "{$this->_queue} >=" => ($isRetrogression ? $wedgedTailQueue + 1 : $aimQueue     ),
          "{$this->_queue} <=" => ($isRetrogression ? $aimTailQueue    : $wedgedTailQueue),
        ]
      );
    }

    $res = $this->_updateAll($fields, $conditions) ?: false;

    return $res;
  }

  protected function _canGraftByNode(Entity $aimNode, $aimBoundaryNode, $wedgedNode) {
    /**
     * When no wedged node, it means that last queue.
     * In the case, can shift always
     */
    if (empty($wedgedNode)) {
      return true;
    }

    /**
     * If grove is different, can not shift.
     */
    if ($this->_hasGrove && $aimNode->{$this->_grove} != $wedgedNode->{$this->_grove}) {
      $this->_setErrors($aimNode   , $this->_grove, 'restructure.defferentGroves');
      $this->_setErrors($wedgedNode, $this->_grove, 'restructure.defferentGroves');
      return false;
    }

    /**
     * If wedged queue between the shifting subtree, can not shift.
     * head < wedged < boundary
     *
     * can be "head == wedged". It is OK for shifting.
     * because it means "depth-shifting".
     *
     * 2015/04/30
     * Linearized Tree Model aborted to use, because float has arithmetic error.
     */
    $wedgedQueue = $wedgedNode->{$this->_queue};

    /**
     * can be "head == wedged". It is OK for shifting.
     * because it means "depth-shifting".
     */
    if ($wedgedQueue <= $aimNode->{$this->_queue}) {
      return true;
    }

    // In this case, must use boundary queue.
    if (empty($aimBoundaryNode)) {
      $this->_setErrors($postedNode, $this->_grove, 'restructure.graftIntoOwn');
      return false;
    }

    // It is safe.
    if ($aimBoundaryNode->{$this->_queue} < $wedgedQueue) {
      return true;
    }

    $this->_setErrors($postedNode, $this->_grove, 'restructure.graftIntoOwn');
    return false;
  }

  ////////////////////////////////////////////////////////////////////////////

  /**
   * Reorder sibling nodes.
   *
   * @param Entity|int[] $args Sibling nodes to permute.
   * @return boolean true:Success|false:Failure.
   */
  public function permute() {
    $args = func_get_args();

    return $this->_transactionWrapper(function() use($args) {
      // refresh
      $siblingNodes = $this->_resolveNodes($this->_resolveArgs($args, true));

      // if node is only one, nothing to do.
      if (count($siblingNodes) == 1) {
        return false;
      }

      /**
       * Are they siblings?
       */
      $currentOrderedChildNodes = $this->areSiblings(array_values($siblingNodes));
      if (empty($currentOrderedChildNodes)) {
        $this->_setErrors($postedNode, $this->_grove, 'restructure.areNotSiblings');
        return false;
      }

      // if they are siblings, yield to permute.

      // create array new ordered nodes.
      $newOrderedSiblingNodes = [];
      $newOrderedIDs = array_keys($siblingNodes);
      foreach ($currentOrderedChildNodes as $theID => $node) {
        if (array_key_exists($theID, $siblingNodes)) {
          $pickedID = array_shift($newOrderedIDs);
          $newOrderedSiblingNodes[] = $currentOrderedChildNodes[$pickedID];
        } else {
          $newOrderedSiblingNodes[] = $node;
        }
      }

      /**
       * get sorted nodes of all siblings by queue.
       */
      $currentQueueOrderedNodes = $this->_queueSortedNodes(array_values($currentOrderedChildNodes));

      /**
       * calc each siblingNode information.
       */
      {
        // get tail node
        $tailNode = end($currentQueueOrderedNodes);

        $aimGrove = $tailNode->{$this->_grove};

        // get total boundary queue (can be null)
        $siblingsBoundaryQueue = $this->_getBoundaryQueue($tailNode);
        $totalTailQueue = empty($siblingsBoundaryQueue)
          ? $this->_getLastQueue($aimGrove, 0)
          : $siblingsBoundaryQueue - 1
        ;
      }

      $attr = 'ff_attributes';

      {
        // set by current order.
        foreach ($currentQueueOrderedNodes as &$nodePointer) {
          $nodePointer->{$attr} = [];
        }

        $lastNodeIndex = count($currentQueueOrderedNodes) - 1;

        $nodeIDHash = [];
        foreach ($currentQueueOrderedNodes as $i => &$nodePointer) {
          $isLast = $i == $lastNodeIndex;
          $nodePointer[$attr]['is_last'] = $isLast;

          $theTailQueue = $isLast ? $totalTailQueue : ($currentQueueOrderedNodes[$i + 1][$this->_queue] - 1);
          $nodePointer[$attr]['tail_queue'] = $theTailQueue;

          // calc queue-width each sibling
          $nodePointer[$attr]['queue_width'] = $theTailQueue - $nodePointer[$this->_queue] + 1;

          // must use &$xxxx, because do not clone node instance.
          $nodeIDHash[$nodePointer->{$this->_id}] = &$nodePointer;
        }
      }

      /**
       * get shifted range of queues
       */
      $rangeQueueHead = reset($currentQueueOrderedNodes)[$this->_queue];

      /**
       * calc moving queue span for each node.
       */
      $hasChanged = false;
      $reduceQueue = $rangeQueueHead;     // default value of new queue.
      foreach ($newOrderedSiblingNodes as $theNode) {
        $updateID = $theNode->{$this->_id};
        $off = $reduceQueue - $nodeIDHash[$updateID][$this->_queue];
        {
          $nodeIDHash[$updateID][$attr]['ff_offset'] = $off;

          if ($off != 0) {
            $hasChanged = true;
          }
        }

        $reduceQueue += $nodeIDHash[$updateID][$attr]['queue_width'];
      }

      // no move, no update.
      if (!$hasChanged) {
        return false;
      }

      // create case for update by original order of queue.
      {
        $whenHash = ['CASE'];
        foreach ($currentQueueOrderedNodes as $info) {
          $originID = $info->{$this->_id};

          $off = $info[$attr]['ff_offset'];
          if ($info[$attr]['is_last']) {
            $whenHash[] = "ELSE {$off}";
          } else {
            $whenHash[] = "WHEN {$this->_queue} <= {$info[$attr]['tail_queue']} THEN {$off}";
          }
        }
        $whenHash[] = 'END';

        $caseString = implode(' ', $whenHash);
      }

      // execute to update
      {
        $fields = [$this->_queue => $this->_table->query()->newExpr("{$this->_queue} + {$caseString}")];

        $conditions = $this->_conditionsScope(
          $aimGrove,
          [
            "{$this->_queue} >=" => $rangeQueueHead,
            "{$this->_queue} <=" => $totalTailQueue,    // <= is for max queue
          ]
        );
      }

      return $this->_updateAll($fields, $conditions);
    });
  }

  /**
   * Permute in siblings as "Move To".
   * @param Entity|int $nodeObj Moved node by Entity|id.
   * @param int        $nth move rank in sibling. (-1:As last sibling)
   * @return boolean true:Success|false:Failure.
   */
  public function moveTo($nodeObj, $nth = -1) {
    return $this->_moveNode($nodeObj, $nth, false);
  }

  /**
   * Permute in siblings as "Move By".
   * @param Entity|int $nodeObj Moved node by Entity|id.
   * @param int $step Moving offset.
   * @return boolean true:Success|false:Failure.
   */
  public function moveBy($nodeObj, $step) {
    return $this->_moveNode($nodeObj, $step, true);
  }

  protected function _moveNode($nodeObj, $moveNumber, $asMoveBy = false) {
    $aimNode = $this->_resolveNodes($nodeObj);
    $siblingNodes = $this->siblings($aimNode, [$this->_id])
      ->toArray()
    ;
    if (empty($aimNode) || empty($siblingNodes)) {
      // TODO: validate error
      return false;
    }

    $aimID = $aimNode->{$this->_id};

    // get ordered rank from $moveNumber
    if ($asMoveBy) {
      if ($moveNumber == 0) {
        // TODO: validate error
        return false;
      }

      $orderedRank = null;
      foreach ($siblingNodes as $i => $node) {
        if ($node->{$this->_id} == $aimID) {
          $orderedRank = $i;
          break;
        }
      }

      if (is_null($orderedRank)) {
        // TODO: validate error
        return false;
      }

      $orderedRank = max(0, $orderedRank + $moveNumber);
      if (count($siblingNodes) <= $orderedRank) {
        $orderedRank = -1;
      }
    } else {
      $orderedRank = $moveNumber;
    }

    if ($orderedRank < 0) {
      $orderedRank = count($siblingNodes) - 1;
    }

    if (count($siblingNodes) <= $orderedRank) {
      // TODO: validate error
      return false;
    }

    if ($siblingNodes[$orderedRank]->{$this->_id} == $aimID) {
      // TODO: validate error
      return false;
    }

    $newOrderedNodes = array_filter(
      $siblingNodes,
      function($node) use($aimID) {
        return $node->{$this->_id} != $aimID;
      }
    );

    $postNodes = array_splice($newOrderedNodes, $orderedRank);
    $newOrderedNodes = array_merge($newOrderedNodes, [$aimNode], $postNodes);

    return $this->permute($newOrderedNodes);
  }

  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////
  // Removes
  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////

  /**
   * Remove the node and shift depth of descendant nodes.
   * soft delete
   *  (1) soft delete
   *  (2) grove delete
   *  (3) normal delete
   * @param Entity|int $nodeObj Node to remove.
   * @return boolean true:Success|false:Failure.
   */
  public function remove($nodeObj) {
    return $this->_transactionWrapper(function() use($nodeObj) {
      $removeNode = $this->_resolveNodes($nodeObj, true);   // refresh
      if (empty($removeNode)) {
        return false;
      }

      {
        $aimQueue = $removeNode->{$this->_queue};
        $aimDepth = $removeNode->{$this->_depth};
        $aimGrove = $removeNode->{$this->_grove};
      }

      // Get range of descendants for moving up depth of them.
      if ($aimDepth == self::ROOT_DEPTH) {
        $offsetDepth = 1;
      } else {
        $parentNode = $this->genitor($removeNode);
        if (empty($parentNode)) {
          return false;
        }
        $parentDepth = empty($parentNode)
          ? self::ROOT_DEPTH
          : $parentNode->{$this->_depth}
        ;

        $offsetDepth = $aimDepth - $parentDepth;
      }

      // boundary queue (can be nulll)
      $aimBoundaryQueue = $this->_getBoundaryQueue($removeNode);

      $aimQuery = $this->_table->query();
      {
        $fields = [
          $this->_depth => $aimQuery->newExpr(
            "CASE {$this->_queue} WHEN {$aimQueue} THEN 0 ELSE {$this->_depth} - {$offsetDepth} END"
          )
        ];

        $conditions = $this->_conditionsScope(
          $aimGrove,      // can be null
          ["{$this->_queue} >=" => $aimQueue]      // include aim node ">="
        );
        if (!empty($aimBoundaryQueue)) {
          $conditions["{$this->_queue} <"] = $aimBoundaryQueue;
        }
      }

      // soft delete
      if ($this->_hasSoftDelete || $this->_enableGroveDelete) {
        if ($this->_hasSoftDelete) {
          $softDeleteValue = $this->_getConfig('deleteValue');
          $fields[$this->_softDelete] = $aimQuery->newExpr("CASE {$this->_queue} WHEN {$aimQueue} THEN {$softDeleteValue} ELSE {$this->_softDelete} END");
        } else {
          $fields[$this->_grove] = $aimQuery->newExpr("{$this->_grove} * CASE {$this->_queue} WHEN {$aimQueue} THEN -1 ELSE 1 END");
        }

        $res = $this->_updateAll($fields, $conditions);

      // Hard delete.
      } else {
        $updateRes = $this->_updateAll($fields, $conditions);
        $res = $this->_table->deleteAll([$this->_id => $removeNode->{$this->_id}]);
      }

      return $res;
    });
  }

  /**
   * Prune subtree nodes.
   * @param Entity|int $baseObj Top node to prune.
   * @param boolean    $withTop Include base node in return query.
   * @return boolean true:Success|false:Failure.
   */
  public function prune($baseObj, $withTop = self::SUBTREE_DESCENDANTS_ONLY) {
    return $this->_transactionWrapper(function() use($baseObj, $withTop) {
      $aimNode = $this->_resolveNodes($baseObj, true);   // refresh
      if (empty($aimNode)) {
        return false;
      }
      {
        $aimQueue = $aimNode->{$this->_queue};
        $aimDepth = $aimNode->{$this->_depth};
        $aimGrove = $aimNode->{$this->_grove};
      }

      // boundary queue (can be null)
      $aimBoundaryQueue = $this->_getBoundaryQueue($aimNode);

      // common conditions
      $lesserCondition = $withTop ? '>=' : '>';
      $conditions = $this->_conditionsScope(
        $aimGrove,
        ["{$this->_queue} {$lesserCondition}" => $aimQueue]
      );
      if (!empty($aimBoundaryQueue)) {
        $conditions["{$this->_queue} <"] = $aimBoundaryQueue;
      }

      // soft delete
      if ($this->_hasSoftDelete) {
        $fields = [$this->_softDelete => $this->_getConfig('deleteValue')];
        $res = $this->_updateAll($fields, $conditions);

      } elseif ($this->_enableGroveDelete) {
        $fields = [$this->_grove => $this->_table->query()->newExpr("{$this->_grove} * -1")];
        $res = $this->_updateAll($fields, $conditions);

      } else {
        $res = $this->_table->deleteAll($conditions);
      }

      return $res;
    });
  }

  /**
   * Extinguish (remove top node and the descendant nodes).
   * @param Entity|int $baseObj Top node to extinguish.
   * @return boolean true:Success|false:Failure.
   */
  public function extinguish($baseObj) {
    return $this->prune($baseObj, self::SUBTREE_WITH_TOP_NODE);
  }

  /**
   * Pollard (remove the descendant nodes).
   * @param Entity|int $baseObj Top node to pollard.
   * @return boolean true:Success|false:Failure.
   */
  public function pollard($baseObj) {
    return $this->prune($baseObj, self::SUBTREE_DESCENDANTS_ONLY);
  }

  /**
   * Normalize tree fieldsin ordered grove.
   * @param Entity|int $aimNode Start node.
   * @param Entity|int $aimBoundaryNode Boundary node.
   * @return boolean true:Success|false:Failure.
   * @todo Normalize depth, too.
   */
  public function normalize($aimNode, $aimBoundaryNode) {
    return $this->_transactionWrapper(function() use($aimNode, $aimBoundaryNode) {
      $aimNode         = $this->_resolveNodes($aimNode        , true);  // can be null
      $aimBoundaryNode = $this->_resolveNodes($aimBoundaryNode, true);  // can be null

      $grove            = empty($aimNode)         ? null : $aimNode->{$this->_grove};
      $aimTopQueue      = empty($aimNode)         ? null : $aimNode->{$this->_queue};
      $aimBoundaryQueue = empty($aimBoundaryNode) ? null : $aimBoundaryNode->{$this->_queue};

      $res = $this->_evenize($grove, $aimTopQueue, $aimBoundaryQueue, 0);  // 0: no append node

      return empty($res) ? false : $res[self::EVENIZE_AFFECTED_ROWS_KEY];
    });
  }

  protected function _evenize(
    $grove,
    $aimQueue,
    $aimBoundaryQueue,
    $addCount,
    $rearJustified = false
  ) {
    if ($this->_hasGrove && empty($grove)) {
      return false;
    }

    /**
     * can evenize?
     * 1.0 <= (boundaryQueue - headQueue) / (updatedNodeCount + appendNodeCount)
     */
    $canEvenize = $this->_canEvenize($grove, $aimQueue, $aimBoundaryQueue, $addCount);
    if (empty($canEvenize)) {
      return false;
    }

    $queueInterval = $canEvenize['queueInterval'];
    $nodeCount   = $canEvenize['nodeCount'  ];

    /**
     * execute to slide
     */

    // calc defaut value of new queues
    $startQueue = $rearJustified
      ? ($aimQueue + $queueInterval * ($addCount - 1))  // $addCount can be 0
      : ($aimQueue - $queueInterval)
    ;

    $updateFields = [$this->_queue => "@forest_queue := @forest_queue + {$queueInterval}"];

    // exists subquery, can not work @compare_depth (avert to use coalesce())
    $updateConditions = $this->_conditionsScope($grove);
    {
      if (!empty($aimQueue)) {
        $updateConditions["{$this->_queue} >="] = $aimQueue;
      }
      if (!empty($aimBoundaryQueue)) {
        $updateConditions["{$this->_queue} <"] = $aimBoundaryQueue;
      }
    }

    $updateOrder = $this->_orderScope();

    $res = $this->_updateAllInOrder(
      $updateFields,
      $updateConditions,
      $updateOrder,
      "SET @forest_queue = {$startQueue}"
    );

    // queue to append and updated row count
    return $canEvenize + [
      self::SPROUT_VACANT_QUEUE_KEY  => $aimQueue + ($rearJustified ? 0 : $queueInterval * $nodeCount),
      self::EVENIZE_AFFECTED_ROWS_KEY => $res,
    ];
  }

  protected function _canEvenize(
    $aimGrove,
    $aimQueue,
    $aimBoundaryQueue,
    $addCount
  ) {
    if (!empty($aimQueue) && !empty($aimBoundaryQueue)) {
      if ($aimBoundaryQueue <= $aimQueue + 1) {
        return false;
      }
    }

    // get count of node for UPDATE
    {
      $conditions = $this->_conditionsScope($aimGrove);
      {
        if (!empty($aimQueue)) {
          $conditions["{$this->_queue} >="] = $aimQueue;
        }

        if (!empty($aimBoundaryQueue)) {
          $conditions["{$this->_queue} <"] = $aimBoundaryQueue;
        }
      }

      $aimCount = $this->_table->find()
        ->where($conditions)
        ->count()
      ;
    }
    // can nomalize?
    $dividNumber = $aimCount + $addCount;
    if ($dividNumber < 1) {
      return false;
    }

    // get boundary queue for calc
    if (empty($aimQueue)) {
      $aimQueue = 0;
    }

    if (empty($aimBoundaryQueue)) {
      // get last queue
      $maxQueue = $this->_getLastQueue($aimGrove, 0);
      $queueInterval = $this->_getQueueInterval();

      $requestQueueSpan = $queueInterval * ($addCount + 1);

      if (self::QUEUE_MAX_VALUE - $maxQueue < $requestQueueSpan) {
        $aimQueueSpan = self::QUEUE_MAX_VALUE - $aimQueue + 1;
      } else {
        $aimQueueSpan = $maxQueue - $aimQueue + $requestQueueSpan;
      }
    } else {
      $aimQueueSpan = $aimBoundaryQueue - $aimQueue;
    }

    $queueInterval = (int)($aimQueueSpan / $dividNumber);
    if ($queueInterval < 1) {
      return false;
    }

    if ($this->_getQueueInterval() < $queueInterval) {
      $queueInterval = $this->_getQueueInterval();
    }

    return [
      'queueInterval' => $queueInterval,
      'nodeCount'   => $aimCount,
    ];
  }

  /**
   * repair depth fault (to close)
   */
  public function normalizeDepth($grove = null) {
    return $this->_transactionWrapper(function() use($grove) {
      if ($this->_hasGrove && empty($grove)) {
        return false;
      }

      // get connection to executeraw query (CakePHP 3.x)
      {
        $conn = $this->_getConnection();
        if (empty($conn)) {
          return false;
        }
      }

      $fields = $this->_fieldsScope();
      {
        $expr = "@compare_depth + 1 < (@compare_depth := {$this->_depth})";
        $fields['ff_is_fault'] = $this->_table->query()->newExpr($expr);
      }

      /**
       * (1) find all fault
       * (2) repeat to repair
       */
      $conditions = $this->_conditionsScope($grove);

      $order = $this->_orderScope();

      $limited = $this->_getConfig('subtreeLimitSize');

      $statement = $conn->query('SET @compare_depth := ' . self::ROOT_DEPTH);
      $faultQuery = $this->_table->query()
        ->select($fields)
        ->where($conditions)
        ->having(['ff_is_fault'])
        ->order($order)
        ->limit($limited)
      ;

      /**
       * found fault-node, update each node.
       *  (1) create subtree conditions with top
       *  (2) update all
       */
      $depthOffsetValues = [];
      foreach ($faultQuery as $aimNode) {
        $prevNode = $this->_getPreviousNode($aimNode);

        if (empty($aimNode) || empty($prevNode)) {
          continue;
        }

        $offset = $aimNode->{$this->_depth} - $prevNode->{$this->_depth};

        $gg = $prevNode->{$this->_grove};
        $dd = $prevNode->{$this->_depth};
        $qq = $prevNode->{$this->_queue};

        $newNode = $this->_table->newEntity();
        {
          $newNode->{$this->_grove} = $gg;
          $newNode->{$this->_queue} = $qq;
        }

        $depthOffets = [];
        for ($o = $offset; 1 < $o--;) {
          $newNode->{$this->_depth} = $dd + $o;
          $boundaryQueue = $this->_getBoundaryQueue($newNode);
          $depthOffets[] = $boundaryQueue;
        }

        $groveCondition = $this->_hasGrove ? "{$this->_grove} = {$gg} AND" : '';
        foreach ($depthOffets as $boundaryQueue) {
          $boundaryCondition = empty($boundaryQueue) ? '' : "AND {$this->_queue} < {$boundaryQueue}";
          $depthOffsetValues[] = "(CASE WHEN {$groveCondition} {$qq} < {$this->_queue} {$boundaryCondition} THEN 1 ELSE 0 END)";
        }
      }

      if (empty($depthOffsetValues)) {
        return false;
      }

      $depthNewValueString = "{$this->_depth} - " . join('-', $depthOffsetValues);
      $predicates = [
        $this->_depth => $this->_table->query()->newExpr($depthNewValueString),
      ];

      $conditions = $this->_hasGrove
        ? [$this->_grove => $grove]
        : []
      ;

      $res = $this->_updateAll($predicates, $conditions);

      return $res;
    });
  }

  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////
  // States
  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////

  /**
   * Are all nodes siblings?
   * @param array $args nodes.
   * @return boolean Returns true is those are sibling nodes.
   */
  public function areSiblings() {
    $args = func_get_args();
    $siblingNodes = $this->_resolveNodes($this->_resolveArgs($args));

    /**
     * get id hash by nested information
     */
    $eldestNode = reset($siblingNodes);   // eldest node.
    $childNodes = $this->siblings($eldestNode, [$this->_id])
      ->order($this->_orderScope())
      ->toArray()   // for count()
    ;
    if (empty($childNodes)) {
      return null;    // dubious
    }

    $childHashes = [];
    $bingoCount = count($siblingNodes);
    foreach ($childNodes as $node) {
      $theID = $node->{$this->_id};

      $childHashes[$theID] = $node;
      if (isset($siblingNodes[$theID])) {
        --$bingoCount;
      }
    }

    return $bingoCount == 0
      ? $childHashes
      : false
    ;
  }

  /**
   * Is root node?
   * @param Entity|int $nodeObj Node of Entity|int to check.
   * @return boolean Returns true is this is root node.
   */
  public function isRoot($nodeObj) {
    $node = $this->_resolveNodes($nodeObj);
    if (empty($node)) {
      return null;    // dubious
    }

    return $node->{$this->_depth} == self::ROOT_DEPTH;   // never ===
  }

  /**
   * Has descendant?
   * @param Entity|int $nodeObj Node of Entity|int to check.
   * @return boolean Returns true is this has descendant node.
   */
  public function hasDescendant($nodeObj) {
    $node = $this->_resolveNodes($nodeObj);
    if (empty($node)) {
      return null;        // null as dubious
    }

    $conditions = $this->_createSubtreeConditions(
      $node,
      false,    // without top
      true      // use COALESCE()
    );

    return $this->_table->query()->where($conditions)->count() ?: false;
  }

  /**
   * Is leaf node?
   * @param Entity|int $nodeObj Node of Entity|int to check.
   * @return boolean Returns true is this is leaf node.
   */
  public function isLeaf($nodeObj) {
    $result = $this->hasDescendant($nodeObj);

    // null as dubious
    return is_null($result) ? null : !$result;
  }

  /**
   * Is internal node?
   * "internal" means non-leaf and non-root.
   * @param Entity|int $nodeObj Node of Entity|int to check.
   * @return boolean Returns true is this is leaf node.
   */
  public function isInternal($nodeObj) {
    $node = $this->_resolveNodes($nodeObj);
    if (empty($node)) {
      return null;        // dubious
    }

    return $node->{$this->_depth} != self::ROOT_DEPTH && $this->hasDescendant($node);
  }

  /**
   * Has sibling node?
   * @param Entity|int $nodeObj Node of Entity|int to check.
   * @return boolean Returns true is this has sibling node.
   */
  public function hasSibling($nodeObj) {
    $aimNode = $this->_resolveNodes($nodeObj);
    if (empty($aimNode)) {
      return null;      // null as dubious
    }
    $aimDepth = $aimNode->{$this->_depth};

    // root node has no sibling
    if ($aimDepth == self::ROOT_DEPTH) {
      return false;
    }

    $parentNode = $this->genitor($aimNode);
    if (empty($parentNode)) {
      return null;      // null as dubious, because no parent is irregular
    }

    $conditions = $this->_createSubtreeConditions(
      $parentNode,
      false,      // without top
      true        // use COALESCE()
    );
    $conditions[$this->_depth] = $aimDepth;

    $nodeCount = $this->_table->find()
      ->where($conditions)
      ->count()
    ;

    return 1 < $nodeCount ? $nodeCount : false;
  }

  /**
   * Is only child?
   * @param Entity|int $nodeObj Node of Entity|int to check.
   * @return boolean Returns true is this is only child node.
   */
  public function isOnlyChild($nodeObj) {
    $hasSibling = $this->hasSibling($nodeObj);
    return is_null($hasSibling) ? null : !$hasSibling;
  }

  /**
   * Is reserching node descendant of base node?
   * @param Entity|int $baseObj Entity|int of base node to check.
   * @param array $researches Research nodes.
   * @return array Item of array true is it is descendant node of base node.
   */
  public function isDescendant($baseObj, $researches = []) {
    $node = $this->_resolveNodes($baseObj);
    if (empty($node)) {
      return null;    // null as dubious
    }

    $isPlural = is_array($researches);

    if (empty($researches)) {
      return $isPlural ? [] : null;
    }

    // need to be "id => node" for checking grove
    $researchNodes = $this->_resolveNodes(
      $isPlural ? $researches : [$researches],
      true   // refresh
    );

    $boundaryQueue = $this->_getBoundaryQueue($node);
    $aimTailQueue = empty($boundaryQueue) ? self::QUEUE_MAX_VALUE : $boundaryQueue - 1;

    {
      $aimQueue = $node->{$this->_queue};
      $aimGrove = $node->{$this->_grove};
    }

    $res = [];
    foreach ($researchNodes as $theID => $theNode) {
      if (!empty($theNode) && $theNode->{$this->_grove} == $aimGrove) {
        $theQueue = $theNode->{$this->_queue};
        $res[$theID] = $aimQueue < $theQueue && $theQueue <= $aimTailQueue;
      } else {
        $res[$theID] = null;
      }
    }

    return $isPlural ? $res : reset($res);
  }

  /**
   * Is reserching node ancestor of base node?
   * @param Entity|int $baseObj Entity|int of base node to check.
   * @param array $researches Research nodes.
   * @return array Item of array true is it is ancestor node of base node.
   */
  public function isAncestor($baseObj, $researches = []) {
    $node = $this->_resolveNodes($baseObj);
    if (empty($node)) {
      return null;    // null as dubious
    }

    $isPlural = is_array($researches);

    if (empty($researches)) {
      return $isPlural ? [] : null;
    }

    $ancestors = $this->ancestors($node);

    $researchHash = [];
    foreach ($ancestors as $ancestorNode) {
      $researchHash[$ancestorNode->{$this->_id}] = $ancestorNode;
    }

    // need to be "id => node" for checking grove
    $researchNodes = $this->_resolveNodes(
      $isPlural ? $researches : [$researches],
      true   // refresh
    );

    $res = [];
    foreach ($researchNodes as $theID => $theNode) {
      $res[$theID] = isset($researchHash[$theID]);
    }

    return $isPlural ? $res : reset($res);
  }

  //////////////////////////////////////////////////////////////////////////////

  /**
   * Calculate height of subtree.
   * When want to get root height as follows.
   *  (1) get height of any node.
   *  (2) root height = height of the node + depth of the node.
   * Height of empty tree is "-1"
   * http://en.wikipedia.org/wiki/Tree_(data_structure)
   * @param Entity|int $baseObj Entity|int of base node to check.
   * @return int|null Height of subtree of base node.
   */
  public function height($baseObj) {
    $node = $this->_resolveNodes($baseObj);
    if (empty($node)) {
      return null;
    }

    $heightKey = 'ff_height';

    $aimQuery = $this->_table->find();

    $fields = [$heightKey => $aimQuery->func()->max($this->_depth)];

    $conditions = $this->_createSubtreeConditions(
      $node,
      true,    // with top
      true     // use COALESCE()
    );

    $resNode = $aimQuery
      ->select($fields)
      ->where($conditions)
      ->first()
    ;

    if (empty($resNode)) {
      return null;    // null as dubious
    }

    return $resNode->{$heightKey} - $node->{$this->_depth};
  }

  /**
   * Calculate size of subtree.
   * @param Entity|int $baseObj Entity|int of base node to check.
   * @return int|null Size of subtree of base node.
   */
  public function size($baseObj) {
    $topNode = $this->_resolveNodes($baseObj);
    if (empty($topNode)) {
      return null;
    }

    $conditions = $this->_createSubtreeConditions(
      $topNode,
      true,    // with top
      true     // use COALESCE()
    );

    return $this->_table->find()
      ->where($conditions)
      ->count()
    ;
  }
}
