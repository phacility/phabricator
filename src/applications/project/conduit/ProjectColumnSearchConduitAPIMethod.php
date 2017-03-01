<?php

final class ProjectColumnSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'project.column.search';
  }

  public function newSearchEngine() {
    return new PhabricatorProjectColumnSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about workboard columns.');
  }

}
