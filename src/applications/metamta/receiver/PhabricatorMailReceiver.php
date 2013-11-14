<?php

abstract class PhabricatorMailReceiver {

  abstract public function isEnabled();
  abstract public function canAcceptMail(PhabricatorMetaMTAReceivedMail $mail);

  abstract protected function processReceivedMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhabricatorUser $sender);

  final public function receiveMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhabricatorUser $sender) {
    $this->processReceivedMail($mail, $sender);
  }

  public function validateSender(
    PhabricatorMetaMTAReceivedMail $mail,
    PhabricatorUser $sender) {

    if (!$sender->isUserActivated()) {
      throw new PhabricatorMetaMTAReceivedMailProcessingException(
        MetaMTAReceivedMailStatus::STATUS_DISABLED_SENDER,
        pht(
          "Sender '%s' does not have an activated user account.",
          $sender->getUsername()));
    }
  }

  /**
   * Identifies the sender's user account for a piece of received mail. Note
   * that this method does not validate that the sender is who they say they
   * are, just that they've presented some credential which corresponds to a
   * recognizable user.
   */
  public function loadSender(PhabricatorMetaMTAReceivedMail $mail) {
    $raw_from = $mail->getHeader('From');
    $from = self::getRawAddress($raw_from);

    $reasons = array();

    // Try to find a user with this email address.
    $user = PhabricatorUser::loadOneWithEmailAddress($from);
    if ($user) {
      return $user;
    } else {
      $reasons[] = pht(
        "The email was sent from '%s', but this address does not correspond ".
        "to any user account.",
        $raw_from);
    }

    // If we missed on "From", try "Reply-To" if we're configured for it.
    $reply_to_key = 'metamta.insecure-auth-with-reply-to';
    $allow_reply_to = PhabricatorEnv::getEnvConfig($reply_to_key);
    if ($allow_reply_to) {
      $raw_reply_to = $mail->getHeader('Reply-To');
      $reply_to = self::getRawAddress($raw_reply_to);

      $user = PhabricatorUser::loadOneWithEmailAddress($reply_to);
      if ($user) {
        return $user;
      } else {
        $reasons[] = pht(
          "Phabricator is configured to try to authenticate users using ".
          "'Reply-To', but the reply to address ('%s') does not correspond ".
          "to any user account.",
          $raw_reply_to);
      }
    } else {
      $reasons[] = pht(
        "Phabricator is not configured to authenticate users using ".
        "'Reply-To' (`metamta.insecure-auth-with-reply-to`), so the ".
        "'Reply-To' header was not examined.");
    }

    // If we don't know who this user is, load or create an external user
    // account for them if we're configured for it.
    $email_key = 'phabricator.allow-email-users';
    $allow_email_users = PhabricatorEnv::getEnvConfig($email_key);
    if ($allow_email_users) {
      $xuser = id(new PhabricatorExternalAccount())->loadOneWhere(
        'accountType = %s AND accountDomain = %s and accountID = %s',
        'email',
        'self',
        $from);
      if (!$xuser) {
        $xuser = id(new PhabricatorExternalAccount())
          ->setAccountID($from)
          ->setAccountType('email')
          ->setAccountDomain('self')
          ->setDisplayName($from)
          ->setEmail($from)
          ->save();
      }
      return $xuser->getPhabricatorUser();
    } else {
      $reasons[] = pht(
        "Phabricator is not configured to allow unknown external users to ".
        "send mail to the system using just an email address ".
        "(`phabricator.allow-email-users`), so an implicit external acount ".
        "could not be created.");
    }

    throw new PhabricatorMetaMTAReceivedMailProcessingException(
      MetaMTAReceivedMailStatus::STATUS_UNKNOWN_SENDER,
      pht('Unknown sender: %s', implode(' ', $reasons)));
  }

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
    $u = self::getRawAddress($u);
    $v = self::getRawAddress($v);

    $u = self::stripMailboxPrefix($u);
    $v = self::stripMailboxPrefix($v);

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


  /**
   * Reduce an email address to its canonical form. For example, an adddress
   * like:
   *
   *  "Abraham Lincoln" < ALincoln@example.com >
   *
   * ...will be reduced to:
   *
   *  alincoln@example.com
   *
   * @param   string  Email address in noncanonical form.
   * @return  string  Canonical email address.
   */
  public static function getRawAddress($address) {
    $address = id(new PhutilEmailAddress($address))->getAddress();
    return trim(phutil_utf8_strtolower($address));
  }

}
