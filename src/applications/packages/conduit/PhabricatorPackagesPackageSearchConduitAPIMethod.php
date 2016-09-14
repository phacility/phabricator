<?php

final class PhabricatorPackagesPackageSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'packages.package.search';
  }

  public function newSearchEngine() {
    return new PhabricatorPackagesPackageSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about packages.');
  }

}
