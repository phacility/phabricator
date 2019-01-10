<?php

final class HarbormasterBuildPlanSearchAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'harbormaster.buildplan.search';
  }

  public function newSearchEngine() {
    return new HarbormasterBuildPlanSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Retrieve information about Harbormaster build plans.');
  }

}
