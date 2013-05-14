<?php

abstract class PhabricatorMailReceiver {


  abstract public function isEnabled();
  abstract public function canAcceptMail(PhabricatorMetaMTAReceivedMail $mail);

  /**
   * Determine if two inbound email addresses are effectively identical. This
   * method strips and normalizes addresses so that equivalent variations are
   * correctly detected as identical. For example, these addresses are all
   * considered to match one another:
   *
   *   "Abraham Lincoln" <alincoln@example.com>
   *   alincoln@example.com
   *   <ALincoln@example.com>
   *   "Abraham" <phabricator+ALINCOLN@EXAMPLE.COM> # With configured prefix.
   *
   * @param   string  Email address.
   * @param   string  Another email address.
   * @return  bool    True if addresses match.
   */
  public static function matchAddresses($u, $v) {
    $u = id(new PhutilEmailAddress($u))->getAddress();
    $v = id(new PhutilEmailAddress($v))->getAddress();

    $u = self::stripMailboxPrefix($u);
    $v = self::stripMailboxPrefix($v);

    $u = trim(phutil_utf8_strtolower($u));
    $v = trim(phutil_utf8_strtolower($v));

    return ($u === $v);
  }


  /**
   * Strip a global mailbox prefix from an address if it is present. Phabricator
   * can be configured to prepend a prefix to all reply addresses, which can
   * make forwarding rules easier to write. A prefix looks like:
   *
   *  example@phabricator.example.com              # No Prefix
   *  phabricator+example@phabricator.example.com  # Prefix "phabricator"
   *
   * @param   string  Email address, possibly with a mailbox prefix.
   * @return  string  Email address with any prefix stripped.
   */
  public static function stripMailboxPrefix($address) {
    $address = id(new PhutilEmailAddress($address))->getAddress();

    $prefix_key = 'metamta.single-reply-handler-prefix';
    $prefix = PhabricatorEnv::getEnvConfig($prefix_key);

    $len = strlen($prefix);

    if ($len) {
      $prefix = $prefix.'+';
      $len = $len + 1;
    }

    if ($len) {
      if (!strncasecmp($address, $prefix, $len)) {
        $address = substr($address, strlen($prefix));
      }
    }

    return $address;
  }


}
