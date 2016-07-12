<?php

final class FundBackerTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_STATUS = 'fund:backer:status';
  const TYPE_REFUND = 'fund:backer:refund';

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
