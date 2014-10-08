<?php

final class PhabricatorBcryptPasswordHasher
  extends PhabricatorPasswordHasher {

  public function getHumanReadableName() {
    return pht('bcrypt');
  }

  public function getHashName() {
    return 'bcrypt';
  }

  public function getHashLength() {
    return 60;
  }

  public function canHashPasswords() {
    return function_exists('password_hash');
  }

  public function getInstallInstructions() {
    return pht('Upgrade to PHP 5.5.0 or newer.');
  }

  public function getStrength() {
    return 2.0;
  }

  public function getHumanReadableStrength() {
    return pht('Good');
  }

  protected function getPasswordHash(PhutilOpaqueEnvelope $envelope) {
    $raw_input = $envelope->openEnvelope();

    $options = array(
      'cost' => $this->getBcryptCost(),
    );

    $raw_hash = password_hash($raw_input, PASSWORD_BCRYPT, $options);

    return new PhutilOpaqueEnvelope($raw_hash);
  }

  protected function verifyPassword(
    PhutilOpaqueEnvelope $password,
    PhutilOpaqueEnvelope $hash) {
    return password_verify($password->openEnvelope(), $hash->openEnvelope());
  }

  protected function canUpgradeInternalHash(PhutilOpaqueEnvelope $hash) {
    $info = password_get_info($hash->openEnvelope());

    // NOTE: If the costs don't match -- even if the new cost is lower than
    // the old cost -- count this as an upgrade. This allows costs to be
    // adjusted down and hashing to be migrated toward the new cost if costs
    // are ever configured too high for some reason.

    $cost = idx($info['options'], 'cost');
    if ($cost != $this->getBcryptCost()) {
      return true;
    }

    return false;
  }

  private function getBcryptCost() {
    // NOTE: The default cost is "10", but my laptop can do a hash of cost
    // "12" in about 300ms. Since server hardware is often virtualized or old,
    // just split the difference.
    return 11;
  }

}
