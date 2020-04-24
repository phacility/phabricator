<?php

final class PhabricatorPasteFerretEngine
  extends PhabricatorFerretEngine {

  public function getApplicationName() {
    return 'paste';
  }

  public function getScopeName() {
    return 'paste';
  }

  public function newSearchEngine() {
    return new PhabricatorPasteSearchEngine();
  }

}
