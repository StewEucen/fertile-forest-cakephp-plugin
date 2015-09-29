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
 * @category  CakePHP Trait for Entity
 * @copyright Copyright (c) 2015 Stew Eucen (http://lab.kochlein.com)
 * @license   http://www.opensource.org/licenses/mit-license.php  MIT License
 * @link      http://lab.kochlein.com/FertileForest
 * @package   FertileForest\Model\Entity
 * @since     File available since Release 1.0.0
 * @version   Release 1.0.0
 */

namespace FertileForest\Model\Entity;

use Cake\ORM\TableRegistry;

use App\Model\Behavior\FertileForestBehavior;

trait FertileForestTrait {
    // fertile forest original information properties.
    protected $_ffInfo = [
        'parent'   => [0 => null],
        'children' => [],
    ];

    protected function _getTable() {
      return TableRegistry::get($this->_registryAlias);
    }

    ////////////////////////////////////////////////////////////////////////////

    /**
     *
     * accessors
     *
     */

    ////////////////////////////////////////////////////////////////////////////

    public function nestParentNode() {
        return reset($this->_ffInfo['parent']);
    }

    public function nestChildNodes() {
        return $this->_ffInfo['children'];
    }

    public function nestParentID() {
        return array_keys($this->_ffInfo['parent'])[0];
    }

    public function nestChildIDs() {
        return array_map(
            function($value) {return (int)$value;},
            array_keys($this->_ffInfo['children'])
        );
    }

    ////////////////////////////////////////////////////////////////////////////

    public function nestSetParentID($id) {
        $this->_ffInfo['parent'] = [$id => null];

        return $this;
    }

    public function nestSetChildID($id) {
        $this->_ffInfo['children'][$id] = null;

        return $this;
    }

    /**
     * set parent node with id
     * Can not read $this->_id of FertileForestTable, therefore use $id param.
     */
    public function nestSetParentNode($id, $node) {
        if (!empty($id) && !empty($node)) {
            $this->_ffInfo['parent'] = [$id => $node];
        }

        return $this;
    }

    /**
     * set child node with id
     * Can not read $this->_id of FertileForestTable, therefore use $id param.
     */
    public function nestSetChildNode($id, $node) {
        if (!empty($id) && !empty($node)) {
            $this->_ffInfo['children'][$id] = $node;
        }

        return $this;
    }

    ////////////////////////////////////////////////////////////////////////////

    public function nestUnsetParent() {
        $this->_ffInfo['parent'] = [0 => null];

        return $this;
    }

    public function nestUnsetChildren($id = null) {
        if (empty($id)) {
            $this->_ffInfo['children'] = [];
        } else {
            unset($this->_ffInfo['children'][$id]);
        }

        return $this;
    }

    ////////////////////////////////////////////////////////////////////////////

    public function isNestLeaf() {
        return empty($this->_ffInfo['children']);
    }

    public function isNestParent() {
        return !$this->isNestLeaf();
    }

    ////////////////////////////////////////////////////////////////////////////

    /**
     * Wrapper methods.
     */
    // Table methods calling directly
    // name do not need "ff_" surffix, because trait can use alias when to use.

    public function trunk($range = FertileForestBehavior::ANCESTOR_ALL, $fields = null) {
      return $this->_getTable()->trunk($this, $range, $fields);
    }

    public function ancestors($fields = null) {
      return $this->_getTable()->ancestors($this, $fields);
    }

    public function genitor($fields = null) {
      return $this->_getTable()->genitor($this, $fields);
    }

    public function root($fields = null) {
      return $this->_getTable()->root($this, $fields);
    }

    public function grandparent($fields = null) {
      return $this->_getTable()->grandparent($this, $fields);
    }

    public function subtree(
      $range = 0,
      $withTop = FertileForestBehavior::SUBTREE_WITH_TOP_NODE,
      $fields = null
    ) {
      return $this->_getTable()->subtree($this, $range, $withTop, $fields);
    }

    public function descendants($fields = null) {
      return $this->_getTable()->descendants($this, $fields);
    }

    public function children($fields = null) {
      return $this->_getTable()->children($this, $fields);
    }

    public function nthChild($nth = 0, $fields = null) {
      return $this->_getTable()->nthChild($this, $nth, $fields);
    }

    public function grandchildren($fields = null) {
      return $this->_getTable()->grandchildren($this, $fields);
    }

    public function siblings($fields = null) {
      return $this->_getTable()->siblings($this, $fields);
    }

    public function nthSibling($nth = 0, $fields = null) {
      return $this->_getTable()->nthSibling($this, $nth, $fields);
    }

    public function elderSibling($baseObj, $fields = null) {
      return $this->_getTable()->elderSibling($this, $fields);
    }

    public function youngerSibling($baseObj, $fields = null) {
      return $this->_getTable()->youngerSibling($this, $fields);
    }

    public function offsetSibling($offset, $fields = null) {
      return $this->_getTable()->offsetSibling($this, $offset, $fields);
    }

    public function leaves($fields = null) {
      return $this->_getTable()->leaves($this, $fields);
    }

    public function internals($fields = null) {
      return $this->_getTable()->internals($this, $fields);
    }

    public function height() {
      return $this->_getTable()->height($this);
    }

    public function size() {
      return $this->_getTable()->size($this);
    }
}
