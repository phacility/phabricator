<?php

final class PhabricatorRoleColumnTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'role';
  }

  public function getApplicationTransactionType() {
    return PhabricatorRoleColumnPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorRoleColumnTransactionType';
  }

}
