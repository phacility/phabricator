<?php

abstract class PassphraseCredentialTypeSSHPrivateKey
  extends PassphraseCredentialType {

  const PROVIDES_TYPE = 'provides/ssh-key-file';

  final public function getProvidesType() {
    return self::PROVIDES_TYPE;
  }

}
