<?php

$pull = new PhabricatorRepositoryPullEvent();
$push = new PhabricatorRepositoryPushEvent();

$conn_w = $pull->establishConnection('w');

$log_types = array($pull, $push);
foreach ($log_types as $log) {
  foreach (new LiskMigrationIterator($log) as $row) {
    $addr = $row->getRemoteAddress();

    $addr = (string)$addr;
    if (!strlen($addr)) {
      continue;
    }

    if (!ctype_digit($addr)) {
      continue;
    }

    if (!(int)$addr) {
      continue;
    }

    $ip = long2ip($addr);
    if (!is_string($ip) || !strlen($ip)) {
      continue;
    }

    $id = $row->getID();
    queryfx(
      $conn_w,
      'UPDATE %T SET remoteAddress = %s WHERE id = %d',
      $log->getTableName(),
      $ip,
      $id);
  }
}
