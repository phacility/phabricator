<?php

final class AlmanacServiceTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'almanac';
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getApplicationTransactionType() {
    return AlmanacServicePHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'AlmanacServiceTransactionType';
  }

}
