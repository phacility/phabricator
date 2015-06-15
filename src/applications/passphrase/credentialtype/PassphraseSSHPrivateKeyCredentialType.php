<?php

abstract class PassphraseSSHPrivateKeyCredentialType
  extends PassphraseCredentialType {

  const PROVIDES_TYPE = 'provides/ssh-key-file';

  final public function getProvidesType() {
    return self::PROVIDES_TYPE;
  }

  public function hasPublicKey() {
    return true;
  }

  public function getPublicKey(
    PhabricatorUser $viewer,
    PassphraseCredential $credential) {

    $key = PassphraseSSHKey::loadFromPHID($credential->getPHID(), $viewer);
    $file = $key->getKeyfileEnvelope();

    list($stdout) = execx('ssh-keygen -y -f %P', $file);

    return $stdout;
  }

}
