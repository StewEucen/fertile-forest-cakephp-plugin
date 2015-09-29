<?php
use Cake\Routing\Router;

Router::plugin('FertileForest', function ($routes) {
  $routes->fallbacks('DashedRoute');
});

//// 2015/09/23 for FertileForest plugin.
Router::connect('/demo', ['controller' => 'Demos', 'action' => 'index', 'plugin' => 'FertileForest']);
Router::connect('/demo/:action', ['controller' => 'Demos', 'plugin' => 'FertileForest']);
