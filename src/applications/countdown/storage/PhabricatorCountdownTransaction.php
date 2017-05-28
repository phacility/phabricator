<?php

final class PhabricatorCountdownTransaction
  extends PhabricatorModularTransaction {

  const MAILTAG_DETAILS = 'countdown:details';
  const MAILTAG_COMMENT = 'countdown:comment';
  const MAILTAG_OTHER  = 'countdown:other';

  public function getApplicationName() {
    return 'countdown';
  }

  public function getApplicationTransactionType() {
    return PhabricatorCountdownCountdownPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorCountdownTransactionComment();
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorCountdownTransactionType';
  }

  public function getMailTags() {
    $tags = parent::getMailTags();

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        $tags[] = self::MAILTAG_COMMENT;
        break;
      case PhabricatorCountdownTitleTransaction::TRANSACTIONTYPE:
      case PhabricatorCountdownEpochTransaction::TRANSACTIONTYPE:
      case PhabricatorCountdownDescriptionTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_DETAILS;
        break;
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }

    return $tags;
  }
}
