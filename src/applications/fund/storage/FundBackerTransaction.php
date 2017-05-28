<?php

final class FundBackerTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'fund';
  }

  public function getApplicationTransactionType() {
    return FundBackerPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getBaseTransactionClass() {
    return 'FundBackerTransactionType';
  }

}
