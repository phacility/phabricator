<?php

final class PhabricatorStorageSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_OTHER;
  }

  /**
   * @phutil-external-symbol class PhabricatorStartup
   */
  protected function executeChecks() {
    $engines = PhabricatorFileStorageEngine::loadWritableChunkEngines();
    $chunk_engine_active = (bool)$engines;

    $this->checkS3();

    if (!$chunk_engine_active) {
      $doc_href = PhabricatorEnv::getDoclink('Configuring File Storage');

      $message = pht(
        'Large file storage has not been configured, which will limit '.
        'the maximum size of file uploads. See %s for '.
        'instructions on configuring uploads and storage.',
        phutil_tag(
          'a',
          array(
            'href' => $doc_href,
            'target' => '_blank',
          ),
          pht('Configuring File Storage')));

      $this
        ->newIssue('large-files')
        ->setShortName(pht('Large Files'))
        ->setName(pht('Large File Storage Not Configured'))
        ->setMessage($message);
    }

    $post_max_size = ini_get('post_max_size');
    if ($post_max_size && ((int)$post_max_size > 0)) {
      $post_max_bytes = phutil_parse_bytes($post_max_size);
      $post_max_need = (32 * 1024 * 1024);
      if ($post_max_need > $post_max_bytes) {
        $summary = pht(
          'Set %s in your PHP configuration to at least 32MB '.
          'to support large file uploads.',
          phutil_tag('tt', array(), 'post_max_size'));

        $message = pht(
          'Adjust %s in your PHP configuration to at least 32MB. When '.
          'set to smaller value, large file uploads may not work properly.',
          phutil_tag('tt', array(), 'post_max_size'));

        $this
          ->newIssue('php.post_max_size')
          ->setName(pht('PHP post_max_size Not Configured'))
          ->setSummary($summary)
          ->setMessage($message)
          ->setGroup(self::GROUP_PHP)
          ->addPHPConfig('post_max_size');
      }
    }

    // This is somewhat arbitrary, but make sure we have enough headroom to
    // upload a default file at the chunk threshold (8MB), which may be
    // base64 encoded, then JSON encoded in the request, and may need to be
    // held in memory in the raw and as a query string.
    $need_bytes = (64 * 1024 * 1024);

    $memory_limit = PhabricatorStartup::getOldMemoryLimit();
    if ($memory_limit && ((int)$memory_limit > 0)) {
      $memory_limit_bytes = phutil_parse_bytes($memory_limit);
      $memory_usage_bytes = memory_get_usage();

      $available_bytes = ($memory_limit_bytes - $memory_usage_bytes);

      if ($need_bytes > $available_bytes) {
        $summary = pht(
          'Your PHP memory limit is configured in a way that may prevent '.
          'you from uploading large files or handling large requests.');

        $message = pht(
          'When you upload a file via drag-and-drop or the API, chunks must '.
          'be buffered into memory before being written to permanent '.
          'storage. Phabricator needs memory available to store these '.
          'chunks while they are uploaded, but PHP is currently configured '.
          'to severely limit the available memory.'.
          "\n\n".
          'PHP processes currently have very little free memory available '.
          '(%s). To work well, processes should have at least %s.'.
          "\n\n".
          '(Note that the application itself must also fit in available '.
          'memory, so not all of the memory under the memory limit is '.
          'available for running workloads.)'.
          "\n\n".
          "The easiest way to resolve this issue is to set %s to %s in your ".
          "PHP configuration, to disable the memory limit. There is ".
          "usually little or no value to using this option to limit ".
          "Phabricator process memory.".
          "\n\n".
          "You can also increase the limit or ignore this issue and accept ".
          "that you may encounter problems uploading large files and ".
          "processing large requests.",
          phutil_format_bytes($available_bytes),
          phutil_format_bytes($need_bytes),
          phutil_tag('tt', array(), 'memory_limit'),
          phutil_tag('tt', array(), '-1'));

        $this
          ->newIssue('php.memory_limit.upload')
          ->setName(pht('Memory Limit Restricts File Uploads'))
          ->setSummary($summary)
          ->setMessage($message)
          ->setGroup(self::GROUP_PHP)
          ->addPHPConfig('memory_limit')
          ->addPHPConfigOriginalValue('memory_limit', $memory_limit);
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

  private function checkS3() {
    $access_key = PhabricatorEnv::getEnvConfig('amazon-s3.access-key');
    $secret_key = PhabricatorEnv::getEnvConfig('amazon-s3.secret-key');
    $region = PhabricatorEnv::getEnvConfig('amazon-s3.region');
    $endpoint = PhabricatorEnv::getEnvConfig('amazon-s3.endpoint');

    $how_many = 0;

    if (strlen($access_key)) {
      $how_many++;
    }

    if (strlen($secret_key)) {
      $how_many++;
    }

    if (strlen($region)) {
      $how_many++;
    }

    if (strlen($endpoint)) {
      $how_many++;
    }

    // Nothing configured, no issues here.
    if ($how_many === 0) {
      return;
    }

    // Everything configured, no issues here.
    if ($how_many === 4) {
      return;
    }

    $message = pht(
      'File storage in Amazon S3 has been partially configured, but you are '.
      'missing some required settings. S3 will not be available to store '.
      'files until you complete the configuration. Either configure S3 fully '.
      'or remove the partial configuration.');

    $this->newIssue('storage.s3.partial-config')
      ->setShortName(pht('S3 Partially Configured'))
      ->setName(pht('Amazon S3 is Only Partially Configured'))
      ->setMessage($message)
      ->addPhabricatorConfig('amazon-s3.access-key')
      ->addPhabricatorConfig('amazon-s3.secret-key')
      ->addPhabricatorConfig('amazon-s3.region')
      ->addPhabricatorConfig('amazon-s3.endpoint');
  }

}
