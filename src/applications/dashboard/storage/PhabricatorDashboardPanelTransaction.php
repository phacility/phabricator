<?php

final class PhabricatorDashboardPanelTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'dashboard';
  }

  public function getApplicationTransactionType() {
    return PhabricatorDashboardPanelPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorDashboardPanelTransactionType';
  }

}
