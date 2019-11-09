<?php

final class PhabricatorAuthSSHPrivateKeyUnknownException
  extends PhabricatorAuthSSHPrivateKeyException {

  public function isFormatException() {
    return true;
  }

  public function isPassphraseException() {
    return true;
  }

}
