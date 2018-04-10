<?php

final class AlmanacDeviceTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'almanac';
  }

  public function getApplicationTransactionType() {
    return AlmanacDevicePHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getBaseTransactionClass() {
    return 'AlmanacDeviceTransactionType';
  }

}
