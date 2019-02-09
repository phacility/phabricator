<?php

final class PhabricatorAuthPasswordTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'auth';
  }

  public function getApplicationTransactionType() {
    return PhabricatorAuthPasswordPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorAuthPasswordTransactionType';
  }
}
