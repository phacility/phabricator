<?php

final class HarbormasterTargetSearchAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'harbormaster.target.search';
  }

  public function newSearchEngine() {
    return new HarbormasterBuildTargetSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Retrieve information about Harbormaster build targets.');
  }

}
