<?php

final class PhabricatorStorageManagementUpgradeWorkflow
  extends PhabricatorStorageManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('upgrade')
      ->setExamples('**upgrade** [__options__]')
      ->setSynopsis(pht('Upgrade database schemata.'))
      ->setArguments(
        array(
          array(
            'name'  => 'apply',
            'param' => 'patch',
            'help'  => pht(
              'Apply __patch__ explicitly. This is an advanced feature for '.
              'development and debugging; you should not normally use this '.
              'flag. This skips adjustment.'),
          ),
          array(
            'name'  => 'no-quickstart',
            'help'  => pht(
              'Build storage patch-by-patch from scratch, even if it could '.
              'be loaded from the quickstart template.'),
          ),
          array(
            'name'  => 'init-only',
            'help'  => pht(
              'Initialize storage only; do not apply patches or adjustments.'),
          ),
          array(
            'name' => 'no-adjust',
            'help' => pht(
              'Do not apply storage adjustments after storage upgrades.'),
          ),
        ));
  }

  public function didExecute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $patches = $this->getPatches();

    if (!$this->isDryRun() && !$this->isForce()) {
      $console->writeOut(
        phutil_console_wrap(
          pht(
            'Before running storage upgrades, you should take down the '.
            'Phabricator web interface and stop any running Phabricator '.
            'daemons (you can disable this warning with %s).',
            '--force')));

      if (!phutil_console_confirm(pht('Are you ready to continue?'))) {
        $console->writeOut("%s\n", pht('Cancelled.'));
        return 1;
      }
    }

    $apply_only = $args->getArg('apply');
    if ($apply_only) {
      if (empty($patches[$apply_only])) {
        throw new PhutilArgumentUsageException(
          pht(
            "%s argument '%s' is not a valid patch. ".
            "Use '%s' to show patch status.",
            '--apply',
            $apply_only,
            './bin/storage status'));
      }
    }

    $no_quickstart = $args->getArg('no-quickstart');
    $init_only     = $args->getArg('init-only');
    $no_adjust     = $args->getArg('no-adjust');

    $apis = $this->getMasterAPIs();

    $this->upgradeSchemata($apis, $apply_only, $no_quickstart, $init_only);

    if ($apply_only || $init_only) {
      echo tsprintf(
        "%s\n",
        pht('Declining to synchronize static tables.'));
    } else {
      echo tsprintf(
        "%s\n",
        pht('Synchronizing static tables...'));
      $this->synchronizeSchemata();
    }

    if ($no_adjust || $init_only || $apply_only) {
      $console->writeOut(
        "%s\n",
        pht('Declining to apply storage adjustments.'));
    } else {
      foreach ($apis as $api) {
        $err = $this->adjustSchemata($api, false);
        if ($err) {
          return $err;
        }
      }
    }

    return 0;
  }

  private function synchronizeSchemata() {
    // Synchronize the InnoDB fulltext stopwords table from the stopwords file
    // on disk.

    $table = new PhabricatorSearchDocument();
    $conn = $table->establishConnection('w');
    $table_ref = PhabricatorSearchDocument::STOPWORDS_TABLE;

    $stopwords_database = queryfx_all(
      $conn,
      'SELECT value FROM %T',
      $table_ref);
    $stopwords_database = ipull($stopwords_database, 'value', 'value');

    $stopwords_path = phutil_get_library_root('phabricator');
    $stopwords_path = $stopwords_path.'/../resources/sql/stopwords.txt';
    $stopwords_file = Filesystem::readFile($stopwords_path);
    $stopwords_file = phutil_split_lines($stopwords_file, false);
    $stopwords_file = array_fuse($stopwords_file);

    $rem_words = array_diff_key($stopwords_database, $stopwords_file);
    if ($rem_words) {
      queryfx(
        $conn,
        'DELETE FROM %T WHERE value IN (%Ls)',
        $table_ref,
        $rem_words);
    }

    $add_words = array_diff_key($stopwords_file, $stopwords_database);
    if ($add_words) {
      foreach ($add_words as $word) {
        queryfx(
          $conn,
          'INSERT IGNORE INTO %T (value) VALUES (%s)',
          $table_ref,
          $word);
      }
    }
  }


}
