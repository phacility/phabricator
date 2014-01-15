<?php

/**
 * Collects old logs and caches to reduce the amount of data stored in the
 * database.
 *
 * @group daemon
 */
final class PhabricatorGarbageCollectorDaemon extends PhabricatorDaemon {

  public function run() {
    $collectors = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorGarbageCollector')
      ->loadObjects();

    do {
      $n_daemon = $this->collectDaemonLogs();
      $n_parse  = $this->collectParseCaches();
      $n_markup = $this->collectMarkupCaches();
      $n_tasks  = $this->collectArchivedTasks();
      $n_cache_ttl = $this->collectGeneralCacheTTL();
      $n_cache  = $this->collectGeneralCaches();
      $n_files  = $this->collectExpiredFiles();
      $n_clogs  = $this->collectExpiredConduitLogs();
      $n_ccons  = $this->collectExpiredConduitConnections();

      $collected = array(
        'Daemon Log'                  => $n_daemon,
        'Differential Parse Cache'    => $n_parse,
        'Markup Cache'                => $n_markup,
        'Archived Tasks'              => $n_tasks,
        'General Cache TTL'           => $n_cache_ttl,
        'General Cache Entries'       => $n_cache,
        'Temporary Files'             => $n_files,
        'Conduit Logs'                => $n_clogs,
        'Conduit Connections'         => $n_ccons,
      );
      $collected = array_filter($collected);

      foreach ($collected as $thing => $count) {
        $count = number_format($count);
        $this->log("Garbage collected {$count} '{$thing}' objects.");
      }

      $total = array_sum($collected);

      // TODO: This logic is unnecessarily complex for now to facilitate a
      // gradual conversion to the new GC infrastructure.

      $had_more_garbage = false;
      foreach ($collectors as $name => $collector) {
        $more_garbage = false;
        do {
          if ($more_garbage) {
            $this->log(pht('Collecting more garbage with "%s".', $name));
          } else {
            $this->log(pht('Collecting garbage with "%s".', $name));
          }

          $more_garbage = $collector->collectGarbage();
          if ($more_garbage) {
            $had_more_garbage = true;
          }
          $this->stillWorking();
        } while ($more_garbage);
      }

      if ($had_more_garbage) {
        $total += 100;
      }

      if ($total < 100) {
        // We didn't max out any of the GCs so we're basically caught up. Ease
        // off the GC loop so we don't keep doing table scans just to delete
        // a handful of rows; wake up in a few hours.
        $this->sleep(4 * (60 * 60));
      } else {
        $this->stillWorking();
      }
    } while (true);

  }

  private function collectDaemonLogs() {
    $ttl = PhabricatorEnv::getEnvConfig('gcdaemon.ttl.daemon-logs');
    if ($ttl <= 0) {
      return 0;
    }

    $table = new PhabricatorDaemonLogEvent();
    $conn_w = $table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE epoch < %d LIMIT 100',
      $table->getTableName(),
      time() - $ttl);

    return $conn_w->getAffectedRows();
  }

  private function collectParseCaches() {
    $key = 'gcdaemon.ttl.differential-parse-cache';
    $ttl = PhabricatorEnv::getEnvConfig($key);
    if ($ttl <= 0) {
      return 0;
    }

    $table = new DifferentialChangeset();
    $conn_w = $table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE dateCreated < %d LIMIT 100',
      DifferentialChangeset::TABLE_CACHE,
      time() - $ttl);

    return $conn_w->getAffectedRows();
  }

  private function collectMarkupCaches() {
    $key = 'gcdaemon.ttl.markup-cache';
    $ttl = PhabricatorEnv::getEnvConfig($key);
    if ($ttl <= 0) {
      return 0;
    }

    $table = new PhabricatorMarkupCache();
    $conn_w = $table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE dateCreated < %d LIMIT 100',
      $table->getTableName(),
      time() - $ttl);

    return $conn_w->getAffectedRows();
  }

  private function collectArchivedTasks() {
    $key = 'gcdaemon.ttl.task-archive';
    $ttl = PhabricatorEnv::getEnvConfig($key);
    if ($ttl <= 0) {
      return 0;
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
      return 0;
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

    return count($task_ids);
  }


  private function collectGeneralCacheTTL() {
    $cache = new PhabricatorKeyValueDatabaseCache();
    $conn_w = $cache->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE cacheExpires < %d
        ORDER BY cacheExpires ASC LIMIT 100',
      $cache->getTableName(),
      time());

    return $conn_w->getAffectedRows();
  }


  private function collectGeneralCaches() {
    $key = 'gcdaemon.ttl.general-cache';
    $ttl = PhabricatorEnv::getEnvConfig($key);
    if ($ttl <= 0) {
      return 0;
    }

    $cache = new PhabricatorKeyValueDatabaseCache();
    $conn_w = $cache->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE cacheCreated < %d
        ORDER BY cacheCreated ASC LIMIT 100',
      $cache->getTableName(),
      time() - $ttl);

    return $conn_w->getAffectedRows();
  }

  private function collectExpiredFiles() {
    $files = id(new PhabricatorFile())->loadAllWhere('ttl < %d LIMIT 100',
      time());

    foreach ($files as $file) {
      $file->delete();
    }

    return count($files);
  }

  private function collectExpiredConduitLogs() {
    $key = 'gcdaemon.ttl.conduit-logs';
    $ttl = PhabricatorEnv::getEnvConfig($key);
    if ($ttl <= 0) {
      return 0;
    }

    $table = new PhabricatorConduitMethodCallLog();
    $conn_w = $table->establishConnection('w');
    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE dateCreated < %d
        ORDER BY dateCreated ASC LIMIT 100',
      $table->getTableName(),
      time() - $ttl);

    return $conn_w->getAffectedRows();
  }

  private function collectExpiredConduitConnections() {
    $key = 'gcdaemon.ttl.conduit-logs';
    $ttl = PhabricatorEnv::getEnvConfig($key);
    if ($ttl <= 0) {
      return 0;
    }

    $table = new PhabricatorConduitConnectionLog();
    $conn_w = $table->establishConnection('w');
    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE dateCreated < %d
        ORDER BY dateCreated ASC LIMIT 100',
      $table->getTableName(),
      time() - $ttl);

    return $conn_w->getAffectedRows();
  }

}
