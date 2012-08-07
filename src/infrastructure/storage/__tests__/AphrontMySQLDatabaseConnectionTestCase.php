<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
      } catch (AphrontQueryConnectionLostException $ex) {
        $caught = $ex;
      }
      $this->assertEqual(true, $caught instanceof Exception);
  }

}
