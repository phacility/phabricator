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
        ));
  }

  public function execute(PhutilArgumentParser $args) {
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

    $storage = $args->getArg('to');
    switch ($storage) {
      case DifferentialHunk::DATATYPE_TEXT:
      case DifferentialHunk::DATATYPE_FILE:
        break;
      default:
        throw new PhutilArgumentUsageException(
          pht('Specify a hunk storage engine with --to.'));
    }

    if ($id) {
      $hunk = $this->loadHunk($id);
      $hunks = array($hunk);
    } else {
      $hunks = new LiskMigrationIterator(new DifferentialHunk());
    }

    foreach ($hunks as $hunk) {
      try {
        $this->migrateHunk($hunk, $storage);
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

  private function migrateHunk(DifferentialHunk $hunk, $format) {
    $old_data = $hunk->getChanges();

    switch ($format) {
      case DifferentialHunk::DATATYPE_TEXT:
        $hunk->saveAsText();
        $this->logOkay(
          pht('TEXT'),
          pht('Converted hunk to text storage.'));
        break;
      case DifferentialHunk::DATATYPE_FILE:
        $hunk->saveAsFile();
        $this->logOkay(
          pht('FILE'),
          pht('Converted hunk to file storage.'));
        break;
    }

    $hunk = $this->loadHunk($hunk->getID());
    $new_data = $hunk->getChanges();

    if ($old_data !== $new_data) {
      throw new Exception(
        pht(
          'Integrity check failed: new file data differs fom old data!'));
    }
  }


}
