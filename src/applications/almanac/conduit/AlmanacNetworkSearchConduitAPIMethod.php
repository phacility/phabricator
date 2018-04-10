<?php

final class AlmanacNetworkSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'almanac.network.search';
  }

  public function newSearchEngine() {
    return new AlmanacNetworkSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about Almanac networks.');
  }

}
