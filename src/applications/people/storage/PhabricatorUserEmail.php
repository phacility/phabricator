<?php

/**
 * @task restrictions   Domain Restrictions
 * @task email          Email About Email
 */
final class PhabricatorUserEmail
  extends PhabricatorUserDAO
  implements
    PhabricatorDestructibleInterface,
    PhabricatorPolicyInterface {

  protected $userPHID;
  protected $address;
  protected $isVerified;
  protected $isPrimary;
  protected $verificationCode;

  private $user = self::ATTACHABLE;

  const MAX_ADDRESS_LENGTH = 128;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'address' => 'sort128',
        'isVerified' => 'bool',
        'isPrimary' => 'bool',
        'verificationCode' => 'text64?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'address' => array(
          'columns' => array('address'),
          'unique' => true,
        ),
        'userPHID' => array(
          'columns' => array('userPHID', 'isPrimary'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return PhabricatorPeopleUserEmailPHIDType::TYPECONST;
  }

  public function getVerificationURI() {
    return '/emailverify/'.$this->getVerificationCode().'/';
  }

  public function save() {
    if (!$this->verificationCode) {
      $this->setVerificationCode(Filesystem::readRandomCharacters(24));
    }
    return parent::save();
  }

  public function attachUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function getUser() {
    return $this->assertAttached($this->user);
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
      'Email addresses should be in the form "user@domain.com". The maximum '.
      'length of an email address is %s characters.',
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

    $lower_domain = phutil_utf8_strtolower($domain);
    foreach ($allowed_domains as $allowed_domain) {
      $lower_allowed = phutil_utf8_strtolower($allowed_domain);
      if ($lower_allowed === $lower_domain) {
        return true;
      }
    }

    return false;
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
      return pht('Email address must be @%s', head($domains));
    } else {
      return pht(
        'Email address must be at one of: %s',
        implode(', ', $domains));
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
      $signature = pht(
        "Get Well Soon,\n%s",
        PlatformSymbols::getPlatformServerName());
    }

    $body = sprintf(
      "%s\n\n%s\n\n  %s\n\n%s",
      pht('Hi %s', $username),
      pht(
        'Please verify that you own this email address (%s) by '.
        'clicking this link:',
        $address),
      $link,
      $signature);

    id(new PhabricatorMetaMTAMail())
      ->addRawTos(array($address))
      ->setForceDelivery(true)
      ->setSubject(
        pht(
          '[%s] Email Verification',
          PlatformSymbols::getPlatformServerName()))
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

    $body = sprintf(
      "%s\n\n%s\n",
      pht('Hi %s', $username),
      pht(
        'This email address (%s) is no longer your primary email address. '.
        'Going forward, all email will be sent to your new primary email '.
        'address (%s).',
        $old_address,
        $new_address));

    id(new PhabricatorMetaMTAMail())
      ->addRawTos(array($old_address))
      ->setForceDelivery(true)
      ->setSubject(
        pht(
          '[%s] Primary Address Changed',
          PlatformSymbols::getPlatformServerName()))
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

    $body = sprintf(
      "%s\n\n%s\n",
      pht('Hi %s', $username),
      pht(
        'This is now your primary email address (%s). Going forward, '.
        'all email will be sent here.',
        $new_address));

    id(new PhabricatorMetaMTAMail())
      ->addRawTos(array($new_address))
      ->setForceDelivery(true)
      ->setSubject(
        pht(
          '[%s] Primary Address Changed',
          PlatformSymbols::getPlatformServerName()))
      ->setBody($body)
      ->setFrom($user->getPHID())
      ->setRelatedPHID($user->getPHID())
      ->saveAndSend();

    return $this;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {
    $this->delete();
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    $user = $this->getUser();

    if ($this->getIsSystemAgent() || $this->getIsMailingList()) {
      return PhabricatorPolicies::POLICY_ADMIN;
    }

    return $user->getPHID();
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

}
