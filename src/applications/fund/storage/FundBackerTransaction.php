<?php

final class FundBackerTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_STATUS = 'fund:backer:status';

  public function getApplicationName() {
    return 'fund';
  }

  public function getApplicationTransactionType() {
    return FundBackerPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

}
