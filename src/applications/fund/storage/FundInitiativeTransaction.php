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

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  public function getMailTags() {
    $tags = parent::getMailTags();

    switch ($this->getTransactionType()) {
      case FundInitiativeStatusTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_STATUS;
        break;
      case FundInitiativeBackerTransaction::TRANSACTIONTYPE:
      case FundInitiativeRefundTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_BACKER;
        break;
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }

    return $tags;
  }

}
