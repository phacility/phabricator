<?php

final class OwnersSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'owners.search';
  }

  public function newSearchEngine() {
    return new PhabricatorOwnersPackageSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about Owners packages.');
  }

}
