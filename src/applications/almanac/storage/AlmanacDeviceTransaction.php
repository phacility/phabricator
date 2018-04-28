<?php

final class AlmanacDeviceTransaction
  extends AlmanacModularTransaction {

  public function getApplicationTransactionType() {
    return AlmanacDevicePHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'AlmanacDeviceTransactionType';
  }

}
