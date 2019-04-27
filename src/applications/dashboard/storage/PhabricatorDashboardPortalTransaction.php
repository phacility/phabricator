<?php

final class PhabricatorDashboardPortalTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'dashboard';
  }

  public function getApplicationTransactionType() {
    return PhabricatorDashboardPortalPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorDashboardPortalTransactionType';
  }

}
