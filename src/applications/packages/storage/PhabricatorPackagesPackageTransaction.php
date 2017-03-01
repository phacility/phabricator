<?php

final class PhabricatorPackagesPackageTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'packages';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPackagesPackagePHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorPackagesPackageTransactionType';
  }

}
