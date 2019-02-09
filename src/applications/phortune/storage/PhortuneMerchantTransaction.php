<?php

final class PhortuneMerchantTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'phortune';
  }

  public function getApplicationTransactionType() {
    return PhortuneMerchantPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhortuneMerchantTransactionType';
  }

}
