<?php

abstract class PassphraseCredentialTypeSSHPrivateKey
  extends PassphraseCredentialType {

  final public function getProvidesType() {
    return 'provides/ssh-key-file';
  }

}
