<?php

final class PhabricatorStorageSetupCheck extends PhabricatorSetupCheck {

  /**
   * @phutil-external-symbol class PhabricatorStartup
   */
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
    } else {
      $memory_limit = PhabricatorStartup::getOldMemoryLimit();
      if ($memory_limit && ((int)$memory_limit > 0)) {
        $memory_limit_bytes = phutil_parse_bytes($memory_limit);
        $memory_usage_bytes = memory_get_usage();
        $upload_limit_bytes = phutil_parse_bytes($upload_limit);

        $available_bytes = ($memory_limit_bytes - $memory_usage_bytes);

        if ($upload_limit_bytes > $available_bytes) {
          $summary = pht(
            'Your PHP memory limit is configured in a way that may prevent '.
            'you from uploading large files.');

          $message = pht(
            'When you upload a file via drag-and-drop or the API, the entire '.
            'file is buffered into memory before being written to permanent '.
            'storage. Phabricator needs memory available to store these '.
            'files while they are uploaded, but PHP is currently configured '.
            'to limit the available memory.'.
            "\n\n".
            'Your Phabricator %s is currently set to a larger value (%s) than '.
            'the amount of available memory (%s) that a PHP process has '.
            'available to use, so uploads via drag-and-drop and the API will '.
            'hit the memory limit before they hit other limits.'.
            "\n\n".
            '(Note that the application itself must also fit in available '.
            'memory, so not all of the memory under the memory limit is '.
            'available for buffering file uploads.)'.
            "\n\n".
            "The easiest way to resolve this issue is to set %s to %s in your ".
            "PHP configuration, to disable the memory limit. There is ".
            "usually little or no value to using this option to limit ".
            "Phabricator process memory.".
            "\n\n".
            "You can also increase the limit, or decrease %s, or ignore this ".
            "issue and accept that these upload mechanisms will be limited ".
            "in the size of files they can handle.",
            phutil_tag('tt', array(), 'storage.upload-size-limit'),
            phutil_format_bytes($upload_limit_bytes),
            phutil_format_bytes($available_bytes),
            phutil_tag('tt', array(), 'memory_limit'),
            phutil_tag('tt', array(), '-1'),
            phutil_tag('tt', array(), 'storage.upload-size-limit'));

          $this
            ->newIssue('php.memory_limit.upload')
            ->setName(pht('Memory Limit Restricts File Uploads'))
            ->setSummary($summary)
            ->setMessage($message)
            ->addPHPConfig('memory_limit')
            ->addPHPConfigOriginalValue('memory_limit', $memory_limit)
            ->addPhabricatorConfig('storage.upload-size-limit');
        }
      }
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
