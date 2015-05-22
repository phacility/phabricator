<?php

final class AphrontIsolatedDatabaseConnectionTestCase
  extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      // We disable this here because this test is unique (it is testing that
      // isolation actually occurs) and must establish a live connection to the
      // database to verify that.
      self::PHABRICATOR_TESTCONFIG_ISOLATE_LISK => false,
    );
  }

  public function testIsolation() {
    // This will fail if the connection isn't isolated.
    queryfx(
      $this->newIsolatedConnection(),
      'INSERT INVALID SYNTAX');

    $this->assertTrue(true);
  }

  public function testInsertGeneratesID() {
    $conn = $this->newIsolatedConnection();

    queryfx($conn, 'INSERT');
    $id1 = $conn->getInsertID();

    queryfx($conn, 'INSERT');
    $id2 = $conn->getInsertID();

    $this->assertTrue((bool)$id1, pht('ID1 exists.'));
    $this->assertTrue((bool)$id2, pht('ID2 exists.'));
    $this->assertTrue(
      $id1 != $id2,
      pht("IDs '%s' and '%s' are distinct.", $id1, $id2));
  }

  public function testDeletePermitted() {
    $conn = $this->newIsolatedConnection();
    queryfx($conn, 'DELETE');

    $this->assertTrue(true);
  }

  public function testTransactionStack() {
    $conn = $this->newIsolatedConnection();
    $conn->openTransaction();
      queryfx($conn, 'INSERT');
    $conn->saveTransaction();
    $this->assertEqual(
      array(
        'START TRANSACTION',
        'INSERT',
        'COMMIT',
      ),
      $conn->getQueryTranscript());

    $conn = $this->newIsolatedConnection();
    $conn->openTransaction();
      queryfx($conn, 'INSERT 1');
      $conn->openTransaction();
        queryfx($conn, 'INSERT 2');
      $conn->killTransaction();
      $conn->openTransaction();
        queryfx($conn, 'INSERT 3');
        $conn->openTransaction();
          queryfx($conn, 'INSERT 4');
        $conn->saveTransaction();
      $conn->saveTransaction();
      $conn->openTransaction();
        queryfx($conn, 'INSERT 5');
      $conn->killTransaction();
      queryfx($conn, 'INSERT 6');
    $conn->saveTransaction();

    $this->assertEqual(
      array(
        'START TRANSACTION',
        'INSERT 1',
        'SAVEPOINT Aphront_Savepoint_1',
        'INSERT 2',
        'ROLLBACK TO SAVEPOINT Aphront_Savepoint_1',
        'SAVEPOINT Aphront_Savepoint_1',
        'INSERT 3',
        'SAVEPOINT Aphront_Savepoint_2',
        'INSERT 4',
        'SAVEPOINT Aphront_Savepoint_1',
        'INSERT 5',
        'ROLLBACK TO SAVEPOINT Aphront_Savepoint_1',
        'INSERT 6',
        'COMMIT',
      ),
      $conn->getQueryTranscript());
  }

  public function testTransactionRollback() {
    $check = array();

    $phid = new HarbormasterScratchTable();
    $phid->openTransaction();
      for ($ii = 0; $ii < 3; $ii++) {
        $key = $this->generateTestData();

        $obj = new HarbormasterScratchTable();
        $obj->setData($key);
        $obj->save();

        $check[] = $key;
      }
    $phid->killTransaction();

    foreach ($check as $key) {
      $this->assertNoSuchRow($key);
    }
  }

  private function newIsolatedConnection() {
    $config = array();
    return new AphrontIsolatedDatabaseConnection($config);
  }

  private function generateTestData() {
    return Filesystem::readRandomCharacters(20);
  }

  private function assertNoSuchRow($data) {
    try {
      $row = id(new HarbormasterScratchTable())->loadOneWhere(
        'data = %s',
        $data);
      $this->assertEqual(
        null,
        $row,
        pht('Expect fake row to exist only in isolation.'));
    } catch (AphrontConnectionQueryException $ex) {
      // If we can't connect to the database, conclude that the isolated
      // connection actually is isolated. Philosophically, this perhaps allows
      // us to claim this test does not depend on the database?
    }
  }

}
