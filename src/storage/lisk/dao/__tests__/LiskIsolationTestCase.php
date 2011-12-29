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

class LiskIsolationTestCase extends PhabricatorTestCase {

  public function testIsolatedWrites() {
    $dao = new LiskIsolationTestDAO();

    $this->assertEqual(null, $dao->getID(), 'Expect no ID.');
    $this->assertEqual(null, $dao->getPHID(), 'Expect no PHID.');

    $dao->save(); // Effects insert

    $id = $dao->getID();
    $phid = $dao->getPHID();

    $this->assertEqual(true, (bool)$id, 'Expect ID generated.');
    $this->assertEqual(true, (bool)$phid, 'Expect PHID generated.');

    $dao->save(); // Effects update

    $this->assertEqual($id, $dao->getID(), 'Expect ID unchanged.');
    $this->assertEqual($phid, $dao->getPHID(), 'Expect PHID unchanged.');
  }

  public function testIsolationContainment() {
    $dao = new LiskIsolationTestDAO();

    try {
      $dao->establishLiveConnection('r');

      $this->assertFailure(
        "LiskIsolationTestDAO did not throw an exception when instructed to ".
        "explicitly connect to an external database.");
    } catch (LiskIsolationTestDAOException $ex) {
      // Expected, pass.
    }

  }

  public function testMagicMethods() {

    $dao = new LiskIsolationTestDAO();

    $this->assertEqual(
      null,
      $dao->getName(),
      'getName() on empty object');

    $this->assertEqual(
      $dao,
      $dao->setName('x'),
      'setName() returns $this');

    $this->assertEqual(
      'y',
      $dao->setName('y')->getName(),
      'setName() has an effect');

    $ex = null;
    try {
      $dao->gxxName();
    } catch (Exception $thrown) {
      $ex = $thrown;
    }
    $this->assertEqual(
      true,
      (bool)$ex,
      'Typoing "get" should throw.');

    $ex = null;
    try {
      $dao->sxxName('z');
    } catch (Exception $thrown) {
      $ex = $thrown;
    }
    $this->assertEqual(
      true,
      (bool)$ex,
      'Typoing "set" should throw.');

    $ex = null;
    try {
      $dao->madeUpMethod();
    } catch (Exception $thrown) {
      $ex = $thrown;
    }
    $this->assertEqual(
      true,
      (bool)$ex,
      'Made up method should throw.');
  }

}
