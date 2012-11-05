<?php

final class LiskIsolationTestCase extends PhabricatorTestCase {

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

  public function testEphemeral() {
    $dao = new LiskIsolationTestDAO();
    $dao->save();
    $dao->makeEphemeral();

    $this->tryTestCases(
      array(
        $dao,
      ),
      array(
        false,
      ),
      array($this, 'saveDAO'));
  }

  public function saveDAO($dao) {
    $dao->save();
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
