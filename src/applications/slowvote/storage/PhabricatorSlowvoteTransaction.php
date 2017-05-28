<?php

final class PhabricatorSlowvoteTransaction
  extends PhabricatorModularTransaction {

  const MAILTAG_DETAILS = 'vote:details';
  const MAILTAG_RESPONSES = 'vote:responses';
  const MAILTAG_OTHER  = 'vote:vote';

  public function getApplicationName() {
    return 'slowvote';
  }

  public function getApplicationTransactionType() {
    return PhabricatorSlowvotePollPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorSlowvoteTransactionComment();
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorSlowvoteTransactionType';
  }

  public function getMailTags() {
    $tags = parent::getMailTags();

    switch ($this->getTransactionType()) {
      case PhabricatorSlowvoteQuestionTransaction::TRANSACTIONTYPE:
      case PhabricatorSlowvoteDescriptionTransaction::TRANSACTIONTYPE:
      case PhabricatorSlowvoteShuffleTransaction::TRANSACTIONTYPE:
      case PhabricatorSlowvoteCloseTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_DETAILS;
        break;
      case PhabricatorSlowvoteResponsesTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_RESPONSES;
        break;
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }

    return $tags;
  }


}
