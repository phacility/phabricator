<?php

final class PhabricatorUserTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'user';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPeopleUserPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorUserTransactionType';
  }

}
