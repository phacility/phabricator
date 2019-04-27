<?php

final class PhabricatorDashboardPanelFerretEngine
  extends PhabricatorFerretEngine {

  public function getApplicationName() {
    return 'dashboard';
  }

  public function getScopeName() {
    return 'panel';
  }

  public function newSearchEngine() {
    return new PhabricatorDashboardPanelSearchEngine();
  }

}
