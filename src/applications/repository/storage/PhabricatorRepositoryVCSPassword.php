<?php

final class PhabricatorRepositoryVCSPassword extends PhabricatorRepositoryDAO {

  protected $id;
  protected $userPHID;
  protected $passwordHash;

  public function setPassword(
    PhutilOpaqueEnvelope $password,
    PhabricatorUser $user) {
    $hash_envelope = $this->hashPassword($password, $user);
    return $this->setPasswordHash($hash_envelope->openEnvelope());
  }

  public function comparePassword(
    PhutilOpaqueEnvelope $password,
    PhabricatorUser $user) {

    return PhabricatorPasswordHasher::comparePassword(
      $this->getPasswordHashInput($password, $user),
      new PhutilOpaqueEnvelope($this->getPasswordHash()));
  }

  private function getPasswordHashInput(
    PhutilOpaqueEnvelope $password,
    PhabricatorUser $user) {
    if ($user->getPHID() != $this->getUserPHID()) {
      throw new Exception("User does not match password user PHID!");
    }

    $raw_input = PhabricatorHash::digestPassword($password, $user->getPHID());
    return new PhutilOpaqueEnvelope($raw_input);
  }

  private function hashPassword(
    PhutilOpaqueEnvelope $password,
    PhabricatorUser $user) {

    $input_envelope = $this->getPasswordHashInput($password, $user);

    $best_hasher = PhabricatorPasswordHasher::getBestHasher();
    return $best_hasher->getPasswordHashForStorage($input_envelope);
  }

}
