<?php

final class PhortuneSubscriptionTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'phortune';
  }

  public function getApplicationTransactionType() {
    return PhortuneSubscriptionPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhortuneSubscriptionTransactionType';
  }

}
