<?php
  $nestedHashInfo = $app['nestedHashInfo'];

  $idHash = [];
  foreach ($nestedHashInfo as $theGrove => $nodes) {
      $idHash += $nodes;
  }
  $allIDs = array_keys($idHash);

  if (empty($idHash)) {
    return false;
  }

  $app += [
    'idHash' => $idHash,
    'allIDs' => $allIDs,
  ];

  uksort($nestedHashInfo, function($a, $b) {return $b - $a;});
  foreach ($nestedHashInfo as $theGrove => $nodes) {
    echo $this->element('RenderGroves', ['app' => $app, 'theGrove' => $theGrove, 'nodes' => $nodes]);
  }
