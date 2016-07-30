<?php

final class PhabricatorPackagesPublisherSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'packages.publisher.search';
  }

  public function newSearchEngine() {
    return new PhabricatorPackagesPublisherSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about publishers.');
  }

}
