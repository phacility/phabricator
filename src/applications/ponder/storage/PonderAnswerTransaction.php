<?php

final class PonderAnswerTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'ponder';
  }

  public function getTableName() {
    return 'ponder_answertransaction';
  }

  public function getApplicationTransactionType() {
    return PonderAnswerPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PonderAnswerTransactionComment();
  }

  public function getBaseTransactionClass() {
    return 'PonderAnswerTransactionType';
  }

  public function getMailTags() {
    $tags = parent::getMailTags();
    $tags[] = PonderQuestionTransaction::MAILTAG_OTHER;

    return $tags;
  }

}
