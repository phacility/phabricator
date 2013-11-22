<?php

final class PassphraseCredentialTypeSSHPrivateKeyText
  extends PassphraseCredentialTypeSSHPrivateKey {

  public function getCredentialType() {
    return 'ssh-key-text';
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

}
