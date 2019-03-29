<?php

final class PhabricatorDashboardPortalEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'portal.edit';
  }

  public function newEditEngine() {
    return new PhabricatorDashboardPortalEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new portal or edit an existing one.');
  }

}
