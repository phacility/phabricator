<?php

final class AlmanacServiceTransaction
  extends AlmanacModularTransaction {

  public function getApplicationTransactionType() {
    return AlmanacServicePHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'AlmanacServiceTransactionType';
  }

}
