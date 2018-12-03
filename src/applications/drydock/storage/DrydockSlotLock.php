<?php

/**
 * Simple optimistic locks for Drydock resources and leases.
 *
 * Most blueprints only need very simple locks: for example, a host blueprint
 * might not want to create multiple resources representing the same physical
 * machine. These optimistic "slot locks" provide a flexible way to do this
 * sort of simple locking.
 *
 * @task info Getting Lock Information
 * @task lock Acquiring and Releasing Locks
 */
final class DrydockSlotLock extends DrydockDAO {

  protected $ownerPHID;
  protected $lockIndex;
  protected $lockKey;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'lockIndex' => 'bytes12',
        'lockKey' => 'text',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_lock' => array(
          'columns' => array('lockIndex'),
          'unique' => true,
        ),
        'key_owner' => array(
          'columns' => array('ownerPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }


/* -(  Getting Lock Information  )------------------------------------------- */


  /**
   * Load all locks held by a particular owner.
   *
   * @param phid Owner PHID.
   * @return list<DrydockSlotLock> All held locks.
   * @task info
   */
  public static function loadLocks($owner_phid) {
    return id(new DrydockSlotLock())->loadAllWhere(
      'ownerPHID = %s',
      $owner_phid);
  }


  /**
   * Test if a lock is currently free.
   *
   * @param string Lock key to test.
   * @return bool True if the lock is currently free.
   * @task info
   */
  public static function isLockFree($lock) {
    return self::areLocksFree(array($lock));
  }


  /**
   * Test if a list of locks are all currently free.
   *
   * @param list<string> List of lock keys to test.
   * @return bool True if all locks are currently free.
   * @task info
   */
  public static function areLocksFree(array $locks) {
    $lock_map = self::loadHeldLocks($locks);
    return !$lock_map;
  }


  /**
   * Load named locks.
   *
   * @param list<string> List of lock keys to load.
   * @return list<DrydockSlotLock> List of held locks.
   * @task info
   */
  public static function loadHeldLocks(array $locks) {
    if (!$locks) {
      return array();
    }

    $table = new DrydockSlotLock();
    $conn_r = $table->establishConnection('r');

    $indexes = array();
    foreach ($locks as $lock) {
      $indexes[] = PhabricatorHash::digestForIndex($lock);
    }

    return id(new DrydockSlotLock())->loadAllWhere(
      'lockIndex IN (%Ls)',
      $indexes);
  }


/* -(  Acquiring and Releasing Locks  )-------------------------------------- */


  /**
   * Acquire a set of slot locks.
   *
   * This method either acquires all the locks or throws an exception (usually
   * because one or more locks are held).
   *
   * @param phid Lock owner PHID.
   * @param list<string> List of locks to acquire.
   * @return void
   * @task locks
   */
  public static function acquireLocks($owner_phid, array $locks) {
    if (!$locks) {
      return;
    }

    $table = new DrydockSlotLock();
    $conn_w = $table->establishConnection('w');

    $sql = array();
    foreach ($locks as $lock) {
      $sql[] = qsprintf(
        $conn_w,
        '(%s, %s, %s)',
        $owner_phid,
        PhabricatorHash::digestForIndex($lock),
        $lock);
    }

    try {
      queryfx(
        $conn_w,
        'INSERT INTO %T (ownerPHID, lockIndex, lockKey) VALUES %LQ',
        $table->getTableName(),
        $sql);
    } catch (AphrontDuplicateKeyQueryException $ex) {
      // Try to improve the readability of the exception. We might miss on
      // this query if the lock has already been released, but most of the
      // time we should be able to figure out which locks are already held.
      $held = self::loadHeldLocks($locks);
      $held = mpull($held, 'getOwnerPHID', 'getLockKey');

      throw new DrydockSlotLockException($held);
    }
  }


  /**
   * Release all locks held by an owner.
   *
   * @param phid Lock owner PHID.
   * @return void
   * @task locks
   */
  public static function releaseLocks($owner_phid) {
    $table = new DrydockSlotLock();
    $conn_w = $table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE ownerPHID = %s',
      $table->getTableName(),
      $owner_phid);
  }

}
