<?php

final class HarbormasterBuildLogSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'harbormaster.log.search';
  }

  public function newSearchEngine() {
    return new HarbormasterBuildLogSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Find out information about build logs.');
  }

}
