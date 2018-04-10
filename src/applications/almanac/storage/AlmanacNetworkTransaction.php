<?php

final class AlmanacNetworkTransaction
  extends AlmanacModularTransaction {

  public function getApplicationTransactionType() {
    return AlmanacNetworkPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'AlmanacNetworkTransactionType';
  }

}
