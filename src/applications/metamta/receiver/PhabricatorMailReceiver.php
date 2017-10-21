<?php

abstract class PhabricatorMailReceiver extends Phobject {

  private $applicationEmail;

  public function setApplicationEmail(
    PhabricatorMetaMTAApplicationEmail $email) {
    $this->applicationEmail = $email;
    return $this;
  }

  public function getApplicationEmail() {
    return $this->applicationEmail;
  }

  abstract public function isEnabled();
  abstract public function canAcceptMail(PhabricatorMetaMTAReceivedMail $mail);
  final protected function canAcceptApplicationMail(
    PhabricatorApplication $app,
    PhabricatorMetaMTAReceivedMail $mail) {

    $application_emails = id(new PhabricatorMetaMTAApplicationEmailQuery())
      ->setViewer($this->getViewer())
      ->withApplicationPHIDs(array($app->getPHID()))
      ->execute();

    foreach ($mail->getToAddresses() as $to_address) {
      foreach ($application_emails as $application_email) {
        $create_address = $application_email->getAddress();
        if ($this->matchAddresses($create_address, $to_address)) {
          $this->setApplicationEmail($application_email);
          return true;
        }
      }
    }

    return false;
  }


  abstract protected function processReceivedMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhabricatorUser $sender);

  final public function receiveMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhabricatorUser $sender) {
    $this->processReceivedMail($mail, $sender);
  }

  public function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

  public function validateSender(
    PhabricatorMetaMTAReceivedMail $mail,
    PhabricatorUser $sender) {

    $failure_reason = null;
    if ($sender->getIsDisabled()) {
      $failure_reason = pht(
        'Your account (%s) is disabled, so you can not interact with '.
        'Phabricator over email.',
        $sender->getUsername());
    } else if ($sender->getIsStandardUser()) {
      if (!$sender->getIsApproved()) {
        $failure_reason = pht(
          'Your account (%s) has not been approved yet. You can not interact '.
          'with Phabricator over email until your account is approved.',
          $sender->getUsername());
      } else if (PhabricatorUserEmail::isEmailVerificationRequired() &&
               !$sender->getIsEmailVerified()) {
        $failure_reason = pht(
          'You have not verified the email address for your account (%s). '.
          'You must verify your email address before you can interact '.
          'with Phabricator over email.',
          $sender->getUsername());
      }
    }

    if ($failure_reason) {
      throw new PhabricatorMetaMTAReceivedMailProcessingException(
        MetaMTAReceivedMailStatus::STATUS_DISABLED_SENDER,
        $failure_reason);
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
        'This email was sent from "%s", but that address is not recognized by '.
        'Phabricator and does not correspond to any known user account.',
        $raw_from);
    }

    // If we missed on "From", try "Reply-To" if we're configured for it.
    $raw_reply_to = $mail->getHeader('Reply-To');
    if (strlen($raw_reply_to)) {
      $reply_to_key = 'metamta.insecure-auth-with-reply-to';
      $allow_reply_to = PhabricatorEnv::getEnvConfig($reply_to_key);
      if ($allow_reply_to) {
        $reply_to = self::getRawAddress($raw_reply_to);

        $user = PhabricatorUser::loadOneWithEmailAddress($reply_to);
        if ($user) {
          return $user;
        } else {
          $reasons[] = pht(
            'Phabricator is configured to authenticate users using the '.
            '"Reply-To" header, but the reply address ("%s") on this '.
            'message does not correspond to any known user account.',
            $raw_reply_to);
        }
      } else {
        $reasons[] = pht(
          '(Phabricator is not configured to authenticate users using the '.
          '"Reply-To" header, so it was ignored.)');
      }
    }

    // If we don't know who this user is, load or create an external user
    // account for them if we're configured for it.
    $email_key = 'phabricator.allow-email-users';
    $allow_email_users = PhabricatorEnv::getEnvConfig($email_key);
    if ($allow_email_users) {
      $from_obj = new PhutilEmailAddress($from);
      $xuser = id(new PhabricatorExternalAccountQuery())
        ->setViewer($this->getViewer())
        ->withAccountTypes(array('email'))
        ->withAccountDomains(array($from_obj->getDomainName(), 'self'))
        ->withAccountIDs(array($from_obj->getAddress()))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->loadOneOrCreate();
      return $xuser->getPhabricatorUser();
    } else {
      // NOTE: Currently, we'll always drop this mail (since it's headed to
      // an unverified recipient). See T12237. These details are still useful
      // because they'll appear in the mail logs and Mail web UI.

      $reasons[] = pht(
        'Phabricator is also not configured to allow unknown external users '.
        'to send mail to the system using just an email address.');
      $reasons[] = pht(
        'To interact with Phabricator, add this address ("%s") to your '.
        'account.',
        $raw_from);
    }

    if ($this->getApplicationEmail()) {
      $application_email = $this->getApplicationEmail();
      $default_user_phid = $application_email->getConfigValue(
        PhabricatorMetaMTAApplicationEmail::CONFIG_DEFAULT_AUTHOR);

      if ($default_user_phid) {
        $user = id(new PhabricatorUser())->loadOneWhere(
          'phid = %s',
          $default_user_phid);
        if ($user) {
          return $user;
        }

        $reasons[] = pht(
          'Phabricator is misconfigured: the application email '.
          '"%s" is set to user "%s", but that user does not exist.',
          $application_email->getAddress(),
          $default_user_phid);
      }
    }

    $reasons = implode("\n\n", $reasons);

    throw new PhabricatorMetaMTAReceivedMailProcessingException(
      MetaMTAReceivedMailStatus::STATUS_UNKNOWN_SENDER,
      $reasons);
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
   * Reduce an email address to its canonical form. For example, an address
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
