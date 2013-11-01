<?php

final class PhabricatorRepositoryVCSPassword extends PhabricatorRepositoryDAO {

  protected $id;
  protected $userPHID;
  protected $passwordHash;

  public function setPassword(
    PhutilOpaqueEnvelope $password,
    PhabricatorUser $user) {
    return $this->setPasswordHash($this->hashPassword($password, $user));
  }

  public function comparePassword(
    PhutilOpaqueEnvelope $password,
    PhabricatorUser $user) {

    $hash = $this->hashPassword($password, $user);
    return ($hash == $this->getPasswordHash());
  }

  private function hashPassword(
    PhutilOpaqueEnvelope $password,
    PhabricatorUser $user) {

    if ($user->getPHID() != $this->getUserPHID()) {
      throw new Exception("User does not match password user PHID!");
    }

    return PhabricatorHash::digestPassword($password, $user->getPHID());
  }

}
