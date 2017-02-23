<?php

final class PhabricatorBadgesTransaction
  extends PhabricatorModularTransaction {

  const MAILTAG_DETAILS = 'badges:details';
  const MAILTAG_COMMENT = 'badges:comment';
  const MAILTAG_OTHER  = 'badges:other';

  public function getApplicationName() {
    return 'badges';
  }

  public function getApplicationTransactionType() {
    return PhabricatorBadgesPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorBadgesTransactionComment();
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorBadgesBadgeTransactionType';
  }

  public function getMailTags() {
    $tags = parent::getMailTags();

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        $tags[] = self::MAILTAG_COMMENT;
        break;
      case PhabricatorBadgesBadgeNameTransaction::TRANSACTIONTYPE:
      case PhabricatorBadgesBadgeDescriptionTransaction::TRANSACTIONTYPE:
      case PhabricatorBadgesBadgeFlavorTransaction::TRANSACTIONTYPE:
      case PhabricatorBadgesBadgeIconTransaction::TRANSACTIONTYPE:
      case PhabricatorBadgesBadgeStatusTransaction::TRANSACTIONTYPE:
      case PhabricatorBadgesBadgeQualityTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_DETAILS;
        break;
      case PhabricatorBadgesBadgeAwardTransaction::TRANSACTIONTYPE:
      case PhabricatorBadgesBadgeRevokeTransaction::TRANSACTIONTYPE:
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }
    return $tags;
  }

}
