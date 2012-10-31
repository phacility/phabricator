<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
  public static function isAllowedAddress($address) {
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
