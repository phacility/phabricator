<?php

final class PhabricatorStorageManagementOptimizeWorkflow
  extends PhabricatorStorageManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('optimize')
      ->setExamples('**optimize**')
      ->setSynopsis(pht('Run "OPTIMIZE TABLE" on tables to reclaim space.'));
  }

  public function didExecute(PhutilArgumentParser $args) {
    $api = $this->getSingleAPI();
    $conn = $api->getConn(null);

    $patches = $this->getPatches();
    $databases = $api->getDatabaseList($patches, true);

    $total_bytes = 0;
    foreach ($databases as $database) {
      queryfx($conn, 'USE %C', $database);

      $tables = queryfx_all($conn, 'SHOW TABLE STATUS');
      foreach ($tables as $table) {
        $table_name = $table['Name'];
        $old_bytes =
          $table['Data_length'] +
          $table['Index_length'] +
          $table['Data_free'];

        $this->logInfo(
          pht('OPTIMIZE'),
          pht(
            'Optimizing table "%s"."%s"...',
            $database,
            $table_name));

        $t_start = microtime(true);
        queryfx(
          $conn,
          'OPTIMIZE TABLE %T',
          $table_name);
        $t_end = microtime(true);

        $status = queryfx_one(
          $conn,
          'SHOW TABLE STATUS LIKE %s',
          $table_name);

        $new_bytes =
          $status['Data_length'] +
          $status['Index_length'] +
          $status['Data_free'];

        $duration_ms = (int)(1000 * ($t_end - $t_start));

        if ($old_bytes > $new_bytes) {
          $this->logOkay(
            pht('DONE'),
            pht(
              'Compacted table by %s in %sms.',
              phutil_format_bytes($old_bytes - $new_bytes),
              new PhutilNumber($duration_ms)));
        } else {
          $this->logInfo(
            pht('DONE'),
            pht(
              'Optimized table (in %sms) but it had little effect.',
              new PhutilNumber($duration_ms)));
        }

        $total_bytes += ($old_bytes - $new_bytes);
      }
    }

    $this->logOkay(
      pht('OPTIMIZED'),
      pht(
        'Completed optimizations, reclaimed %s of disk space.',
        phutil_format_bytes($total_bytes)));

    return 0;
  }

}
