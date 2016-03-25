<?php

final class PassphraseTokenCredentialType
  extends PassphraseCredentialType {

  const CREDENTIAL_TYPE = 'token';
  const PROVIDES_TYPE = 'provides/token';

  public function getCredentialType() {
    return self::CREDENTIAL_TYPE;
  }

  public function getProvidesType() {
    return self::PROVIDES_TYPE;
  }

  public function getCredentialTypeName() {
    return pht('Token');
  }

  public function getCredentialTypeDescription() {
    return pht('Store an API token.');
  }

  public function getSecretLabel() {
    return pht('Token');
  }

  public function newSecretControl() {
    return id(new AphrontFormTextControl());
  }

  public function shouldRequireUsername() {
    return false;
  }

}
