<?php

final class PassphrasePasswordCredentialType
  extends PassphraseCredentialType {

  const CREDENTIAL_TYPE = 'password';
  const PROVIDES_TYPE = 'provides/password';

  public function getCredentialType() {
    return self::CREDENTIAL_TYPE;
  }

  public function getProvidesType() {
    return self::PROVIDES_TYPE;
  }

  public function getCredentialTypeName() {
    return pht('Password');
  }

  public function getCredentialTypeDescription() {
    return pht('Store a plaintext password.');
  }

  public function getSecretLabel() {
    return pht('Password');
  }

  public function newSecretControl() {
    return id(new AphrontFormPasswordControl())
      ->setDisableAutocomplete(true);
  }

}
