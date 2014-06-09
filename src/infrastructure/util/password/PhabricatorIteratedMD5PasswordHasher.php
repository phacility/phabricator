<?php

final class PhabricatorIteratedMD5PasswordHasher
  extends PhabricatorPasswordHasher {

  public function getHumanReadableName() {
    return pht('Iterated MD5');
  }

  public function getHashName() {
    return 'md5';
  }

  public function getHashLength() {
    return 32;
  }

  public function canHashPasswords() {
    return function_exists('md5');
  }

  public function getInstallInstructions() {
    // This should always be available, but do something useful anyway.
    return pht('To use iterated MD5, make the md5() function available.');
  }

  public function getStrength() {
    return 1.0;
  }

  public function getHumanReadableStrength() {
    return pht('Okay');
  }

  protected function getPasswordHash(PhutilOpaqueEnvelope $envelope) {
    $raw_input = $envelope->openEnvelope();

    $hash = $raw_input;
    for ($ii = 0; $ii < 1000; $ii++) {
      $hash = md5($hash);
    }

    return new PhutilOpaqueEnvelope($hash);
  }

}
