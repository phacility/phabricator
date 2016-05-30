<?php

final class UserSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'user.search';
  }

  public function newSearchEngine() {
    return new PhabricatorPeopleSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about users.');
  }

}
