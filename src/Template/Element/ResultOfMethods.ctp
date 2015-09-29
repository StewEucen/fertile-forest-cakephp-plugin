<?php
  $findInfo = $app['findInfo'];
  if (empty($findInfo)) {
    return false;
  }

  $commandName = array_keys($findInfo)[0];
?>
<section>
  <h1>Result of Methods<br><?= $findInfo['params'] ?></h1>
  <?php
    $infos = $findInfo[$commandName];
    if (empty($infos)) :
      echo '<div>(no results)</div>';
      return false;
    endif;
  ?>

  <?php
    foreach ($infos as $node) :
      if ($node instanceof Entity) {
        echo "<div>[{$node->id}]</div>";
        continue;
      }

      $theType = gettype($node);
      switch ($theType) :
        case 'boolean':
          $node = $node
            ? '<span style="color:blue;">true</span>'
            : '<span style="color:red;">false</span>'
          ;
          break;
        case 'integer':
          $node = (int)$node;
          break;
        case 'array':
          $node = join(',', $node);
          break;
        case 'object':
          $value = $node->id ?: $node->ff_grove;
          $node = "[{$value}]";
          break;
        default:
          break;
      endswitch;
      echo "<div>{$node} (as {$theType})</div>";
    endforeach;
  ?>
</section>
