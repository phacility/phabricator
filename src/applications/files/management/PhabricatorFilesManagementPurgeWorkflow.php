<?php

final class PhabricatorFilesManagementPurgeWorkflow
  extends PhabricatorFilesManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('purge')
      ->setSynopsis('Delete files with missing data.')
      ->setArguments(
        array(
          array(
            'name'      => 'all',
            'help'      => 'Update all files.',
          ),
          array(
            'name'      => 'dry-run',
            'help'      => 'Show what would be updated.',
          ),
          array(
            'name'      => 'names',
            'wildcard'  => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $iterator = $this->buildIterator($args);
    if (!$iterator) {
      throw new PhutilArgumentUsageException(
        'Either specify a list of files to purge, or use `--all` '.
        'to purge all files.');
    }

    $is_dry_run = $args->getArg('dry-run');

    foreach ($iterator as $file) {
      $fid = 'F'.$file->getID();

      try {
        $file->loadFileData();
        $okay = true;
      } catch (Exception $ex) {
        $okay = false;
      }

      if ($okay) {
        $console->writeOut(
          "%s: File data is OK, not purging.\n",
          $fid);
      } else {
        if ($is_dry_run) {
          $console->writeOut(
            "%s: Would purge (dry run).\n",
            $fid);
        } else {
          $console->writeOut(
            "%s: Purging.\n",
            $fid);
          $file->delete();
        }
      }
    }

    return 0;
  }
}
