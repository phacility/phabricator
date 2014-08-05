<?php

final class AphrontMySQLDatabaseConnectionTestCase
  extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      // We disable this here because we're testing live MySQL connections.
      self::PHABRICATOR_TESTCONFIG_ISOLATE_LISK => false,
    );
  }

  public function testConnectionFailures() {
    $conn = id(new HarbormasterScratchTable())->establishConnection('r');

    queryfx($conn, 'SELECT 1');

    // We expect the connection to recover from a 2006 (lost connection) when
    // outside of a transaction...
    $conn->simulateErrorOnNextQuery(2006);
    queryfx($conn, 'SELECT 1');

    // ...but when transactional, we expect the query to throw when the
    // connection is lost, because it indicates the transaction was aborted.
    $conn->openTransaction();
      $conn->simulateErrorOnNextQuery(2006);

      $caught = null;
      try {
        queryfx($conn, 'SELECT 1');
      } catch (AphrontConnectionLostQueryException $ex) {
        $caught = $ex;
      }
      $this->assertTrue($caught instanceof Exception);
  }

}
