<?php

final class PassphraseSSHKey extends PassphraseAbstractKey {

  private $keyFile;

  public static function loadFromPHID($phid, PhabricatorUser $viewer) {
    $key = new PassphraseSSHKey();
    return $key->loadAndValidateFromPHID(
      $phid,
      $viewer,
      PassphraseCredentialTypeSSHPrivateKey::PROVIDES_TYPE);
  }

  public function getKeyfileEnvelope() {
    $credential = $this->requireCredential();

    $text_type = PassphraseCredentialTypeSSHPrivateKeyText::CREDENTIAL_TYPE;
    if ($credential->getCredentialType() == $text_type) {
      // If the credential stores key text, write it out to a temporary file
      // so we can pass it to `ssh`.
      if (!$this->keyFile) {
        $temporary_file = new TempFile('passphrase-ssh-key');

        Filesystem::changePermissions($temporary_file, 0600);

        Filesystem::writeFile(
          $temporary_file,
          $credential->getSecret()->openEnvelope());

        $this->keyFile = $temporary_file;
      }

      return new PhutilOpaqueEnvelope((string)$this->keyFile);
    }

    return $credential->getSecret();
  }

}
