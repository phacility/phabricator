<?php

final class PhabricatorDashboardFerretEngine
  extends PhabricatorFerretEngine {

  public function getApplicationName() {
    return 'dashboard';
  }

  public function getScopeName() {
    return 'dashboard';
  }

  public function newSearchEngine() {
    return new PhabricatorDashboardSearchEngine();
  }

}
