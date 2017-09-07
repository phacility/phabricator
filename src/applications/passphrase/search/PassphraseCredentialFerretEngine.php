<?php

final class PassphraseCredentialFerretEngine
  extends PhabricatorFerretEngine {

  public function newNgramsObject() {
    return new PassphraseCredentialFerretNgrams();
  }

  public function newDocumentObject() {
    return new PassphraseCredentialFerretDocument();
  }

  public function newFieldObject() {
    return new PassphraseCredentialFerretField();
  }

  public function newSearchEngine() {
    return new PassphraseCredentialSearchEngine();
  }

}
