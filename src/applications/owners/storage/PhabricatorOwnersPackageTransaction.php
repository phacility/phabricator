<?php

final class PhabricatorOwnersPackageTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'owners';
  }

  public function getApplicationTransactionType() {
    return PhabricatorOwnersPackagePHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorOwnersPackageTransactionType';
  }

}
