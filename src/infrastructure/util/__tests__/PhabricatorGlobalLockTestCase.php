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
    $conn = id(new HarbormasterScratchTable())
      ->establishConnection('w');

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
    $conn = id(new HarbormasterScratchTable())
      ->establishConnection('w');

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
    $conn = id(new HarbormasterScratchTable())
      ->establishConnection('w');

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

  private function newLockName() {
    return 'testlock-'.Filesystem::readRandomCharacters(16);
  }

}
