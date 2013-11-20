<?php

final class PassphraseCredentialTypePassword
  extends PassphraseCredentialType {

  public function getCredentialType() {
    return 'password';
  }

  public function getProvidesType() {
    return 'provides/password';
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
    return new AphrontFormPasswordControl();
  }

}
