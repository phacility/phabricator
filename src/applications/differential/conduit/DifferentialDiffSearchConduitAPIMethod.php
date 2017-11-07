<?php

final class DifferentialDiffSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'differential.diff.search';
  }

  public function newSearchEngine() {
    return new DifferentialDiffSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about diffs.');
  }

}
