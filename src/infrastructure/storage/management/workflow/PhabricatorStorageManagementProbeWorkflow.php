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

    asort($totals);

    $table = id(new PhutilConsoleTable())
      ->setShowHeader(false)
      ->setPadding(2)
      ->addColumn('name', array('title' => pht('Database / Table')))
      ->addColumn('size', array('title' => pht('Size')))
      ->addColumn('percentage', array('title' => pht('Percentage')));

    foreach ($totals as $db => $size) {
      list($database_size, $database_percentage) = $this->formatSize(
        $totals[$db],
        $overall);

      $table->addRow(array(
        'name' => phutil_console_format('**%s**', $db),
        'size' => phutil_console_format('**%s**', $database_size),
        'percentage' => phutil_console_format('**%s**', $database_percentage),
      ));
      $data[$db] = isort($data[$db], '_totalSize');
      foreach ($data[$db] as $table_name => $info) {
        list($table_size, $table_percentage) = $this->formatSize(
          $info['_totalSize'],
          $overall);

        $table->addRow(array(
          'name' => '    '.$table_name,
          'size' => $table_size,
          'percentage' => $table_percentage,
        ));
      }
    }

    list($overall_size, $overall_percentage) = $this->formatSize(
      $overall,
      $overall);
    $table->addRow(array(
      'name' => phutil_console_format('**%s**', pht('TOTAL')),
      'size' => phutil_console_format('**%s**', $overall_size),
      'percentage' => phutil_console_format('**%s**', $overall_percentage),
    ));

    $table->draw();
    return 0;
  }

  private function formatSize($n, $o) {
    return array(
      sprintf('%8.8s MB', number_format($n / (1024 * 1024), 1)),
      sprintf('%3.1f%%', 100 * ($n / $o)),
    );
  }

}
