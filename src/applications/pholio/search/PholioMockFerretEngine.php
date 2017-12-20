<?php

final class PholioMockFerretEngine
  extends PhabricatorFerretEngine {

  public function getApplicationName() {
    return 'pholio';
  }

  public function getScopeName() {
    return 'mock';
  }

  public function newSearchEngine() {
    return new PholioMockSearchEngine();
  }

}
