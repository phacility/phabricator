<?php

final class PhabricatorRepositoryWorkingCopyVersion
  extends PhabricatorRepositoryDAO {

  protected $repositoryPHID;
  protected $devicePHID;
  protected $repositoryVersion;
  protected $isWriting;
  protected $lockOwner;
  protected $writeProperties;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'repositoryVersion' => 'uint32',
        'isWriting' => 'bool',
        'writeProperties' => 'text?',
        'lockOwner' => 'text255?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_workingcopy' => array(
          'columns' => array('repositoryPHID', 'devicePHID'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public static function loadVersions($repository_phid) {
    $version = new self();
    $conn_w = $version->establishConnection('w');
    $table = $version->getTableName();

    // This is a normal read, but force it to come from the master.
    $rows = queryfx_all(
      $conn_w,
      'SELECT * FROM %T WHERE repositoryPHID = %s',
      $table,
      $repository_phid);

    return $version->loadAllFromArray($rows);
  }

  public static function getReadLock($repository_phid, $device_phid) {
    $repository_hash = PhabricatorHash::digestForIndex($repository_phid);
    $device_hash = PhabricatorHash::digestForIndex($device_phid);
    $lock_key = "repo.read({$repository_hash}, {$device_hash})";

    return PhabricatorGlobalLock::newLock($lock_key);
  }

  public static function getWriteLock($repository_phid) {
    $repository_hash = PhabricatorHash::digestForIndex($repository_phid);
    $lock_key = "repo.write({$repository_hash})";

    return PhabricatorGlobalLock::newLock($lock_key);
  }


  /**
   * Before a write, set the "isWriting" flag.
   *
   * This allows us to detect when we lose a node partway through a write and
   * may have committed and acknowledged a write on a node that lost the lock
   * partway through the write and is no longer reachable.
   *
   * In particular, if a node loses its connection to the database the global
   * lock is released by default. This is a durable lock which stays locked
   * by default.
   */
  public static function willWrite(
    AphrontDatabaseConnection $locked_connection,
    $repository_phid,
    $device_phid,
    array $write_properties,
    $lock_owner) {

    $version = new self();
    $table = $version->getTableName();

    queryfx(
      $locked_connection,
      'INSERT INTO %T
        (repositoryPHID, devicePHID, repositoryVersion, isWriting,
          writeProperties, lockOwner)
        VALUES
        (%s, %s, %d, %d, %s, %s)
        ON DUPLICATE KEY UPDATE
          isWriting = VALUES(isWriting),
          writeProperties = VALUES(writeProperties),
          lockOwner = VALUES(lockOwner)',
      $table,
      $repository_phid,
      $device_phid,
      0,
      1,
      phutil_json_encode($write_properties),
      $lock_owner);
  }


  /**
   * After a write, update the version and release the "isWriting" lock.
   */
  public static function didWrite(
    $repository_phid,
    $device_phid,
    $old_version,
    $new_version,
    $lock_owner) {

    $version = new self();
    $conn_w = $version->establishConnection('w');
    $table = $version->getTableName();

    queryfx(
      $conn_w,
      'UPDATE %T SET
          repositoryVersion = %d,
          isWriting = 0,
          lockOwner = NULL
        WHERE
          repositoryPHID = %s AND
          devicePHID = %s AND
          repositoryVersion = %d AND
          isWriting = 1 AND
          lockOwner = %s',
      $table,
      $new_version,
      $repository_phid,
      $device_phid,
      $old_version,
      $lock_owner);
  }


  /**
   * After a fetch, set the local version to the fetched version.
   */
  public static function updateVersion(
    $repository_phid,
    $device_phid,
    $new_version) {

    $version = new self();
    $conn_w = $version->establishConnection('w');
    $table = $version->getTableName();

    queryfx(
      $conn_w,
      'INSERT INTO %T
        (repositoryPHID, devicePHID, repositoryVersion, isWriting)
        VALUES
        (%s, %s, %d, %d)
        ON DUPLICATE KEY UPDATE
          repositoryVersion = VALUES(repositoryVersion)',
      $table,
      $repository_phid,
      $device_phid,
      $new_version,
      0);
  }


  /**
   * Explicitly demote a device.
   */
  public static function demoteDevice(
    $repository_phid,
    $device_phid) {

    $version = new self();
    $conn_w = $version->establishConnection('w');
    $table = $version->getTableName();

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE repositoryPHID = %s AND devicePHID = %s',
      $table,
      $repository_phid,
      $device_phid);
  }

}
