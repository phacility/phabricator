<?php

final class PassphraseCredentialFerretDocument
  extends PhabricatorFerretDocument {

  public function getApplicationName() {
    return 'passphrase';
  }

  public function getIndexKey() {
    return 'credential';
  }

}
