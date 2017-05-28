<?php

final class PhabricatorFileSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'file.search';
  }

  public function newSearchEngine() {
    return new PhabricatorFileSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about files.');
  }

}
