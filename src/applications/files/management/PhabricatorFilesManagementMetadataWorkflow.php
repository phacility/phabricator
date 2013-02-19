<?php

final class PhabricatorFilesManagementMetadataWorkflow
  extends PhabricatorFilesManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('metadata')
      ->setSynopsis('Update metadata of old files.')
      ->setArguments(
        array(
          array(
            'name'      => 'all',
            'help'      => 'Update all files.',
          ),
          array(
            'name'      => 'names',
            'wildcard'  => true,
            'help'      => 'Update the given files.',
          ),
          array(
            'name'      => 'dry-run',
            'help'      => 'Show what would be updated.',
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    if ($args->getArg('all')) {
      if ($args->getArg('names')) {
        throw new PhutilArgumentUsageException(
          "Specify either a list of files or `--all`, but not both.");
      }
      $iterator = new LiskMigrationIterator(new PhabricatorFile());
    } else if ($args->getArg('names')) {
      $iterator = array();

      foreach ($args->getArg('names') as $name) {
        $name = trim($name);

        $id = preg_replace('/^F/i', '', $name);
        if (ctype_digit($id)) {
          $file = id(new PhabricatorFile())->loadOneWhere(
            'id = %d',
            $id);
          if (!$file) {
            throw new PhutilArgumentUsageException(
              "No file exists with id '{$name}'.");
          }
        } else {
          $file = id(new PhabricatorFile())->loadOneWhere(
            'phid = %d',
            $name);
          if (!$file) {
            throw new PhutilArgumentUsageException(
              "No file exists with PHID '{$name}'.");
          }
        }
        $iterator[] = $file;
      }
    } else {
      throw new PhutilArgumentUsageException(
        "Either specify a list of files to update, or use `--all` ".
        "to update all files.");
    }

    $is_dry_run = $args->getArg('dry-run');

    $failed = array();

    foreach ($iterator as $file) {
      $fid = 'F'.$file->getID();

      if (!$file->isViewableImage()) {
        $console->writeOut(
          "%s: Not an image file.\n",
          $fid);
        continue;
      }

      $metadata = $file->getMetadata();
      $image_width = idx($metadata, PhabricatorFile::METADATA_IMAGE_WIDTH);
      $image_height = idx($metadata, PhabricatorFile::METADATA_IMAGE_HEIGHT);
      if ($image_width && $image_height) {
        $console->writeOut(
          "%s: Already updated\n",
          $fid);
        continue;
      }

      if ($is_dry_run) {
        $console->writeOut(
          "%s: Would update file (dry run)\n",
          $fid);
        continue;
      }

      $console->writeOut(
        "%s: Updating metadata... ",
        $fid);

      try {
        $file->updateDimensions();
        $console->writeOut("done.\n");
      } catch (Exception $ex) {
        $console->writeOut("failed!\n");
        $console->writeErr("%s\n", (string)$ex);
        $failed[] = $file;
      }
    }

    if ($failed) {
      $console->writeOut("**Failures!**\n");
      $ids = array();
      foreach ($failed as $file) {
        $ids[] = 'F'.$file->getID();
      }
      $console->writeOut("%s\n", implode(', ', $ids));

      return 1;
    } else {
      $console->writeOut("**Success!**\n");
      return 0;
    }

    return 0;
  }
}
