<?php

final class PassphraseNoteCredentialType
  extends PassphraseCredentialType {

  const CREDENTIAL_TYPE = 'note';
  const PROVIDES_TYPE = 'provides/note';

  public function getCredentialType() {
    return self::CREDENTIAL_TYPE;
  }

  public function getProvidesType() {
    return self::PROVIDES_TYPE;
  }

  public function getCredentialTypeName() {
    return pht('Note');
  }

  public function getCredentialTypeDescription() {
    return pht('Store a plaintext note.');
  }

  public function getSecretLabel() {
    return pht('Note');
  }

  public function newSecretControl() {
    return id(new AphrontFormTextAreaControl());
  }

  public function shouldRequireUsername() {
    return false;
  }

}
