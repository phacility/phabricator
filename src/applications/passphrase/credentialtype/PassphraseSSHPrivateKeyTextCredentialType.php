<?php

final class PassphraseSSHPrivateKeyTextCredentialType
  extends PassphraseSSHPrivateKeyCredentialType {

  const CREDENTIAL_TYPE = 'ssh-key-text';

  public function getCredentialType() {
    return self::CREDENTIAL_TYPE;
  }

  public function getCredentialTypeName() {
    return pht('SSH Private Key');
  }

  public function getCredentialTypeDescription() {
    return pht('Store the plaintext of an SSH private key.');
  }

  public function getSecretLabel() {
    return pht('Private Key');
  }

  public function shouldShowPasswordField() {
    return true;
  }

  public function getPasswordLabel() {
    return pht('Password for Key');
  }

}
