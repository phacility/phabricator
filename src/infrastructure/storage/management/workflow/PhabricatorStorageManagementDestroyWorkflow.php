<?php

final class PhabricatorStorageManagementDestroyWorkflow
  extends PhabricatorStorageManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('destroy')
      ->setExamples('**destroy** [__options__]')
      ->setSynopsis('Permanently destroy all storage and data.')
      ->setArguments(
        array(
          array(
            'name'  => 'unittest-fixtures',
            'help'  => "Restrict **destroy** operations to databases created ".
                       "by PhabricatorTestCase test fixtures.",
          )));
  }

  public function execute(PhutilArgumentParser $args) {
    $is_dry = $args->getArg('dryrun');
    $is_force = $args->getArg('force');

    if (!$is_dry && !$is_force) {
      echo phutil_console_wrap(
        "Are you completely sure you really want to permanently destroy all ".
        "storage for Phabricator data? This operation can not be undone and ".
        "your data will not be recoverable if you proceed.");

      if (!phutil_console_confirm('Permanently destroy all data?')) {
        echo "Cancelled.\n";
        exit(1);
      }

      if (!phutil_console_confirm('Really destroy all data forever?')) {
        echo "Cancelled.\n";
        exit(1);
      }
    }

    $api = $this->getAPI();
    $patches = $this->getPatches();

    if ($args->getArg('unittest-fixtures')) {
      $conn = $api->getConn(null, false);
      $databases = queryfx_all(
        $conn,
        'SELECT DISTINCT(TABLE_SCHEMA) AS db '.
        'FROM INFORMATION_SCHEMA.TABLES '.
        'WHERE TABLE_SCHEMA LIKE %>',
        PhabricatorTestCase::NAMESPACE_PREFIX);
      $databases = ipull($databases, 'db');
    } else {
      $databases = $api->getDatabaseList($patches);
      $databases[] = $api->getDatabaseName('meta_data');
    }

    foreach ($databases as $database) {
      if ($is_dry) {
        echo "DRYRUN: Would drop database '{$database}'.\n";
      } else {
        echo "Dropping database '{$database}'...\n";
        queryfx(
          $api->getConn('meta_data', $select_database = false),
          'DROP DATABASE IF EXISTS %T',
          $database);
      }
    }

    if (!$is_dry) {
      echo "Storage was destroyed.\n";
    }

    return 0;
  }

}
