<?php

final class PhrictionDocumentFerretEngine
  extends PhabricatorFerretEngine {

  public function getApplicationName() {
    return 'phriction';
  }

  public function getScopeName() {
    return 'document';
  }

  public function newSearchEngine() {
    return new PhrictionDocumentSearchEngine();
  }

}
