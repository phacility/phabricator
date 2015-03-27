<?php

/**
 * Amazon S3 file storage engine. This engine scales well but is relatively
 * high-latency since data has to be pulled off S3.
 *
 * @task internal Internals
 */
final class PhabricatorS3FileStorageEngine
  extends PhabricatorFileStorageEngine {


/* -(  Engine Metadata  )---------------------------------------------------- */


  /**
   * This engine identifies as `amazon-s3`.
   */
  public function getEngineIdentifier() {
    return 'amazon-s3';
  }

  public function getEnginePriority() {
    return 100;
  }

  public function canWriteFiles() {
    $bucket = PhabricatorEnv::getEnvConfig('storage.s3.bucket');
    $access_key = PhabricatorEnv::getEnvConfig('amazon-s3.access-key');
    $secret_key = PhabricatorEnv::getEnvConfig('amazon-s3.secret-key');

    return (strlen($bucket) && strlen($access_key) && strlen($secret_key));
  }


/* -(  Managing File Data  )------------------------------------------------- */


  /**
   * Writes file data into Amazon S3.
   */
  public function writeFile($data, array $params) {
    $s3 = $this->newS3API();

    // Generate a random name for this file. We add some directories to it
    // (e.g. 'abcdef123456' becomes 'ab/cd/ef123456') to make large numbers of
    // files more browsable with web/debugging tools like the S3 administration
    // tool.
    $seed = Filesystem::readRandomCharacters(20);
    $parts = array();
    $parts[] = 'phabricator';

    $instance_name = PhabricatorEnv::getEnvConfig('cluster.instance');
    if (strlen($instance_name)) {
      $parts[] = $instance_name;
    }

    $parts[] = substr($seed, 0, 2);
    $parts[] = substr($seed, 2, 2);
    $parts[] = substr($seed, 4);

    $name = implode('/', $parts);

    AphrontWriteGuard::willWrite();
    $profiler = PhutilServiceProfiler::getInstance();
    $call_id = $profiler->beginServiceCall(
      array(
        'type' => 's3',
        'method' => 'putObject',
      ));
    $s3->putObject(
      $data,
      $this->getBucketName(),
      $name,
      $acl = 'private');
    $profiler->endServiceCall($call_id, array());

    return $name;
  }


  /**
   * Load a stored blob from Amazon S3.
   */
  public function readFile($handle) {
    $s3 = $this->newS3API();
    $profiler = PhutilServiceProfiler::getInstance();
    $call_id = $profiler->beginServiceCall(
      array(
        'type' => 's3',
        'method' => 'getObject',
      ));
    $result = $s3->getObject(
      $this->getBucketName(),
      $handle);
    $profiler->endServiceCall($call_id, array());

    // NOTE: The implementation of the API that we're using may respond with
    // a successful result that has length 0 and no body property.
    if (isset($result->body)) {
      return $result->body;
    } else {
      return '';
    }
  }


  /**
   * Delete a blob from Amazon S3.
   */
  public function deleteFile($handle) {
    AphrontWriteGuard::willWrite();
    $s3 = $this->newS3API();
    $profiler = PhutilServiceProfiler::getInstance();
    $call_id = $profiler->beginServiceCall(
      array(
        'type' => 's3',
        'method' => 'deleteObject',
      ));
    $s3->deleteObject(
      $this->getBucketName(),
      $handle);
    $profiler->endServiceCall($call_id, array());
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   * Retrieve the S3 bucket name.
   *
   * @task internal
   */
  private function getBucketName() {
    $bucket = PhabricatorEnv::getEnvConfig('storage.s3.bucket');
    if (!$bucket) {
      throw new PhabricatorFileStorageConfigurationException(
        "No 'storage.s3.bucket' specified!");
    }
    return $bucket;
  }

  /**
   * Create a new S3 API object.
   *
   * @task internal
   * @phutil-external-symbol class S3
   */
  private function newS3API() {
    $libroot = dirname(phutil_get_library_root('phabricator'));
    require_once $libroot.'/externals/s3/S3.php';

    $access_key = PhabricatorEnv::getEnvConfig('amazon-s3.access-key');
    $secret_key = PhabricatorEnv::getEnvConfig('amazon-s3.secret-key');
    $endpoint = PhabricatorEnv::getEnvConfig('amazon-s3.endpoint');

    if (!$access_key || !$secret_key) {
      throw new PhabricatorFileStorageConfigurationException(
        "Specify 'amazon-s3.access-key' and 'amazon-s3.secret-key'!");
    }

    if ($endpoint !== null) {
      $s3 = new S3($access_key, $secret_key, $use_ssl = true, $endpoint);
    } else {
      $s3 = new S3($access_key, $secret_key, $use_ssl = true);
    }

    $s3->setExceptions(true);

    return $s3;
  }

}
