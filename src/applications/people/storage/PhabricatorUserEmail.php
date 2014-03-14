<?php

/**
 * @task restrictions   Domain Restrictions
 * @task email          Email About Email
 */
final class PhabricatorUserEmail extends PhabricatorUserDAO {

  protected $userPHID;
  protected $address;
  protected $isVerified;
  protected $isPrimary;
  protected $verificationCode;

  const MAX_ADDRESS_LENGTH = 128;

  public function getVerificationURI() {
    return '/emailverify/'.$this->getVerificationCode().'/';
  }

  public function save() {
    if (!$this->verificationCode) {
      $this->setVerificationCode(Filesystem::readRandomCharacters(24));
    }
    return parent::save();
  }


/* -(  Domain Restrictions  )------------------------------------------------ */


  /**
   * @task restrictions
   */
  public static function isValidAddress($address) {
    if (strlen($address) > self::MAX_ADDRESS_LENGTH) {
      return false;
    }

    // Very roughly validate that this address isn't so mangled that a
    // reasonable piece of code might completely misparse it. In particular,
    // the major risks are:
    //
    //   - `PhutilEmailAddress` needs to be able to extract the domain portion
    //     from it.
    //   - Reasonable mail adapters should be hard-pressed to interpret one
    //     address as several addresses.
    //
    // To this end, we're roughly verifying that there's some normal text, an
    // "@" symbol, and then some more normal text.

    $email_regex = '(^[a-z0-9_+.!-]+@[a-z0-9_+:.-]+\z)i';
    if (!preg_match($email_regex, $address)) {
      return false;
    }

    return true;
  }


  /**
   * @task restrictions
   */
  public static function describeValidAddresses() {
    return pht(
      "Email addresses should be in the form 'user@domain.com'. The maximum ".
      "length of an email address is %d character(s).",
      new PhutilNumber(self::MAX_ADDRESS_LENGTH));
  }


  /**
   * @task restrictions
   */
  public static function isAllowedAddress($address) {
    if (!self::isValidAddress($address)) {
      return false;
    }

    $allowed_domains = PhabricatorEnv::getEnvConfig('auth.email-domains');
    if (!$allowed_domains) {
      return true;
    }

    $addr_obj = new PhutilEmailAddress($address);

    $domain = $addr_obj->getDomainName();
    if (!$domain) {
      return false;
    }

    return in_array($domain, $allowed_domains);
  }


  /**
   * @task restrictions
   */
  public static function describeAllowedAddresses() {
    $domains = PhabricatorEnv::getEnvConfig('auth.email-domains');
    if (!$domains) {
      return null;
    }

    if (count($domains) == 1) {
      return 'Email address must be @'.head($domains);
    } else {
      return 'Email address must be at one of: '.
        implode(', ', $domains);
    }
  }


  /**
   * Check if this install requires email verification.
   *
   * @return bool True if email addresses must be verified.
   *
   * @task restrictions
   */
  public static function isEmailVerificationRequired() {
    // NOTE: Configuring required email domains implies required verification.
    return PhabricatorEnv::getEnvConfig('auth.require-email-verification') ||
           PhabricatorEnv::getEnvConfig('auth.email-domains');
  }


/* -(  Email About Email  )-------------------------------------------------- */


  /**
   * Send a verification email from $user to this address.
   *
   * @param PhabricatorUser The user sending the verification.
   * @return this
   * @task email
   */
  public function sendVerificationEmail(PhabricatorUser $user) {
    $username = $user->getUsername();

    $address = $this->getAddress();
    $link = PhabricatorEnv::getProductionURI($this->getVerificationURI());


    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $signature = null;
    if (!$is_serious) {
      $signature = <<<EOSIGNATURE
Get Well Soon,
Phabricator
EOSIGNATURE;
    }

    $body = <<<EOBODY
Hi {$username},

Please verify that you own this email address ({$address}) by clicking this
link:

  {$link}

{$signature}
EOBODY;

    id(new PhabricatorMetaMTAMail())
      ->addRawTos(array($address))
      ->setSubject('[Phabricator] Email Verification')
      ->setBody($body)
      ->setRelatedPHID($user->getPHID())
      ->saveAndSend();

    return $this;
  }


  /**
   * Send a notification email from $user to this address, informing the
   * recipient that this is no longer their account's primary address.
   *
   * @param PhabricatorUser The user sending the notification.
   * @param PhabricatorUserEmail New primary email address.
   * @return this
   * @task email
   */
  public function sendOldPrimaryEmail(
    PhabricatorUser $user,
    PhabricatorUserEmail $new) {
    $username = $user->getUsername();

    $old_address = $this->getAddress();
    $new_address = $new->getAddress();

    $body = <<<EOBODY
Hi {$username},

This email address ({$old_address}) is no longer your primary email address.
Going forward, Phabricator will send all email to your new primary email
address ({$new_address}).

EOBODY;

    id(new PhabricatorMetaMTAMail())
      ->addRawTos(array($old_address))
      ->setSubject('[Phabricator] Primary Address Changed')
      ->setBody($body)
      ->setFrom($user->getPHID())
      ->setRelatedPHID($user->getPHID())
      ->saveAndSend();
  }


  /**
   * Send a notification email from $user to this address, informing the
   * recipient that this is now their account's new primary email address.
   *
   * @param PhabricatorUser The user sending the verification.
   * @return this
   * @task email
   */
  public function sendNewPrimaryEmail(PhabricatorUser $user) {
    $username = $user->getUsername();

    $new_address = $this->getAddress();

    $body = <<<EOBODY
Hi {$username},

This is now your primary email address ({$new_address}). Going forward,
Phabricator will send all email here.

EOBODY;

    id(new PhabricatorMetaMTAMail())
      ->addRawTos(array($new_address))
      ->setSubject('[Phabricator] Primary Address Changed')
      ->setBody($body)
      ->setFrom($user->getPHID())
      ->setRelatedPHID($user->getPHID())
      ->saveAndSend();

    return $this;
  }

}
