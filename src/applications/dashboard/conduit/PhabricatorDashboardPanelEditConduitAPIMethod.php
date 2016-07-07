<?php

final class PhabricatorDashboardPanelEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'dashboard.panel.edit';
  }

  public function newEditEngine() {
    return new PhabricatorDashboardPanelEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new dashboard panel or edit an '.
      'existing one.');
  }

}
