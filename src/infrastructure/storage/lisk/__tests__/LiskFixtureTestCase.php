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


}
