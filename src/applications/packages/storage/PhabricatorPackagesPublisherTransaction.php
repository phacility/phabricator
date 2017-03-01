<?php

final class PhabricatorPackagesPublisherTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'packages';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPackagesPublisherPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorPackagesPublisherTransactionType';
  }

}
