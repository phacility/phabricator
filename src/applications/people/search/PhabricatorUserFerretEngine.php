<?php

final class PhabricatorUserFerretEngine
  extends PhabricatorFerretEngine {

  public function newNgramsObject() {
    return new PhabricatorUserFerretNgrams();
  }

  public function newDocumentObject() {
    return new PhabricatorUserFerretDocument();
  }

  public function newFieldObject() {
    return new PhabricatorUserFerretField();
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
