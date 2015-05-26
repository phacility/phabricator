<?php

final class PhabricatorFilesManagementPurgeWorkflow
  extends PhabricatorFilesManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('purge')
      ->setSynopsis(pht('Delete files with missing data.'))
      ->setArguments(
        array(
          array(
            'name'      => 'all',
            'help'      => pht('Update all files.'),
          ),
          array(
            'name'      => 'dry-run',
            'help'      => pht('Show what would be updated.'),
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
        pht(
          'Either specify a list of files to purge, or use `%s` '.
          'to purge all files.',
          '--all'));
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
          "%s\n",
          pht('%s: File data is OK, not purging.', $fid));
      } else {
        if ($is_dry_run) {
          $console->writeOut(
            "%s\n",
            pht('%s: Would purge (dry run).', $fid));
        } else {
          $console->writeOut(
            "%s\n",
            pht('%s: Purging.', $fid));
          $file->delete();
        }
      }
    }

    return 0;
  }
}
