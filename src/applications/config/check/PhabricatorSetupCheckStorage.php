<?php

final class PhabricatorSetupCheckStorage extends PhabricatorSetupCheck {

  protected function executeChecks() {
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
