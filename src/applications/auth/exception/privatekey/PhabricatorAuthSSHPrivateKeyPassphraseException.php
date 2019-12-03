<?php

abstract class PhabricatorAuthSSHPrivateKeyPassphraseException
  extends PhabricatorAuthSSHPrivateKeyException {

  final public function isFormatException() {
    return false;
  }

  final public function isPassphraseException() {
    return true;
  }

}
