<?php

final class LiskFixtureTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
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

      $this->assertTrue(
        ($loaded !== null),
        pht('Reads inside transactions should have transaction visibility.'));

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
    $this->assertEqual(null, $load->load($id.'cow'));

    $this->assertTrue((bool)$load->load((int)$id));
    $this->assertTrue((bool)$load->load((string)$id));
  }

  public function testCounters() {
    $obj = new HarbormasterObject();
    $conn_w = $obj->establishConnection('w');

    // Test that the counter basically behaves as expected.
    $this->assertEqual(1, LiskDAO::loadNextCounterValue($conn_w, 'a'));
    $this->assertEqual(2, LiskDAO::loadNextCounterValue($conn_w, 'a'));
    $this->assertEqual(3, LiskDAO::loadNextCounterValue($conn_w, 'a'));

    // This first insert is primarily a test that the previous LAST_INSERT_ID()
    // value does not bleed into the creation of a new counter.
    $this->assertEqual(1, LiskDAO::loadNextCounterValue($conn_w, 'b'));
    $this->assertEqual(2, LiskDAO::loadNextCounterValue($conn_w, 'b'));

    // Test alternate access/overwrite methods.
    $this->assertEqual(3, LiskDAO::loadCurrentCounterValue($conn_w, 'a'));

    LiskDAO::overwriteCounterValue($conn_w, 'a', 42);
    $this->assertEqual(42, LiskDAO::loadCurrentCounterValue($conn_w, 'a'));
    $this->assertEqual(43, LiskDAO::loadNextCounterValue($conn_w, 'a'));

    // These inserts alternate database connections. Since unit tests are
    // transactional by default, we need to break out of them or we'll deadlock
    // since the transactions don't normally close until we exit the test.
    LiskDAO::endIsolateAllLiskEffectsToTransactions();
    try {

      $conn_1 = $obj->establishConnection('w', $force_new = true);
      $conn_2 = $obj->establishConnection('w', $force_new = true);

      $this->assertEqual(1, LiskDAO::loadNextCounterValue($conn_1, 'z'));
      $this->assertEqual(2, LiskDAO::loadNextCounterValue($conn_2, 'z'));
      $this->assertEqual(3, LiskDAO::loadNextCounterValue($conn_1, 'z'));
      $this->assertEqual(4, LiskDAO::loadNextCounterValue($conn_2, 'z'));
      $this->assertEqual(5, LiskDAO::loadNextCounterValue($conn_1, 'z'));

      LiskDAO::beginIsolateAllLiskEffectsToTransactions();
    } catch (Exception $ex) {
      LiskDAO::beginIsolateAllLiskEffectsToTransactions();
      throw $ex;
    }
  }

  public function testNonmutableColumns() {
    $object = id(new HarbormasterScratchTable())
      ->setData('val1')
      ->setNonmutableData('val1')
      ->save();

    $object->reload();

    $this->assertEqual('val1', $object->getData());
    $this->assertEqual('val1', $object->getNonmutableData());

    $object
      ->setData('val2')
      ->setNonmutableData('val2')
      ->save();

    $object->reload();

    $this->assertEqual('val2', $object->getData());

    // NOTE: This is the important test: the nonmutable column should not have
    // been affected by the update.
    $this->assertEqual('val1', $object->getNonmutableData());
  }


}
