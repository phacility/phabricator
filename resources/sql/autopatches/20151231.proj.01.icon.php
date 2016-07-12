<?php

$icon_map = array(
  'fa-briefcase' => 'project',
  'fa-tags' => 'tag',
  'fa-lock' => 'policy',
  'fa-users' => 'group',

  'fa-folder' => 'folder',
  'fa-calendar' => 'timeline',
  'fa-flag-checkered' => 'goal',
  'fa-truck' => 'release',

  'fa-bug' => 'bugs',
  'fa-trash-o' => 'cleanup',
  'fa-umbrella' => 'umbrella',
  'fa-envelope' => 'communication',

  'fa-building' => 'organization',
  'fa-cloud' => 'infrastructure',
  'fa-credit-card' => 'account',
  'fa-flask' => 'experimental',
);

$table = new PhabricatorProject();
$conn_w = $table->establishConnection('w');
foreach ($icon_map as $old_icon => $new_key) {
  queryfx(
    $conn_w,
    'UPDATE %T SET icon = %s WHERE icon = %s',
    $table->getTableName(),
    $new_key,
    $old_icon);
}
