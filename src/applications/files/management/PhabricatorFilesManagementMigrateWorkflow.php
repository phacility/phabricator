<?php

final class PhabricatorFilesManagementMigrateWorkflow
  extends PhabricatorFilesManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('migrate')
      ->setSynopsis(pht('Migrate files between storage engines.'))
      ->setArguments(
        array(
          array(
            'name'      => 'engine',
            'param'     => 'storage_engine',
            'help'      => pht('Migrate to the named storage engine.'),
          ),
          array(
            'name'      => 'dry-run',
            'help'      => pht('Show what would be migrated.'),
          ),
          array(
            'name'      => 'all',
            'help'      => pht('Migrate all files.'),
          ),
          array(
            'name'      => 'names',
            'wildcard'  => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $target_key = $args->getArg('engine');
    if (!$target_key) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify an engine to migrate to with `%s`. '.
          'Use `%s` to get a list of engines.',
          '--engine',
          'files engines'));
    }

    $target_engine = PhabricatorFile::buildEngine($target_key);

    $iterator = $this->buildIterator($args);
    if (!$iterator) {
      throw new PhutilArgumentUsageException(
        pht(
          'Either specify a list of files to migrate, or use `%s` '.
          'to migrate all files.',
          '--all'));
    }

    $is_dry_run = $args->getArg('dry-run');

    $failed = array();
    $engines = PhabricatorFileStorageEngine::loadAllEngines();
    foreach ($iterator as $file) {
      $monogram = $file->getMonogram();

      $engine_key = $file->getStorageEngine();
      $engine = idx($engines, $engine_key);

      if (!$engine) {
        echo tsprintf(
          "%s\n",
          pht(
            '%s: Uses unknown storage engine "%s".',
            $monogram,
            $engine_key));
        $failed[] = $file;
        continue;
      }

      if ($engine->isChunkEngine()) {
        echo tsprintf(
          "%s\n",
          pht(
            '%s: Stored as chunks, no data to migrate directly.',
            $monogram));
        continue;
      }

      if ($engine_key === $target_key) {
        echo tsprintf(
          "%s\n",
          pht(
            '%s: Already stored in engine "%s".',
            $monogram,
            $target_key));
        continue;
      }

      if ($is_dry_run) {
        echo tsprintf(
          "%s\n",
          pht(
            '%s: Would migrate from "%s" to "%s" (dry run).',
            $monogram,
            $engine_key,
            $target_key));
        continue;
      }

      echo tsprintf(
        "%s\n",
        pht(
          '%s: Migrating from "%s" to "%s"...',
          $monogram,
          $engine_key,
          $target_key));

      try {
        $file->migrateToEngine($target_engine);

        echo tsprintf(
          "%s\n",
          pht('Done.'));

      } catch (Exception $ex) {
        echo tsprintf(
          "%s\n",
          pht('Failed! %s', (string)$ex));
        $failed[] = $file;

        throw $ex;
      }
    }

    if ($failed) {
      $monograms = mpull($failed, 'getMonogram');

      echo tsprintf(
        "%s\n",
        pht('Failures: %s.', implode(', ', $monograms)));

      return 1;
    }

    return 0;
  }

}
