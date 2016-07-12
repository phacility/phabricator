<?php

final class PhabricatorBadgesSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'badges.search';
  }

  public function newSearchEngine() {
    return new PhabricatorBadgesSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about badges.');
  }

}
