<?php

final class PhabricatorRepositoryVCSPassword extends PhabricatorRepositoryDAO {

  protected $id;
  protected $userPHID;
  protected $passwordHash;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'passwordHash' => 'text128',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => array(
          'columns' => array('userPHID'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

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
      throw new Exception(pht('User does not match password user PHID!'));
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
