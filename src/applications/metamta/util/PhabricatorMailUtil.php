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
    $len = strlen($prefix);

    if ($len) {
      $prefix = $prefix.'+';
      $len = $len + 1;

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

}
