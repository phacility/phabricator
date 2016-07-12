<?php

final class ProjectSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'project.search';
  }

  public function newSearchEngine() {
    return new PhabricatorProjectSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about projects.');
  }

  protected function getCustomQueryMaps($query) {
    return array(
      'slugMap' => $query->getSlugMap(),
    );
  }

}
