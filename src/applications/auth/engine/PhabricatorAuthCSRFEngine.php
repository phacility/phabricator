<?php

final class PhabricatorAuthCSRFEngine extends Phobject {

  private $salt;
  private $secret;

  public function setSalt($salt) {
    $this->salt = $salt;
    return $this;
  }

  public function getSalt() {
    return $this->salt;
  }

  public function setSecret(PhutilOpaqueEnvelope $secret) {
    $this->secret = $secret;
    return $this;
  }

  public function getSecret() {
    return $this->secret;
  }

  public function newSalt() {
    $salt_length = $this->getSaltLength();
    return Filesystem::readRandomCharacters($salt_length);
  }

  public function newToken() {
    $salt = $this->getSalt();

    if (!$salt) {
      throw new PhutilInvalidStateException('setSalt');
    }

    $token = $this->newRawToken($salt);
    $prefix = $this->getBREACHPrefix();

    return sprintf('%s%s%s', $prefix, $salt, $token);
  }

  public function isValidToken($token) {
    $salt_length = $this->getSaltLength();

    // We expect a BREACH-mitigating token. See T3684.
    $breach_prefix = $this->getBREACHPrefix();
    $breach_prelen = strlen($breach_prefix);
    if (strncmp($token, $breach_prefix, $breach_prelen) !== 0) {
      return false;
    }

    $salt = substr($token, $breach_prelen, $salt_length);
    $token = substr($token, $breach_prelen + $salt_length);

    foreach ($this->getWindowOffsets() as $offset) {
      $expect_token = $this->newRawToken($salt, $offset);
      if (phutil_hashes_are_identical($expect_token, $token)) {
        return true;
      }
    }

    return false;
  }

  private function newRawToken($salt, $offset = 0) {
    $now = PhabricatorTime::getNow();
    $cycle_frequency = $this->getCycleFrequency();

    $time_block = (int)floor($now / $cycle_frequency);
    $time_block = $time_block + $offset;

    $secret = $this->getSecret();
    if (!$secret) {
      throw new PhutilInvalidStateException('setSecret');
    }
    $secret = $secret->openEnvelope();

    $hash = PhabricatorHash::digestWithNamedKey(
      $secret.$time_block.$salt,
      'csrf');

    return substr($hash, 0, $this->getTokenLength());
  }

  private function getBREACHPrefix() {
    return 'B@';
  }

  private function getSaltLength() {
    return 8;
  }

  private function getTokenLength() {
    return 16;
  }

  private function getCycleFrequency() {
    return phutil_units('1 hour in seconds');
  }

  private function getWindowOffsets() {
    // We accept some tokens from the recent past and near future. Users may
    // have older tokens if they close their laptop and open it up again
    // later. Users may have newer tokens if there are multiple web hosts with
    // a bit of clock skew.

    // Javascript on the client tries to keep CSRF tokens up to date, but
    // it may fail, and it doesn't run if the user closes their laptop.

    // The window during which our tokens remain valid is generally more
    // conservative than other platforms. For example, Rails uses "session
    // duration" and Django uses "forever".

    return range(-6, 1);
  }

}
