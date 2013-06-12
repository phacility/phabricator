<?php

final class PhabricatorSetupCheckStorage extends PhabricatorSetupCheck {

  protected function executeChecks() {
    $upload_limit = PhabricatorEnv::getEnvConfig('storage.upload-size-limit');
    if (!$upload_limit) {
      $message = pht(
        'The Phabricator file upload limit is not configured. You may only '.
        'be able to upload very small files until you configure it, because '.
        'some PHP default limits are very low (as low as 2MB).');

      $this
        ->newIssue('config.storage.upload-size-limit')
        ->setShortName(pht('Upload Limit'))
        ->setName(pht('Upload Limit Not Yet Configured'))
        ->setMessage($message)
        ->addPhabricatorConfig('storage.upload-size-limit');
    }

    $local_path = PhabricatorEnv::getEnvConfig('storage.local-disk.path');
    if (!$local_path) {
      return;
    }

    if (!Filesystem::pathExists($local_path) ||
        !is_readable($local_path) ||
        !is_writable($local_path)) {

      $message = pht(
        'Configured location for storing uploaded files on disk ("%s") does '.
        'not exist, or is not readable or writable. Verify the directory '.
        'exists and is readable and writable by the webserver.',
        $local_path);

      $this
        ->newIssue('config.storage.local-disk.path')
        ->setShortName(pht('Local Disk Storage'))
        ->setName(pht('Local Disk Storage Not Readable/Writable'))
        ->setMessage($message)
        ->addPhabricatorConfig('storage.local-disk.path');
    }
  }
}
