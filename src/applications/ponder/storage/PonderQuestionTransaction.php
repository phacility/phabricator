<?php

final class PonderQuestionTransaction
  extends PhabricatorModularTransaction {

  const MAILTAG_DETAILS = 'question:details';
  const MAILTAG_COMMENT = 'question:comment';
  const MAILTAG_ANSWERS = 'question:answer';
  const MAILTAG_OTHER = 'question:other';

  public function getApplicationName() {
    return 'ponder';
  }

  public function getTableName() {
    return 'ponder_questiontransaction';
  }

  public function getApplicationTransactionType() {
    return PonderQuestionPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PonderQuestionTransactionComment();
  }

  public function getBaseTransactionClass() {
    return 'PonderQuestionTransactionType';
  }

  public function getMailTags() {
    $tags = parent::getMailTags();

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        $tags[] = self::MAILTAG_COMMENT;
        break;
      case PonderQuestionTitleTransaction::TRANSACTIONTYPE:
      case PonderQuestionContentTransaction::TRANSACTIONTYPE:
      case PonderQuestionStatusTransaction::TRANSACTIONTYPE:
      case PonderQuestionAnswerWikiTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_DETAILS;
        break;
      case PonderQuestionAnswerTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_ANSWERS;
        break;
      default:
        $tags[] = self::MAILTAG_OTHER;
        break;
    }
    return $tags;
  }

}
