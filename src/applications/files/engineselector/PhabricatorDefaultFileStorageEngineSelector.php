<?php

/**
 * Default storage engine selector. See
 * @{class:PhabricatorFileStorageEngineSelector} and @{article:File Storage
 * Technical Documentation} for more information.
 *
 * @group filestorage
 */
final class PhabricatorDefaultFileStorageEngineSelector
  extends PhabricatorFileStorageEngineSelector {

  /**
   * Select viable default storage engines according to configuration. We'll
   * select the MySQL and Local Disk storage engines if they are configured
   * to allow a given file.
   */
  public function selectStorageEngines($data, array $params) {
    $length = strlen($data);

    $mysql_key = 'storage.mysql-engine.max-size';
    $mysql_limit = PhabricatorEnv::getEnvConfig($mysql_key);

    $engines = array();
    if ($mysql_limit && $length <= $mysql_limit) {
      $engines[] = new PhabricatorMySQLFileStorageEngine();
    }

    $local_key = 'storage.local-disk.path';
    $local_path = PhabricatorEnv::getEnvConfig($local_key);
    if ($local_path) {
      $engines[] = new PhabricatorLocalDiskFileStorageEngine();
    }

    $s3_key = 'storage.s3.bucket';
    if (PhabricatorEnv::getEnvConfig($s3_key)) {
      $engines[] = new PhabricatorS3FileStorageEngine();
    }

    if ($mysql_limit && empty($engines)) {
      // If we return no engines, an exception will be thrown but it will be
      // a little vague ("No valid storage engines"). Since this is a default
      // case, throw a more specific exception.
      throw new Exception(
        "This file exceeds the configured MySQL storage engine filesize ".
        "limit, but no other storage engines are configured. Increase the ".
        "MySQL storage engine limit or configure a storage engine suitable ".
        "for larger files.");
    }

    return $engines;
  }

}
