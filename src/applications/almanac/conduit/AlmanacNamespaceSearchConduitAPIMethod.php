<?php

final class AlmanacNamespaceSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'almanac.namespace.search';
  }

  public function newSearchEngine() {
    return new AlmanacNamespaceSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about Almanac namespaces.');
  }

}
