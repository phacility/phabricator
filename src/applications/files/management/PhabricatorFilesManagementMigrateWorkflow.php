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
    $console = PhutilConsole::getConsole();

    $engine_id = $args->getArg('engine');
    if (!$engine_id) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify an engine to migrate to with `%s`. '.
          'Use `%s` to get a list of engines.',
          '--engine',
          'files engines'));
    }

    $engine = PhabricatorFile::buildEngine($engine_id);

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

    foreach ($iterator as $file) {
      $fid = 'F'.$file->getID();

      if ($file->getStorageEngine() == $engine_id) {
        $console->writeOut(
          "%s\n",
          pht(
            "%s: Already stored on '%s'",
            $fid,
            $engine_id));
        continue;
      }

      if ($is_dry_run) {
        $console->writeOut(
          "%s\n",
          pht(
            "%s: Would migrate from '%s' to '%s' (dry run)",
            $fid,
            $file->getStorageEngine(),
            $engine_id));
        continue;
      }

      $console->writeOut(
        "%s\n",
        pht(
          "%s: Migrating from '%s' to '%s'...",
          $fid,
          $file->getStorageEngine(),
          $engine_id));

      try {
        $file->migrateToEngine($engine);
        $console->writeOut("%s\n", pht('Done.'));
      } catch (Exception $ex) {
        $console->writeOut("%s\n", pht('Failed!'));
        $console->writeErr("%s\n", (string)$ex);
        $failed[] = $file;
      }
    }

    if ($failed) {
      $console->writeOut("**%s**\n", pht('Failures!'));
      $ids = array();
      foreach ($failed as $file) {
        $ids[] = 'F'.$file->getID();
      }
      $console->writeOut("%s\n", implode(', ', $ids));

      return 1;
    } else {
      $console->writeOut("**%s**\n", pht('Success!'));
      return 0;
    }
  }

}
