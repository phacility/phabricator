<?php

$table = new PhabricatorProfileMenuItemConfiguration();
$conn_w = $table->establishConnection('w');

queryfx(
  $conn_w,
  'DELETE FROM %T WHERE menuItemKey = "motivator"',
  $table->getTableName());
