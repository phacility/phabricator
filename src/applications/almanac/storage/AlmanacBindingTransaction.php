<?php

final class AlmanacBindingTransaction
  extends AlmanacModularTransaction {

  public function getApplicationTransactionType() {
    return AlmanacBindingPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'AlmanacBindingTransactionType';
  }

}
