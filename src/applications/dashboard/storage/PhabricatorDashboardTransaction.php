<?php

final class PhabricatorDashboardTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'dashboard';
  }

  public function getApplicationTransactionType() {
    return PhabricatorDashboardDashboardPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorDashboardTransactionType';
  }

}
