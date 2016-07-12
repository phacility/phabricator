<?php

final class PhabricatorFilesManagementRebuildWorkflow
  extends PhabricatorFilesManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('rebuild')
      ->setSynopsis(pht('Rebuild metadata of old files.'))
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
            'name'      => 'rebuild-mime',
            'help'      => pht('Rebuild MIME information.'),
          ),
          array(
            'name'      => 'rebuild-dimensions',
            'help'      => pht('Rebuild image dimension information.'),
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
          'Either specify a list of files to update, or use `%s` '.
          'to update all files.',
          '--all'));
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
            "%s\n",
            pht(
              '%s: Mime type not changed (%s).',
              $fid,
              $new_type));
        } else {
          if ($is_dry_run) {
            $console->writeOut(
              "%s\n",
              pht(
                "%s: Would update Mime type: '%s' -> '%s'.",
                $fid,
                $file->getMimeType(),
                $new_type));
          } else {
            $console->writeOut(
              "%s\n",
              pht(
                "%s: Updating Mime type: '%s' -> '%s'.",
                $fid,
                $file->getMimeType(),
                $new_type));
            $file->setMimeType($new_type);
            $file->save();
          }
        }
      }

      if ($update['dimensions']) {
        if (!$file->isViewableImage()) {
          $console->writeOut(
            "%s\n",
            pht('%s: Not an image file.', $fid));
          continue;
        }

        $metadata = $file->getMetadata();
        $image_width = idx($metadata, PhabricatorFile::METADATA_IMAGE_WIDTH);
        $image_height = idx($metadata, PhabricatorFile::METADATA_IMAGE_HEIGHT);
        if ($image_width && $image_height) {
          $console->writeOut(
            "%s\n",
            pht('%s: Image dimensions already exist.', $fid));
          continue;
        }

        if ($is_dry_run) {
          $console->writeOut(
            "%s\n",
            pht('%s: Would update file dimensions (dry run)', $fid));
          continue;
        }

        $console->writeOut(
          pht('%s: Updating metadata... ', $fid));

        try {
          $file->updateDimensions();
          $console->writeOut("%s\n", pht('Done.'));
        } catch (Exception $ex) {
          $console->writeOut("%s\n", pht('Failed!'));
          $console->writeErr("%s\n", (string)$ex);
          $failed[] = $file;
        }
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

    return 0;
  }
}
