<?php

final class PhabricatorPasteTransaction
  extends PhabricatorModularTransaction {

  const MAILTAG_CONTENT = 'paste-content';
  const MAILTAG_OTHER = 'paste-other';
  const MAILTAG_COMMENT = 'paste-comment';

  public function getApplicationName() {
    return 'paste';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPastePastePHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorPasteTransactionComment();
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorPasteTransactionType';
  }

  public function getMailTags() {
    $tags = array();
    switch ($this->getTransactionType()) {
      case PhabricatorPasteTitleTransaction::TRANSACTIONTYPE:
      case PhabricatorPasteContentTransaction::TRANSACTIONTYPE:
      case PhabricatorPasteLanguageTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_CONTENT;
        break;
      case PhabricatorTransactions::TYPE_COMMENT:
        $tags[] = self::MAILTAG_COMMENT;
        break;
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }
    return $tags;
  }

}
