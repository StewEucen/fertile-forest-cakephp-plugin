<?php
namespace FertileForest\Controller;

use Cake\Event\Event;
use Cake\ORM\TableRegistry;

use FertileForest\Controller\AppController;

class DemosController extends AppController
{
  protected $_modelName = null;
  public $_app = [];

  public function beforeFilter(Event $event) {
    parent::beforeFilter($event);
  }

  public function afterFilter(Event $event) {
    parent::afterFilter($event);
  }

  public function index() {
  }

  public function singleWoods() {
    $this->_modelName = 'SingleWoods';

    $this->_showBeforeFilter();
    $this->_commonExecute();
    $this->_getAllNodes();
    $this->_showAfterFilter();
  }

  public function multipleWoods() {
    $this->_modelName = 'MultipleWoods';

    $this->_showBeforeFilter();
    $this->_commonExecute();
    $this->_getAllNodes();
    $this->_showAfterFilter();
  }

  public function saveMultipleWoods() {
    $this->_modelName = 'MultipleWoods';
    if ($this->request->is('post')) {
      $this->_saveNode();
    }

    $this->_saveAfterFilter();
  }

  public function saveSingleWoods() {
    $this->_modelName = 'SingleWoods';
    if ($this->request->is('post')) {
      $this->_saveNode();
    }

    $this->_saveAfterFilter();
  }

  //////////////////////////////////////////////////////////////////////////////
  // Inner methods.
  //////////////////////////////////////////////////////////////////////////////

  protected function _showBeforeFilter() {
    $this->_app['modelName'] = $this->_modelName;

    $this->loadModel('FertileForest.' . $this->_modelName);

    $this->_app['hasGrove'] = $this->{$this->_modelName}->hasGrove();
  }

  protected function _showAfterFilter() {
      // newEntity for <form> in template
      $this->_app['formEntity'] = $this->{$this->_modelName}->newEntity();

      $this->set('app', $this->_app);
      $this->render('show');
  }

  protected function _saveAfterFilter() {
    $this->set('app', $this->_app);
    $this->render('save');
  }

  /**
   * Execute methods of Fertile Forest behavior.
   */
  protected function _commonExecute() {
    $modelName = $this->_modelName;

    $aimParams = $this->request->query
      + ['command' => null, 'p1' => [], 'p2' => [], 'p3' => [], 'p4' => []]
    ;
    {
        $subCommand = $aimParams['command'];
        $p1 = $aimParams['p1'];
        $p2 = $aimParams['p2'];
        $p3 = $aimParams['p3'];
        $p4 = $aimParams['p4'];
    }
    $this->_app['commandBaseNodeID'] = (int)$p1;

    $views = [];
    switch ($subCommand) {
        case 'ancestors':
        case 'children':
        case 'descendants':
        case 'siblings':
        case 'leaves':
        case 'internals':
        case 'grandchildren':
            $res = $this->{$modelName}->{$subCommand}($p1);
            $views[$subCommand] = empty($res) ? [] : $res;
            $views['params'] = "{$subCommand}({$p1})";
            break;

        case 'nthChild':
        case 'offsetSibling':
        case 'nthSibling':
            $p2 = (int)$p2;
            $res = $this->{$modelName}->{$subCommand}($p1, $p2);
            $views[$subCommand] = [$res];
            $views['params'] = "{$subCommand}({$p1}, {$p2})";
            break;

        case 'parent':
        case 'genitor':
        case 'root':
        case 'grandparent':
        case 'elderSibling':
        case 'youngerSibling':
            $res = $this->{$modelName}->{$subCommand}($p1);
            $views[$subCommand] = empty($res) ? [] : [$res];
            $views['params'] = "{$subCommand}({$p1})";
            break;

        case 'isRoot':
        case 'hasDescendant':
        case 'isLeaf':
        case 'isInternal':
        case 'hasSibling':
        case 'isOnlyChild':
            $res = $this->{$modelName}->{$subCommand}($p1);
            $views[$subCommand] = [$res];
            $views['params'] = "{$subCommand}({$p1})";
            break;

        case 'isDescendant':
        case 'isAncestor':
            $res = $this->{$modelName}->{$subCommand}($p1, $p2);
            $results = [];
            foreach ($res as $i => $result) {
                $results[] = "[{$i}] " . ($result ? 'true' : '<span style="color:red;">false</span>');
            }
            $views[$subCommand] = $results;
            $views['params'] = "{$subCommand}({$p1}, [...])";
            break;

        case 'height':
        case 'size':
            $res = $this->{$modelName}->{$subCommand}($p1);
            $views[$subCommand] = [$res];
            $views['params'] = "{$subCommand}({$p1})";
            break;

        case 'groves':
            $res = $this->{$modelName}->{$subCommand}();
            $views[$subCommand] = empty($res) ? [] : $res->toArray();
            $views['params'] = "{$subCommand}()";
            break;

        case 'graft':
            if ($p3 === 'true' || $p3 === 'false') {
                    $relation = $p3 === 'true';
            } else {
                    $relation = (int)$p3;
            }
            $res = $this->{$modelName}->{$subCommand}($p1, $p2, $relation);
            $views[$subCommand] = [$res];
            $views['params'] = "{$subCommand}({$p1}, {$p2}, {$p3})";
            break;

        case 'permute':
        case 'reorder':
            // command=permute&p1[]=5&p1[]=20
            $res = $this->{$modelName}->{$subCommand}($p1);
            $views[$subCommand] = [$res];
            $views['params'] = "{$subCommand}(" . join(',', $p1) . ")";
            break;
        case 'moveTo':
        case 'moveBy':
            // command=moveTo&p1=12&p2=1
            $res = $this->{$modelName}->{$subCommand}($p1, $p2);
            $views[$subCommand] = [$res];
            $views['params'] = "{$subCommand}({$p1}, {$p2})";
            break;

        case 'remove':
        case 'extinguish':
        case 'terminalize':
        case 'pollard':
            $res = $this->{$modelName}->{$subCommand}($p1);
            $views[$subCommand] = [$res];
            $views['params'] = "{$subCommand}({$p1})";
            break;

        case 'normalize':
            $res = $this->{$modelName}->{$subCommand}($p1, $p2);
            $views[$subCommand] = [$res];
            $views['params'] = "{$subCommand}({$p1}, {$p2})";
            break;

        default:
            break;
    }

    $this->_app['findInfo'] = $views;
  }

  protected function _getAllNodes() {
    $modelName = $this->_modelName;

    $foundRes = $this->{$modelName}->groveNodes();

    $nested = $this->{$modelName}->nestedNodes($foundRes);
    $this->_app['nestedHashInfo'] = $this->{$modelName}->hasGrove()
        ? $nested
        : [$nested];
  }

  protected function _saveNode() {
    $modelName = $this->_modelName;
    $this->_app['modelName'] = $modelName;

    $table = TableRegistry::get('FertileForest.' . $modelName);

    $postNodeEntity = $table->newEntity($this->request->data);

    $res = $table->sprout($postNodeEntity);

    if ($res) {
        $this->Flash->success('Saved new node !');
    } else {
        $err = $postNodeEntity->errors();
        $messages = [];
        foreach ($err as $field => $msgs) {
            $messages[] = "[{$field}] " . join(' ', $msgs);
        }
        $this->Flash->error(join('', $messages));
    }

    return $res;
  }
}
