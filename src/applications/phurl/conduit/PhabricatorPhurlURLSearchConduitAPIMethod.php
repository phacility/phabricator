<?php

final class PhabricatorPhurlURLSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'phurls.search';
  }

  public function newSearchEngine() {
    return new PhabricatorPhurlURLSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about Phurl URLS.');
  }

}
