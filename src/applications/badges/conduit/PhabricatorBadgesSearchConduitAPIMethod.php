<?php

final class PhabricatorBadgesSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'badge.search';
  }

  public function newSearchEngine() {
    return new PhabricatorBadgesSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about badges.');
  }

}
