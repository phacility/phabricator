<?php

final class PhabricatorMailUtil
  extends Phobject {

  /**
   * Normalize an email address for comparison or lookup.
   *
   * Phabricator can be configured to prepend a prefix to all reply addresses,
   * which can make forwarding rules easier to write. This method strips the
   * prefix if it is present, and normalizes casing and whitespace.
   *
   * @param PhutilEmailAddress Email address.
   * @return PhutilEmailAddress Normalized address.
   */
  public static function normalizeAddress(PhutilEmailAddress $address) {
    $raw_address = $address->getAddress();
    $raw_address = phutil_utf8_strtolower($raw_address);
    $raw_address = trim($raw_address);

    // If a mailbox prefix is configured and present, strip it off.
    $prefix_key = 'metamta.single-reply-handler-prefix';
    $prefix = PhabricatorEnv::getEnvConfig($prefix_key);

    if (phutil_nonempty_string($prefix)) {
      $prefix = $prefix.'+';
      $len = strlen($prefix);

      if (!strncasecmp($raw_address, $prefix, $len)) {
        $raw_address = substr($raw_address, $len);
      }
    }

    return id(clone $address)
      ->setAddress($raw_address);
  }

  /**
   * Determine if two inbound email addresses are effectively identical.
   *
   * This method strips and normalizes addresses so that equivalent variations
   * are correctly detected as identical. For example, these addresses are all
   * considered to match one another:
   *
   *   "Abraham Lincoln" <alincoln@example.com>
   *   alincoln@example.com
   *   <ALincoln@example.com>
   *   "Abraham" <phabricator+ALINCOLN@EXAMPLE.COM> # With configured prefix.
   *
   * @param   PhutilEmailAddress Email address.
   * @param   PhutilEmailAddress Another email address.
   * @return  bool True if addresses are effectively the same address.
   */
  public static function matchAddresses(
    PhutilEmailAddress $u,
    PhutilEmailAddress $v) {

    $u = self::normalizeAddress($u);
    $v = self::normalizeAddress($v);

    return ($u->getAddress() === $v->getAddress());
  }

  public static function isReservedAddress(PhutilEmailAddress $address) {
    $address = self::normalizeAddress($address);
    $local = $address->getLocalPart();

    $reserved = array(
      'admin',
      'administrator',
      'hostmaster',
      'list',
      'list-request',
      'majordomo',
      'postmaster',
      'root',
      'ssl-admin',
      'ssladmin',
      'ssladministrator',
      'sslwebmaster',
      'sysadmin',
      'uucp',
      'webmaster',

      'noreply',
      'no-reply',
    );

    $reserved = array_fuse($reserved);

    if (isset($reserved[$local])) {
      return true;
    }

    $default_address = id(new PhabricatorMailEmailEngine())
      ->newDefaultEmailAddress();
    if (self::matchAddresses($address, $default_address)) {
      return true;
    }

    $void_address = id(new PhabricatorMailEmailEngine())
      ->newVoidEmailAddress();
    if (self::matchAddresses($address, $void_address)) {
      return true;
    }

    return false;
  }

  public static function isUserAddress(PhutilEmailAddress $address) {
    $user_email = id(new PhabricatorUserEmail())->loadOneWhere(
      'address = %s',
      $address->getAddress());

    return (bool)$user_email;
  }

}
