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

    $tasks = id(new PhabricatorWorkerArchiveTaskQuery())
      ->withDateCreatedBefore(time() - $ttl)
      ->execute();

    if (!$tasks) {
      return false;
    }

    $data_ids = array_filter(mpull($tasks, 'getDataID'));
    $task_ids = mpull($tasks, 'getID');

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
