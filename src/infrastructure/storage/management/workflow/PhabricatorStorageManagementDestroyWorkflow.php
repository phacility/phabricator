<?php

final class PhabricatorStorageManagementDestroyWorkflow
  extends PhabricatorStorageManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('destroy')
      ->setExamples('**destroy** [__options__]')
      ->setSynopsis(pht('Permanently destroy all storage and data.'))
      ->setArguments(
        array(
          array(
            'name'  => 'unittest-fixtures',
            'help'  => pht(
              'Restrict **destroy** operations to databases created '.
              'by %s test fixtures.',
              'PhabricatorTestCase'),
          ),
        ));
  }

  public function didExecute(PhutilArgumentParser $args) {
    $api = $this->getSingleAPI();

    $host_display = $api->getDisplayName();

    if (!$this->isDryRun() && !$this->isForce()) {
      if ($args->getArg('unittest-fixtures')) {
        $warning = pht(
          'Are you completely sure you really want to destroy all unit '.
          'test fixure data on host "%s"? This operation can not be undone.',
          $host_display);

        echo tsprintf(
          '%B',
          id(new PhutilConsoleBlock())
            ->addParagraph($warning)
            ->drawConsoleString());

        if (!phutil_console_confirm(pht('Destroy all unit test data?'))) {
          $this->logFail(
            pht('CANCELLED'),
            pht('User cancelled operation.'));
          exit(1);
        }
      } else {
        $warning = pht(
          'Are you completely sure you really want to permanently destroy '.
          'all storage for %s data on host "%s"? This operation '.
          'can not be undone and your data will not be recoverable if '.
          'you proceed.',
          PlatformSymbols::getPlatformServerName(),
          $host_display);

        echo tsprintf(
          '%B',
          id(new PhutilConsoleBlock())
            ->addParagraph($warning)
            ->drawConsoleString());

        if (!phutil_console_confirm(pht('Permanently destroy all data?'))) {
          $this->logFail(
            pht('CANCELLED'),
            pht('User cancelled operation.'));
          exit(1);
        }

        if (!phutil_console_confirm(pht('Really destroy all data forever?'))) {
          $this->logFail(
            pht('CANCELLED'),
            pht('User cancelled operation.'));
          exit(1);
        }
      }
    }

    $patches = $this->getPatches();

    if ($args->getArg('unittest-fixtures')) {
      $conn = $api->getConn(null);
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

      // These are legacy databases that were dropped long ago. See T2237.
      $databases[] = $api->getDatabaseName('phid');
      $databases[] = $api->getDatabaseName('directory');
    }

    asort($databases);

    foreach ($databases as $database) {
      if ($this->isDryRun()) {
        $this->logInfo(
          pht('DRY RUN'),
          pht(
            'Would drop database "%s" on host "%s".',
            $database,
            $host_display));
      } else {
        $this->logWarn(
          pht('DESTROY'),
          pht(
            'Dropping database "%s" on host "%s"...',
            $database,
            $host_display));

        queryfx(
          $api->getConn(null),
          'DROP DATABASE IF EXISTS %T',
          $database);
      }
    }

    if (!$this->isDryRun()) {
      $this->logOkay(
        pht('DONE'),
        pht(
          'Storage on "%s" was destroyed.',
          $host_display));
    }

    return 0;
  }

}
