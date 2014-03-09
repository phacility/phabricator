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

  public function testMD5Hasher() {
    $hasher = new PhabricatorIteratedMD5PasswordHasher();

    $this->assertEqual(
      'md5:4824a35493d8b5dceab36f017d68425f',
      $hasher->getPasswordHashForStorage(
        new PhutilOpaqueEnvelope('quack'))->openEnvelope());
  }

}
