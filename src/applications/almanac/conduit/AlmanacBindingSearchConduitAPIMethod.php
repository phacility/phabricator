<?php

final class AlmanacBindingSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'almanac.binding.search';
  }

  public function newSearchEngine() {
    return new AlmanacBindingSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about Almanac bindings.');
  }

}
