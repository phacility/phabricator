<?php

final class PhabricatorFilesManagementRebuildWorkflow
  extends PhabricatorFilesManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('rebuild')
      ->setSynopsis('Rebuild metadata of old files.')
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
            'name'      => 'rebuild-mime',
            'help'      => 'Rebuild MIME information.',
          ),
          array(
            'name'      => 'rebuild-dimensions',
            'help'      => 'Rebuild image dimension information.',
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
        'Either specify a list of files to update, or use `--all` '.
        'to update all files.');
    }

    $update = array(
      'mime'          => $args->getArg('rebuild-mime'),
      'dimensions'    => $args->getArg('rebuild-dimensions'),
    );

    // If the user didn't select anything, rebuild everything.
    if (!array_filter($update)) {
      foreach ($update as $key => $ignored) {
        $update[$key] = true;
      }
    }

    $is_dry_run = $args->getArg('dry-run');

    $failed = array();

    foreach ($iterator as $file) {
      $fid = 'F'.$file->getID();

      if ($update['mime']) {
        $tmp = new TempFile();
        Filesystem::writeFile($tmp, $file->loadFileData());
        $new_type = Filesystem::getMimeType($tmp);

        if ($new_type == $file->getMimeType()) {
          $console->writeOut(
            "%s: Mime type not changed (%s).\n",
            $fid,
            $new_type);
        } else {
          if ($is_dry_run) {
            $console->writeOut(
              "%s: Would update Mime type: '%s' -> '%s'.\n",
              $fid,
              $file->getMimeType(),
              $new_type);
          } else {
            $console->writeOut(
              "%s: Updating Mime type: '%s' -> '%s'.\n",
              $fid,
              $file->getMimeType(),
              $new_type);
            $file->setMimeType($new_type);
            $file->save();
          }
        }
      }

      if ($update['dimensions']) {
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
            "%s: Image dimensions already exist.\n",
            $fid);
          continue;
        }

        if ($is_dry_run) {
          $console->writeOut(
            "%s: Would update file dimensions (dry run)\n",
            $fid);
          continue;
        }

        $console->writeOut(
          '%s: Updating metadata... ',
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
