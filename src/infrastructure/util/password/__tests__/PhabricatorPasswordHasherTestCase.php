<?php

final class PhabricatorPasswordHasherTestCase extends PhabricatorTestCase {

  public function testHasherSyntax() {
    $caught = null;
    try {
      PhabricatorPasswordHasher::getHasherForHash(
        new PhutilOpaqueEnvelope('xxx'));
    } catch (Exception $ex) {
      $caught = $ex;
    }

    $this->assertTrue(
      ($caught instanceof Exception),
      pht('Exception on unparseable hash format.'));

    $caught = null;
    try {
      PhabricatorPasswordHasher::getHasherForHash(
        new PhutilOpaqueEnvelope('__test__:yyy'));
    } catch (Exception $ex) {
      $caught = $ex;
    }

    $this->assertTrue(
      ($caught instanceof PhabricatorPasswordHasherUnavailableException),
      pht('Fictional hasher unavailable.'));
  }

  public function testGetAllHashers() {
    PhabricatorPasswordHasher::getAllHashers();
    $this->assertTrue(true);
  }

}
