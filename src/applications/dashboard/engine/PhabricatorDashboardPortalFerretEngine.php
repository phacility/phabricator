<?php

final class PhabricatorDashboardPortalFerretEngine
  extends PhabricatorFerretEngine {

  public function getApplicationName() {
    return 'dashboard';
  }

  public function getScopeName() {
    return 'portal';
  }

  public function newSearchEngine() {
    return new PhabricatorDashboardPortalSearchEngine();
  }

}
