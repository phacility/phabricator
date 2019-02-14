<?php

final class PhabricatorAuthContactNumberTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'auth';
  }

  public function getApplicationTransactionType() {
    return PhabricatorAuthContactNumberPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorAuthContactNumberTransactionType';
  }

}
