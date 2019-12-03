<?php

abstract class PhabricatorAuthSSHPrivateKeyException
  extends Exception {

  abstract public function isFormatException();
  abstract public function isPassphraseException();

}
