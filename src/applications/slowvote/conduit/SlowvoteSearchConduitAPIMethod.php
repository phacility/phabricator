<?php

final class SlowvoteSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'slowvote.poll.search';
  }

  public function newSearchEngine() {
    return new PhabricatorSlowvoteSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about polls.');
  }

}
