<?php

final class PhabricatorDifferentialMigrateHunkWorkflow
  extends PhabricatorDifferentialManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('migrate-hunk')
      ->setExamples(
        "**migrate-hunk** --id __hunk__ --to __storage__\n".
        "**migrate-hunk** --all")
      ->setSynopsis(pht('Migrate storage engines for a hunk.'))
      ->setArguments(
        array(
          array(
            'name' => 'id',
            'param' => 'id',
            'help' => pht('Hunk ID to migrate.'),
          ),
          array(
            'name' => 'to',
            'param' => 'storage',
            'help' => pht('Storage engine to migrate to.'),
          ),
          array(
            'name' => 'all',
            'help' => pht('Migrate all hunks.'),
          ),
          array(
            'name' => 'auto',
            'help' => pht('Select storage format automatically.'),
          ),
          array(
            'name' => 'dry-run',
            'help' => pht('Show planned writes but do not perform them.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $is_dry_run = $args->getArg('dry-run');

    $id = $args->getArg('id');
    $is_all = $args->getArg('all');

    if ($is_all && $id) {
      throw new PhutilArgumentUsageException(
        pht(
          'Options "--all" (to migrate all hunks) and "--id" (to migrate a '.
          'specific hunk) are mutually exclusive.'));
    } else if (!$is_all && !$id) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a hunk to migrate with "--id", or migrate all hunks '.
          'with "--all".'));
    }

    $is_auto = $args->getArg('auto');
    $storage = $args->getArg('to');
    if ($is_auto && $storage) {
      throw new PhutilArgumentUsageException(
        pht(
          'Options "--to" (to choose a specific storage format) and "--auto" '.
          '(to select a storage format automatically) are mutually '.
          'exclusive.'));
    } else if (!$is_auto && !$storage) {
      throw new PhutilArgumentUsageException(
        pht(
          'Use "--to" to choose a storage format, or "--auto" to select a '.
          'format automatically.'));
    }

    $types = array(
      DifferentialHunk::DATATYPE_TEXT,
      DifferentialHunk::DATATYPE_FILE,
    );
    $types = array_fuse($types);
    if (strlen($storage)) {
      if (!isset($types[$storage])) {
        throw new PhutilArgumentUsageException(
          pht(
            'Storage type "%s" is unknown. Supported types are: %s.',
            $storage,
            implode(', ', array_keys($types))));
      }
    }

    if ($id) {
      $hunk = $this->loadHunk($id);
      $hunks = array($hunk);
    } else {
      $hunks = new LiskMigrationIterator(new DifferentialHunk());
    }

    foreach ($hunks as $hunk) {
      try {
        $this->migrateHunk($hunk, $storage, $is_auto, $is_dry_run);
      } catch (Exception $ex) {
        // If we're migrating a single hunk, just throw the exception. If
        // we're migrating multiple hunks, warn but continue.
        if ($id) {
          throw $ex;
        }

        $this->logWarn(
          pht('WARN'),
          pht(
            'Failed to migrate hunk %d: %s',
            $hunk->getID(),
            $ex->getMessage()));
      }
    }

    return 0;
  }

  private function loadHunk($id) {
    $hunk = id(new DifferentialHunk())->load($id);
    if (!$hunk) {
      throw new PhutilArgumentUsageException(
        pht(
          'No hunk exists with ID "%s".',
          $id));
    }

    return $hunk;
  }

  private function migrateHunk(
    DifferentialHunk $hunk,
    $type,
    $is_auto,
    $is_dry_run) {

    $old_type = $hunk->getDataType();

    if ($is_auto) {
      // By default, we're just going to keep hunks in the same storage
      // engine. In the future, we could perhaps select large hunks stored in
      // text engine and move them into file storage.
      $new_type = $old_type;
    } else {
      $new_type = $type;
    }

    // Figure out if the storage format (e.g., plain text vs compressed)
    // would change if we wrote this hunk anew today.
    $old_format = $hunk->getDataFormat();
    $new_format = $hunk->getAutomaticDataFormat();

    $same_type = ($old_type === $new_type);
    $same_format = ($old_format === $new_format);

    // If we aren't going to change the storage engine and aren't going to
    // change the storage format, just bail out.
    if ($same_type && $same_format) {
      $this->logInfo(
        pht('SKIP'),
        pht(
          'Hunk %d is already stored in the preferred engine ("%s") '.
          'with the preferred format ("%s").',
          $hunk->getID(),
          $new_type,
          $new_format));
      return;
    }

    if ($is_dry_run) {
      $this->logOkay(
        pht('DRY RUN'),
        pht(
          'Hunk %d would be rewritten (storage: "%s" -> "%s"; '.
          'format: "%s" -> "%s").',
          $hunk->getID(),
          $old_type,
          $new_type,
          $old_format,
          $new_format));
      return;
    }

    $old_data = $hunk->getChanges();

    switch ($new_type) {
      case DifferentialHunk::DATATYPE_TEXT:
        $hunk->saveAsText();
        break;
      case DifferentialHunk::DATATYPE_FILE:
        $hunk->saveAsFile();
        break;
    }

    $this->logOkay(
      pht('MIGRATE'),
      pht(
        'Converted hunk %d to "%s" storage (with format "%s").',
        $hunk->getID(),
        $new_type,
        $hunk->getDataFormat()));

    $hunk = $this->loadHunk($hunk->getID());
    $new_data = $hunk->getChanges();

    if ($old_data !== $new_data) {
      throw new Exception(
        pht(
          'Integrity check failed: new file data differs from old data!'));
    }
  }


}
