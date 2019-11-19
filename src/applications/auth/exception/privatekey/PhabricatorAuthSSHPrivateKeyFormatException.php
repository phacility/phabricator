<?php

final class PhabricatorAuthSSHPrivateKeyFormatException
  extends PhabricatorAuthSSHPrivateKeyException {

  public function isFormatException() {
    return true;
  }

  public function isPassphraseException() {
    return false;
  }

}
