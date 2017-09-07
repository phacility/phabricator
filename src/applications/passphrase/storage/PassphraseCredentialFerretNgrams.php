<?php

final class PassphraseCredentialFerretNgrams
  extends PhabricatorFerretNgrams {

  public function getApplicationName() {
    return 'passphrase';
  }

  public function getIndexKey() {
    return 'credential';
  }

}
