<?php

final class PhabricatorUserFerretEngine
  extends PhabricatorFerretEngine {

  public function getApplicationName() {
    return 'user';
  }

  public function getScopeName() {
    return 'user';
  }

  public function newSearchEngine() {
    return new PhabricatorPeopleSearchEngine();
  }

  public function getObjectTypeRelevance() {
    // Always sort users above other documents, regardless of relevance
    // metrics. A user profile is very likely to be the best hit for a query
    // which matches a user.
    return 500;
  }

}
