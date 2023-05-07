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
    $endpoint = PhabricatorEnv::getEnvConfig('amazon-s3.endpoint');
    $region = PhabricatorEnv::getEnvConfig('amazon-s3.region');

    return ($bucket !== null && strlen($bucket) &&
      $access_key !== null && strlen($access_key) &&
      $secret_key !== null && strlen($secret_key) &&
      $endpoint !== null && strlen($endpoint) &&
      $region !== null && strlen($region));
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
    if ($instance_name !== null && strlen($instance_name)) {
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

    $s3
      ->setParametersForPutObject($name, $data)
      ->resolve();

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

    $result = $s3
      ->setParametersForGetObject($handle)
      ->resolve();

    $profiler->endServiceCall($call_id, array());

    return $result;
  }


  /**
   * Delete a blob from Amazon S3.
   */
  public function deleteFile($handle) {
    $s3 = $this->newS3API();

    AphrontWriteGuard::willWrite();
    $profiler = PhutilServiceProfiler::getInstance();
    $call_id = $profiler->beginServiceCall(
      array(
        'type' => 's3',
        'method' => 'deleteObject',
      ));

    $s3
      ->setParametersForDeleteObject($handle)
      ->resolve();

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
        pht(
          "No '%s' specified!",
          'storage.s3.bucket'));
    }
    return $bucket;
  }

  /**
   * Create a new S3 API object.
   *
   * @task internal
   */
  private function newS3API() {
    $access_key = PhabricatorEnv::getEnvConfig('amazon-s3.access-key');
    $secret_key = PhabricatorEnv::getEnvConfig('amazon-s3.secret-key');
    $region = PhabricatorEnv::getEnvConfig('amazon-s3.region');
    $endpoint = PhabricatorEnv::getEnvConfig('amazon-s3.endpoint');

    return id(new PhutilAWSS3Future())
      ->setAccessKey($access_key)
      ->setSecretKey(new PhutilOpaqueEnvelope($secret_key))
      ->setRegion($region)
      ->setEndpoint($endpoint)
      ->setBucket($this->getBucketName());
  }

}
