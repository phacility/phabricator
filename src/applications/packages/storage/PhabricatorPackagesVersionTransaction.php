<?php

final class PhabricatorPackagesVersionTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'packages';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPackagesVersionPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorPackagesVersionTransactionType';
  }

}
