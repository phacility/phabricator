<?php

final class PhabricatorIteratedMD5PasswordHasherTestCase
  extends PhabricatorTestCase {

  public function testHasher() {
    $hasher = new PhabricatorIteratedMD5PasswordHasher();

    $this->assertEqual(
      'md5:4824a35493d8b5dceab36f017d68425f',
      $hasher->getPasswordHashForStorage(
        new PhutilOpaqueEnvelope('quack'))->openEnvelope());
  }

}
