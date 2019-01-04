<?php

final class PhabricatorMailReceiverTestCase extends PhabricatorTestCase {

  public function testAddressSimilarity() {
    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('metamta.single-reply-handler-prefix', 'prefix');

    $base = 'alincoln@example.com';

    $same = array(
      'alincoln@example.com',
      '"Abrahamn Lincoln" <alincoln@example.com>',
      'ALincoln@example.com',
      'prefix+alincoln@example.com',
    );

    foreach ($same as $address) {
      $this->assertTrue(
        PhabricatorMailUtil::matchAddresses(
          new PhutilEmailAddress($base),
          new PhutilEmailAddress($address)),
        pht('Address %s', $address));
    }

    $diff = array(
      'a.lincoln@example.com',
      'aluncoln@example.com',
      'prefixalincoln@example.com',
      'badprefix+alincoln@example.com',
      'bad+prefix+alincoln@example.com',
      'prefix+alincoln+sufffix@example.com',
    );

    foreach ($diff as $address) {
      $this->assertFalse(
        PhabricatorMailUtil::matchAddresses(
          new PhutilEmailAddress($base),
          new PhutilEmailAddress($address)),
        pht('Address: %s', $address));
    }
  }

  public function testReservedAddresses() {
    $default_address = id(new PhabricatorMailEmailEngine())
      ->newDefaultEmailAddress();

    $void_address = id(new PhabricatorMailEmailEngine())
      ->newVoidEmailAddress();

    $map = array(
      'alincoln@example.com' => false,
      'sysadmin@example.com' => true,
      'hostmaster@example.com' => true,
      '"Walter Ebmaster" <webmaster@example.com>' => true,
      (string)$default_address => true,
      (string)$void_address => true,
    );

    foreach ($map as $raw_address => $expect) {
      $address = new PhutilEmailAddress($raw_address);

      $this->assertEqual(
        $expect,
        PhabricatorMailUtil::isReservedAddress($address),
        pht('Reserved: %s', $raw_address));
    }
  }

}
