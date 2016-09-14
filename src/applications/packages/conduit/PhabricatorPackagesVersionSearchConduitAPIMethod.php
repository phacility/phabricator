<?php

final class PhabricatorPackagesVersionSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'packages.version.search';
  }

  public function newSearchEngine() {
    return new PhabricatorPackagesVersionSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about versions.');
  }

}
