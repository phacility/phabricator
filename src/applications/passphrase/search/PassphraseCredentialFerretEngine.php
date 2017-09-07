<?php

final class PassphraseCredentialFerretEngine
  extends PhabricatorFerretEngine {

  public function getApplicationName() {
    return 'passphrase';
  }

  public function getScopeName() {
    return 'credential';
  }

  public function newSearchEngine() {
    return new PassphraseCredentialSearchEngine();
  }

}
