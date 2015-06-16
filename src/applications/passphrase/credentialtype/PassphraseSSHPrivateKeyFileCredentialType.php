<?php

final class PassphraseSSHPrivateKeyFileCredentialType
  extends PassphraseSSHPrivateKeyCredentialType {

  const CREDENTIAL_TYPE = 'ssh-key-file';

  public function getCredentialType() {
    return self::CREDENTIAL_TYPE;
  }

  public function getCredentialTypeName() {
    return pht('SSH Private Key File');
  }

  public function getCredentialTypeDescription() {
    return pht('Store the path on disk to an SSH private key.');
  }

  public function getSecretLabel() {
    return pht('Path On Disk');
  }

  public function newSecretControl() {
    return new AphrontFormTextControl();
  }

  public function isCreateable() {
    // This credential type exists to support historic repository configuration.
    // We don't support creating new credentials with this type, since it does
    // not scale and managing passwords is much more difficult than if we have
    // the key text.
    return false;
  }

  public function hasPublicKey() {
    // These have public keys, but they'd be cumbersome to extract.
    return true;
  }

}
