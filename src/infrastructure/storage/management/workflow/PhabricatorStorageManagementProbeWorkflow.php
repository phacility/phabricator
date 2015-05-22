<?php

final class PhabricatorStorageManagementProbeWorkflow
  extends PhabricatorStorageManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('probe')
      ->setExamples('**probe**')
      ->setSynopsis(pht('Show approximate table sizes.'));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $console->writeErr(
      "%s\n",
      pht('Analyzing table sizes (this may take a moment)...'));

    $api = $this->getAPI();
    $patches = $this->getPatches();
    $databases = $api->getDatabaseList($patches, $only_living = true);

    $conn_r = $api->getConn(null);

    $data = array();
    foreach ($databases as $database) {
      queryfx($conn_r, 'USE %C', $database);
      $tables = queryfx_all(
        $conn_r,
        'SHOW TABLE STATUS');
      $tables = ipull($tables, null, 'Name');
      $data[$database] = $tables;
    }

    $totals = array_fill_keys(array_keys($data), 0);
    $overall = 0;

    foreach ($data as $db => $tables) {
      foreach ($tables as $table => $info) {
        $table_size = $info['Data_length'] + $info['Index_length'];

        $data[$db][$table]['_totalSize'] = $table_size;
        $totals[$db] += $table_size;
        $overall += $table_size;
      }
    }

    $console->writeOut("%s\n", pht('APPROXIMATE TABLE SIZES'));
    asort($totals);
    foreach ($totals as $db => $size) {
      $database_size = $this->formatSize($totals[$db], $overall);
      $console->writeOut(
        "**%s**\n",
        sprintf('%-32.32s %18s', $db, $database_size));
      $data[$db] = isort($data[$db], '_totalSize');
      foreach ($data[$db] as $table => $info) {
        $table_size = $this->formatSize($info['_totalSize'], $overall);
        $console->writeOut(
          "%s\n",
          sprintf('    %-28.28s %18s', $table, $table_size));
      }
    }
    $overall_size = $this->formatSize($overall, $overall);
    $console->writeOut(
      "**%s**\n",
      sprintf('%-32.32s %18s', pht('TOTAL'), $overall_size));

    return 0;
  }

  private function formatSize($n, $o) {
    return sprintf(
      '%8.8s MB  %5.5s%%',
      number_format($n / (1024 * 1024), 1),
      sprintf('%3.1f', 100 * ($n / $o)));
  }

}
