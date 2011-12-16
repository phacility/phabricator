<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class AphrontIsolatedDatabaseConnectionTestCase
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
    $conn = $this->newIsolatedConnection();

    $test_phid = 'PHID-TEST-'.Filesystem::readRandomCharacters(20);

    queryfx(
      $conn,
      'INSERT INTO phabricator_phid.phid (phid) VALUES (%s)',
      $test_phid);

    try {
      $real_phid = id(new PhabricatorPHID())->loadOneWhere(
        'phid = %s',
        $test_phid);
      $this->assertEqual(
        null,
        $real_phid,
        'Expect fake PHID to exist only in isolation.');
    } catch (AphrontQueryConnectionException $ex) {
      // If we can't connect to the database, conclude that the isolated
      // connection actually is isolated. Philosophically, this perhaps allows
      // us to claim this test does not depend on the database?
    }
  }

  public function testInsertGeneratesID() {
    $conn = $this->newIsolatedConnection();

    queryfx($conn, 'INSERT');
    $id1 = $conn->getInsertID();

    queryfx($conn, 'INSERT');
    $id2 = $conn->getInsertID();

    $this->assertEqual(true, (bool)$id1, 'ID1 exists.');
    $this->assertEqual(true, (bool)$id2, 'ID2 exists.');
    $this->assertEqual(
      true,
      $id1 != $id2,
      "IDs '{$id1}' and '{$id2}' are distinct.");
  }

  public function testDeletePermitted() {
    $conn = $this->newIsolatedConnection();
    queryfx($conn, 'DELETE');
  }

  private function newIsolatedConnection() {
    $config = array();
    return new AphrontIsolatedDatabaseConnection($config);
  }
}
