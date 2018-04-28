<?php

final class AlmanacInterfaceSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'almanac.interface.search';
  }

  public function newSearchEngine() {
    return new AlmanacInterfaceSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about Almanac interfaces.');
  }

}
