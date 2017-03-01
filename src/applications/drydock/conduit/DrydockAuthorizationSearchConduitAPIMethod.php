<?php

final class DrydockAuthorizationSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'drydock.authorization.search';
  }

  public function newSearchEngine() {
    return new DrydockAuthorizationSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Retrieve information about Drydock authorizations.');
  }

}
