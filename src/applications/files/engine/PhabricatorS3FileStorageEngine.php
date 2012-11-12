<?php

/**
 * Amazon S3 file storage engine. This engine scales well but is relatively
 * high-latency since data has to be pulled off S3.
 *
 * @task impl       Implementation
 * @task internal   Internals
 * @group filestorage
 */
final class PhabricatorS3FileStorageEngine
  extends PhabricatorFileStorageEngine {

/* -(  Implementation  )----------------------------------------------------- */


  /**
   * This engine identifies as "amazon-s3".
   *
   * @task impl
   */
  public function getEngineIdentifier() {
    return 'amazon-s3';
  }


  /**
   * Write file data into S3.
   * @task impl
   */
  public function writeFile($data, array $params) {
    $s3 = $this->newS3API();

    $name = 'phabricator/'.Filesystem::readRandomCharacters(20);

    AphrontWriteGuard::willWrite();
    $s3->putObject(
      $data,
      $this->getBucketName(),
      $name,
      $acl = 'private');

    return $name;
  }


  /**
   * Load a stored blob from S3.
   * @task impl
   */
  public function readFile($handle) {
    $result = $this->newS3API()->getObject(
      $this->getBucketName(),
      $handle);
    return $result->body;
  }


  /**
   * Delete a blob from S3.
   * @task impl
   */
  public function deleteFile($handle) {

    AphrontWriteGuard::willWrite();
    $this->newS3API()->deleteObject(
      $this->getBucketName(),
      $handle);
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

    if (!$access_key || !$secret_key) {
      throw new PhabricatorFileStorageConfigurationException(
        "Specify 'amazon-s3.access-key' and 'amazon-s3.secret-key'!");
    }

    $s3 = new S3($access_key, $secret_key, $use_ssl = true);

    $s3->setExceptions(true);

    return $s3;
  }

}
