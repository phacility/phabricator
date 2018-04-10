<?php

final class AlmanacInterfaceTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'almanac';
  }

  public function getApplicationTransactionType() {
    return AlmanacInterfacePHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getBaseTransactionClass() {
    return 'AlmanacInterfaceTransactionType';
  }

}
