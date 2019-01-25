<?php

final class PhabricatorCredentialEditField
  extends PhabricatorEditField {

  private $credentialType;
  private $credentials;

  public function setCredentialType($credential_type) {
    $this->credentialType = $credential_type;
    return $this;
  }

  public function getCredentialType() {
    return $this->credentialType;
  }

  public function setCredentials(array $credentials) {
    $this->credentials = $credentials;
    return $this;
  }

  public function getCredentials() {
    return $this->credentials;
  }

  protected function newControl() {
    $control = id(new PassphraseCredentialControl())
      ->setCredentialType($this->getCredentialType())
      ->setOptions($this->getCredentials());

    return $control;
  }

  protected function newHTTPParameterType() {
    return new AphrontPHIDHTTPParameterType();
  }

  protected function newConduitParameterType() {
    return new ConduitPHIDParameterType();
  }

}
