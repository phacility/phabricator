<?php

final class PhabricatorDaemonTaskGarbageCollector
  extends PhabricatorGarbageCollector {

  public function collectGarbage() {
    $key = 'gcdaemon.ttl.task-archive';
    $ttl = PhabricatorEnv::getEnvConfig($key);
    if ($ttl <= 0) {
      return false;
    }

    $table = new PhabricatorWorkerArchiveTask();
    $data_table = new PhabricatorWorkerTaskData();
    $conn_w = $table->establishConnection('w');

    $rows = queryfx_all(
      $conn_w,
      'SELECT id, dataID FROM %T WHERE dateCreated < %d LIMIT 100',
      $table->getTableName(),
      time() - $ttl);

    if (!$rows) {
      return false;
    }

    $data_ids = array_filter(ipull($rows, 'dataID'));
    $task_ids = ipull($rows, 'id');

    $table->openTransaction();
      if ($data_ids) {
        queryfx(
          $conn_w,
          'DELETE FROM %T WHERE id IN (%Ld)',
          $data_table->getTableName(),
          $data_ids);
      }
      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE id IN (%Ld)',
        $table->getTableName(),
        $task_ids);
    $table->saveTransaction();

    return (count($task_ids) == 100);
  }

}
