<?php

final class PasteSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'paste.search';
  }

  public function newSearchEngine() {
    return new PhabricatorPasteSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about pastes.');
  }

}
