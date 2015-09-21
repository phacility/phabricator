<?php

/**
 * Simple optimistic locks for Drydock resources and leases.
 *
 * Most blueprints only need very simple locks: for example, a host blueprint
 * might not want to create multiple resources representing the same physical
 * machine. These optimistic "slot locks" provide a flexible way to do this
 * sort of simple locking.
 *
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

  public static function loadLocks($owner_phid) {
    return id(new DrydockSlotLock())->loadAllWhere(
      'ownerPHID = %s',
      $owner_phid);
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

    // TODO: These exceptions are pretty tricky to read. It would be good to
    // figure out which locks could not be acquired and try to improve the
    // exception to make debugging easier.

    queryfx(
      $conn_w,
      'INSERT INTO %T (ownerPHID, lockIndex, lockKey) VALUES %Q',
      $table->getTableName(),
      implode(', ', $sql));
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
