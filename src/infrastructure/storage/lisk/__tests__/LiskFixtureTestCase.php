<?php

final class LiskFixtureTestCase extends PhabricatorTestCase {

  public function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testTransactionalIsolation1of2() {
    // NOTE: These tests are verifying that data is destroyed between tests.
    // If the user from either test persists, the other test will fail.
    $this->assertEqual(
      0,
      count(id(new HarbormasterScratchTable())->loadAll()));

    id(new HarbormasterScratchTable())
      ->setData('alincoln')
      ->save();
  }

  public function testTransactionalIsolation2of2() {
    $this->assertEqual(
      0,
      count(id(new HarbormasterScratchTable())->loadAll()));

    id(new HarbormasterScratchTable())
      ->setData('ugrant')
      ->save();
  }

  public function testFixturesBasicallyWork() {
    $this->assertEqual(
      0,
      count(id(new HarbormasterScratchTable())->loadAll()));

    id(new HarbormasterScratchTable())
      ->setData('gwashington')
      ->save();

    $this->assertEqual(
      1,
      count(id(new HarbormasterScratchTable())->loadAll()));
  }

  public function testReadableTransactions() {
    // TODO: When we have semi-durable fixtures, use those instead. This is
    // extremely hacky.

    LiskDAO::endIsolateAllLiskEffectsToTransactions();
    try {

      $data = Filesystem::readRandomCharacters(32);

      $obj = new HarbormasterScratchTable();
      $obj->openTransaction();

        $obj->setData($data);
        $obj->save();

        $loaded = id(new HarbormasterScratchTable())->loadOneWhere(
          'data = %s',
          $data);

      $obj->killTransaction();

      $this->assertEqual(
        true,
        ($loaded !== null),
        "Reads inside transactions should have transaction visibility.");

      LiskDAO::beginIsolateAllLiskEffectsToTransactions();
    } catch (Exception $ex) {
      LiskDAO::beginIsolateAllLiskEffectsToTransactions();
      throw $ex;
    }
  }

  public function testGarbageLoadCalls() {
    $obj = new HarbormasterObject();
    $obj->save();
    $id = $obj->getID();

    $load = new HarbormasterObject();

    $this->assertEqual(null, $load->load(0));
    $this->assertEqual(null, $load->load(-1));
    $this->assertEqual(null, $load->load(9999));
    $this->assertEqual(null, $load->load(''));
    $this->assertEqual(null, $load->load('cow'));
    $this->assertEqual(null, $load->load($id."cow"));

    $this->assertEqual(true, (bool)$load->load((int)$id));
    $this->assertEqual(true, (bool)$load->load((string)$id));
  }

  public function testCounters() {
    $obj = new HarbormasterObject();
    $conn_w = $obj->establishConnection('w');

    // Test that the counter bascially behaves as expected.
    $this->assertEqual(1, LiskDAO::loadNextCounterID($conn_w, 'a'));
    $this->assertEqual(2, LiskDAO::loadNextCounterID($conn_w, 'a'));
    $this->assertEqual(3, LiskDAO::loadNextCounterID($conn_w, 'a'));

    // This first insert is primarily a test that the previous LAST_INSERT_ID()
    // value does not bleed into the creation of a new counter.
    $this->assertEqual(1, LiskDAO::loadNextCounterID($conn_w, 'b'));
    $this->assertEqual(2, LiskDAO::loadNextCounterID($conn_w, 'b'));

    // These inserts alternate database connections. Since unit tests are
    // transactional by default, we need to break out of them or we'll deadlock
    // since the transactions don't normally close until we exit the test.
    LiskDAO::endIsolateAllLiskEffectsToTransactions();
    try {

      $conn_1 = $obj->establishConnection('w', $force_new = true);
      $conn_2 = $obj->establishConnection('w', $force_new = true);

      $this->assertEqual(1, LiskDAO::loadNextCounterID($conn_1, 'z'));
      $this->assertEqual(2, LiskDAO::loadNextCounterID($conn_2, 'z'));
      $this->assertEqual(3, LiskDAO::loadNextCounterID($conn_1, 'z'));
      $this->assertEqual(4, LiskDAO::loadNextCounterID($conn_2, 'z'));
      $this->assertEqual(5, LiskDAO::loadNextCounterID($conn_1, 'z'));

      LiskDAO::beginIsolateAllLiskEffectsToTransactions();
    } catch (Exception $ex) {
      LiskDAO::beginIsolateAllLiskEffectsToTransactions();
      throw $ex;
    }
  }

}
