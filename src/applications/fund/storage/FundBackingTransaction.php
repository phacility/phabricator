<?php

final class FundBackingTransaction
  extends PhabricatorApplicationTransaction {

  public function getApplicationName() {
    return 'fund';
  }

  public function getApplicationTransactionType() {
    return FundBackingPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

}
