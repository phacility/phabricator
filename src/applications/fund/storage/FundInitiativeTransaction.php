<?php

final class FundInitiativeTransaction
  extends PhabricatorModularTransaction {

  const MAILTAG_BACKER = 'fund.backer';
  const MAILTAG_STATUS = 'fund.status';
  const MAILTAG_OTHER  = 'fund.other';

  const PROPERTY_AMOUNT = 'fund.amount';
  const PROPERTY_BACKER = 'fund.backer';

  public function getApplicationName() {
    return 'fund';
  }

  public function getApplicationTransactionType() {
    return FundInitiativePHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new FundInitiativeTransactionComment();
  }

  public function getBaseTransactionClass() {
    return 'FundInitiativeTransactionType';
  }

  public function getMailTags() {
    $tags = parent::getMailTags();

    switch ($this->getTransactionType()) {
      case self::TYPE_STATUS:
        $tags[] = self::MAILTAG_STATUS;
        break;
      case self::TYPE_BACKER:
      case self::TYPE_REFUND:
        $tags[] = self::MAILTAG_BACKER;
        break;
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }

    return $tags;
  }

}
