<?php

final class AlmanacNamespaceTransaction
  extends AlmanacModularTransaction {

  public function getApplicationTransactionType() {
    return AlmanacNamespacePHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'AlmanacNamespaceTransactionType';
  }

}
