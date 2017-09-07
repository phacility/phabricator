<?php

final class PassphraseCredentialFerretField
  extends PhabricatorFerretField {

  public function getApplicationName() {
    return 'passphrase';
  }

  public function getIndexKey() {
    return 'credential';
  }

}
