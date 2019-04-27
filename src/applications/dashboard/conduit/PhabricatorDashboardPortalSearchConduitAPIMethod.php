<?php

final class PhabricatorDashboardPortalSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'portal.search';
  }

  public function newSearchEngine() {
    return new PhabricatorDashboardPortalSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about portals.');
  }

}
