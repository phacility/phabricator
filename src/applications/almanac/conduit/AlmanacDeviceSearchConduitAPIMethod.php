<?php

final class AlmanacDeviceSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'almanac.device.search';
  }

  public function newSearchEngine() {
    return new AlmanacDeviceSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about Almanac devices.');
  }

}
