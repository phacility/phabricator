<?php

final class PhortuneAccountEmailTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'phortune';
  }

  public function getApplicationTransactionType() {
    return PhortuneAccountEmailPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhortuneAccountEmailTransactionType';
  }

}
