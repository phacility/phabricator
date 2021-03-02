<?php

final class PhabricatorGlobalLockTestCase
  extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testConnectionPoolWithDefaultConnection() {
    PhabricatorGlobalLock::clearConnectionPool();

    $this->assertEqual(
      0,
      PhabricatorGlobalLock::getConnectionPoolSize(),
      pht('Clear Connection Pool'));

    $lock_name = $this->newLockName();
    $lock = PhabricatorGlobalLock::newLock($lock_name);
    $lock->lock();

    $this->assertEqual(
      0,
      PhabricatorGlobalLock::getConnectionPoolSize(),
      pht('Connection Pool With Lock'));

    $lock->unlock();

    $this->assertEqual(
      1,
      PhabricatorGlobalLock::getConnectionPoolSize(),
      pht('Connection Pool With Lock Released'));

    PhabricatorGlobalLock::clearConnectionPool();
  }

  public function testConnectionPoolWithSpecificConnection() {
    $conn = PhabricatorGlobalLock::newConnection();

    PhabricatorGlobalLock::clearConnectionPool();

    $this->assertEqual(
      0,
      PhabricatorGlobalLock::getConnectionPoolSize(),
      pht('Clear Connection Pool'));

    $this->assertEqual(
      false,
      $conn->isHoldingAnyLock(),
      pht('Specific Connection, No Lock'));

    $lock_name = $this->newLockName();
    $lock = PhabricatorGlobalLock::newLock($lock_name);
    $lock->setExternalConnection($conn);
    $lock->lock();

    $this->assertEqual(
      0,
      PhabricatorGlobalLock::getConnectionPoolSize(),
      pht('Connection Pool + Specific, With Lock'));

    $this->assertEqual(
      true,
      $conn->isHoldingAnyLock(),
      pht('Specific Connection, Holding Lock'));

    $lock->unlock();

    // The specific connection provided should NOT be returned to the
    // connection pool.

    $this->assertEqual(
      0,
      PhabricatorGlobalLock::getConnectionPoolSize(),
      pht('Connection Pool + Specific, With Lock Released'));

    $this->assertEqual(
      false,
      $conn->isHoldingAnyLock(),
      pht('Specific Connection, No Lock'));

    PhabricatorGlobalLock::clearConnectionPool();
  }

  public function testExternalConnectionMutationScope() {
    $conn = PhabricatorGlobalLock::newConnection();

    $lock_name = $this->newLockName();
    $lock = PhabricatorGlobalLock::newLock($lock_name);
    $lock->lock();

    $caught = null;
    try {
      $lock->setExternalConnection($conn);
    } catch (Exception $ex) {
      $caught = $ex;
    } catch (Throwable $ex) {
      $caught = $ex;
    }

    $lock->unlock();

    $this->assertTrue(
      ($caught instanceof Exception),
      pht('Changing connection while locked is forbidden.'));
  }

  public function testMultipleLocks() {
    $conn = PhabricatorGlobalLock::newConnection();

    PhabricatorGlobalLock::clearConnectionPool();

    $lock_name_a = $this->newLockName();
    $lock_name_b = $this->newLockName();

    $lock_a = PhabricatorGlobalLock::newLock($lock_name_a);
    $lock_a->setExternalConnection($conn);

    $lock_b = PhabricatorGlobalLock::newLock($lock_name_b);
    $lock_b->setExternalConnection($conn);

    $lock_a->lock();

    $caught = null;
    try {
      $lock_b->lock();
    } catch (Exception $ex) {
      $caught = $ex;
    } catch (Throwable $ex) {
      $caught = $ex;
    }

    // See T13627. The lock infrastructure must forbid this because it does
    // not work in versions of MySQL older than 5.7.

    $this->assertTrue(
      ($caught instanceof Exception),
      pht('Expect multiple locks on the same connection to fail.'));
  }

  public function testPoolReleaseOnFailure() {
    $conn = PhabricatorGlobalLock::newConnection();
    $lock_name = $this->newLockName();

    PhabricatorGlobalLock::clearConnectionPool();

    $this->assertEqual(
      0,
      PhabricatorGlobalLock::getConnectionPoolSize(),
      pht('Clear Connection Pool'));

    $lock = PhabricatorGlobalLock::newLock($lock_name);

    // NOTE: We're cheating here, since there's a global registry of locks
    // for the process that we have to bypass. In the real world, this lock
    // would have to be held by some external process. To simplify this
    // test case, just use a raw "GET_LOCK()" call to hold the lock.

    $raw_conn = PhabricatorGlobalLock::newConnection();
    $raw_name = $lock->getName();

    $row = queryfx_one(
      $raw_conn,
      'SELECT GET_LOCK(%s, %f)',
      $raw_name,
      0);
    $this->assertTrue((bool)head($row), pht('Establish Raw Lock'));

    $this->assertEqual(
      0,
      PhabricatorGlobalLock::getConnectionPoolSize(),
      pht('Connection Pool with Held Lock'));

    // We expect this sequence to establish a new connection, fail to acquire
    // the lock, then put the connection in the connection pool. After the
    // first cycle, the connection should be reused.

    for ($ii = 0; $ii < 3; $ii++) {
      $this->tryHeldLock($lock_name);
      $this->assertEqual(
        1,
        PhabricatorGlobalLock::getConnectionPoolSize(),
        pht('Connection Pool After Lock Failure'));
    }

    PhabricatorGlobalLock::clearConnectionPool();

    // Now, do the same thing with an external connection. This connection
    // should not be put into the pool! See T13627.

    for ($ii = 0; $ii < 3; $ii++) {
      $this->tryHeldLock($lock_name, $conn);
      $this->assertEqual(
        0,
        PhabricatorGlobalLock::getConnectionPoolSize(),
        pht('Connection Pool After External Lock Failure'));
    }
  }

  private function newLockName() {
    return 'testlock-'.Filesystem::readRandomCharacters(16);
  }

  private function tryHeldLock(
    $lock_name,
    AphrontDatabaseConnection $conn = null) {

    $lock = PhabricatorGlobalLock::newLock($lock_name);

    if ($conn) {
      $lock->setExternalConnection($conn);
    }

    $caught = null;
    try {
      $lock->lock(0);
    } catch (PhutilLockException $ex) {
      $caught = $ex;
    }

    $this->assertTrue($caught instanceof PhutilLockException);
  }


}
