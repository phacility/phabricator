<?php

final class PonderQuestionTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_TITLE = 'ponder.question:question';
  const TYPE_CONTENT = 'ponder.question:content';
  const TYPE_ANSWERS = 'ponder.question:answer';

  public function getApplicationName() {
    return 'ponder';
  }

  public function getTableName() {
    return 'ponder_questiontransaction';
  }

  public function getApplicationTransactionType() {
    return PonderPHIDTypeQuestion::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PonderQuestionTransactionComment();
  }

  public function getApplicationObjectTypeName() {
    return pht('question');
  }


}

