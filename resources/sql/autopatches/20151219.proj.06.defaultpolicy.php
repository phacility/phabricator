<?php

$app = PhabricatorApplication::getByClass('PhabricatorProjectApplication');

$view_policy = $app->getPolicy(ProjectDefaultViewCapability::CAPABILITY);
$edit_policy = $app->getPolicy(ProjectDefaultEditCapability::CAPABILITY);
$join_policy = $app->getPolicy(ProjectDefaultJoinCapability::CAPABILITY);

$table = new PhabricatorProject();
$conn_w = $table->establishConnection('w');

queryfx(
  $conn_w,
  'UPDATE %T SET viewPolicy = %s WHERE viewPolicy IS NULL',
  $table->getTableName(),
  $view_policy);

queryfx(
  $conn_w,
  'UPDATE %T SET editPolicy = %s WHERE editPolicy IS NULL',
  $table->getTableName(),
  $edit_policy);

queryfx(
  $conn_w,
  'UPDATE %T SET joinPolicy = %s WHERE joinPolicy IS NULL',
  $table->getTableName(),
  $join_policy);
