<?php

final class PhabricatorProjectColumnTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'project';
  }

  public function getApplicationTransactionType() {
    return PhabricatorProjectColumnPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorProjectColumnTransactionType';
  }

}
