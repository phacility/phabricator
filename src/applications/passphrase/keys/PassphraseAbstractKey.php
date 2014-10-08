<?php

abstract class PassphraseAbstractKey extends Phobject {

  private $credential;

  protected function requireCredential() {
    if (!$this->credential) {
      throw new Exception(pht('Credential is required!'));
    }
    return $this->credential;
  }

  private function loadCredential(
    $phid,
    PhabricatorUser $viewer) {

    $credential = id(new PassphraseCredentialQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($phid))
      ->needSecrets(true)
      ->executeOne();

    if (!$credential) {
      throw new Exception(pht('Failed to load credential "%s"!', $phid));
    }

    return $credential;
  }

  private function validateCredential(
    PassphraseCredential $credential,
    $provides_type) {

    $type = $credential->getCredentialTypeImplementation();

    if (!$type) {
      throw new Exception(
        pht(
          'Credential "%s" is of unknown type "%s"!',
          'K'.$credential->getID(),
          $credential->getCredentialType()));
    }

    if ($type->getProvidesType() !== $provides_type) {
      throw new Exception(
        pht(
          'Credential "%s" must provide "%s", but provides "%s"!',
          'K'.$credential->getID(),
          $provides_type,
          $type->getProvidesType()));
    }
  }

  protected function loadAndValidateFromPHID(
    $phid,
    PhabricatorUser $viewer,
    $type) {

    $credential = $this->loadCredential($phid, $viewer);

    $this->validateCredential($credential, $type);

    $this->credential = $credential;

    return $this;
  }

  public function getUsernameEnvelope() {
    $credential = $this->requireCredential();
    return new PhutilOpaqueEnvelope($credential->getUsername());
  }

}
