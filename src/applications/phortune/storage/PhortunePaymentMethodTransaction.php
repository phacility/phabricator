<?php

final class PhortunePaymentMethodTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'phortune';
  }

  public function getApplicationTransactionType() {
    return PhortunePaymentMethodPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhortunePaymentMethodTransactionType';
  }

}
