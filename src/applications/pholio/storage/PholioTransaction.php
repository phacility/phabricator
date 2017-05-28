<?php

final class PholioTransaction extends PhabricatorModularTransaction {

  const MAILTAG_STATUS            = 'pholio-status';
  const MAILTAG_COMMENT           = 'pholio-comment';
  const MAILTAG_UPDATED           = 'pholio-updated';
  const MAILTAG_OTHER             = 'pholio-other';

  public function getApplicationName() {
    return 'pholio';
  }

  public function getBaseTransactionClass() {
    return 'PholioTransactionType';
  }

  public function getApplicationTransactionType() {
    return PholioMockPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PholioTransactionComment();
  }

  public function getApplicationTransactionViewObject() {
    return new PholioTransactionView();
  }

  public function getMailTags() {
    $tags = array();
    switch ($this->getTransactionType()) {
      case PholioMockInlineTransaction::TRANSACTIONTYPE:
      case PhabricatorTransactions::TYPE_COMMENT:
        $tags[] = self::MAILTAG_COMMENT;
        break;
      case PholioMockStatusTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_STATUS;
        break;
      case PholioMockNameTransaction::TRANSACTIONTYPE:
      case PholioMockDescriptionTransaction::TRANSACTIONTYPE:
      case PholioImageNameTransaction::TRANSACTIONTYPE:
      case PholioImageDescriptionTransaction::TRANSACTIONTYPE:
      case PholioImageSequenceTransaction::TRANSACTIONTYPE:
      case PholioImageFileTransaction::TRANSACTIONTYPE:
      case PholioImageReplaceTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_UPDATED;
        break;
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }
    return $tags;
  }

}
