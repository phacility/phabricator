<?php

final class PhabricatorHMACTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testHMACKeyGeneration() {
    $input = 'quack';

    $hash_1 = PhabricatorHash::digestWithNamedKey($input, 'test');
    $hash_2 = PhabricatorHash::digestWithNamedKey($input, 'test');

    $this->assertEqual($hash_1, $hash_2);
  }

  public function testSHA256Hashing() {
    $input = 'quack';
    $key = 'duck';
    $expect =
      '5274473dc34fc61bd7a6a5ff258e6505'.
      '4b26644fb7a272d74f276ab677361b9a';

    $hash = PhabricatorHash::digestHMACSHA256($input, $key);
    $this->assertEqual($expect, $hash);

    $input = 'The quick brown fox jumps over the lazy dog';
    $key = 'key';
    $expect =
      'f7bc83f430538424b13298e6aa6fb143'.
      'ef4d59a14946175997479dbc2d1a3cd8';

    $hash = PhabricatorHash::digestHMACSHA256($input, $key);
    $this->assertEqual($expect, $hash);
  }

}
