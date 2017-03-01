<?php

final class HarbormasterBuildSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'harbormaster.build.search';
  }

  public function newSearchEngine() {
    return new HarbormasterBuildSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Find out information about builds.');
  }

}
