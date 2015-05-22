<?php

final class LiskIsolationTestCase extends PhabricatorTestCase {

  public function testIsolatedWrites() {
    $dao = new LiskIsolationTestDAO();

    $this->assertEqual(null, $dao->getID(), pht('Expect no ID.'));
    $this->assertEqual(null, $dao->getPHID(), pht('Expect no PHID.'));

    $dao->save(); // Effects insert

    $id = $dao->getID();
    $phid = $dao->getPHID();

    $this->assertTrue((bool)$id, pht('Expect ID generated.'));
    $this->assertTrue((bool)$phid, pht('Expect PHID generated.'));

    $dao->save(); // Effects update

    $this->assertEqual($id, $dao->getID(), pht('Expect ID unchanged.'));
    $this->assertEqual($phid, $dao->getPHID(), pht('Expect PHID unchanged.'));
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
      $method = new ReflectionMethod($dao, 'establishLiveConnection');
      $method->setAccessible(true);
      $method->invoke($dao, 'r');

      $this->assertFailure(
        pht(
          '%s did not throw an exception when instructed to '.
          'explicitly connect to an external database.',
          'LiskIsolationTestDAO'));
    } catch (LiskIsolationTestDAOException $ex) {
      // Expected, pass.
    }

    $this->assertTrue(true);
  }

  public function testMagicMethods() {

    $dao = new LiskIsolationTestDAO();

    $this->assertEqual(
      null,
      $dao->getName(),
      pht('%s on empty object', 'getName()'));

    $this->assertEqual(
      $dao,
      $dao->setName('x'),
      pht('%s returns %s', 'setName()', '$this'));

    $this->assertEqual(
      'y',
      $dao->setName('y')->getName(),
      pht('%s has an effect', 'setName()'));

    $ex = null;
    try {
      $dao->gxxName();
    } catch (Exception $thrown) {
      $ex = $thrown;
    }
    $this->assertTrue(
      (bool)$ex,
      pht('Typoing "%s" should throw.', 'get'));

    $ex = null;
    try {
      $dao->sxxName('z');
    } catch (Exception $thrown) {
      $ex = $thrown;
    }
    $this->assertTrue(
      (bool)$ex,
      pht('Typoing "%s" should throw.', 'set'));

    $ex = null;
    try {
      $dao->madeUpMethod();
    } catch (Exception $thrown) {
      $ex = $thrown;
    }
    $this->assertTrue(
      (bool)$ex,
      pht('Made up method should throw.'));
  }

}
