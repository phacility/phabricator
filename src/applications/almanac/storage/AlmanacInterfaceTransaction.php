<?php

final class AlmanacInterfaceTransaction
  extends AlmanacModularTransaction {

  public function getApplicationTransactionType() {
    return AlmanacInterfacePHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'AlmanacInterfaceTransactionType';
  }

}
