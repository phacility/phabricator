<?php

final class CountdownSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'countdown.search';
  }

  public function newSearchEngine() {
    return new PhabricatorCountdownSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about countdowns.');
  }

}
