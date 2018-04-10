<?php

final class AlmanacBindingTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'almanac';
  }

  public function getApplicationTransactionType() {
    return AlmanacBindingPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getBaseTransactionClass() {
    return 'AlmanacBindingTransactionType';
  }

}
