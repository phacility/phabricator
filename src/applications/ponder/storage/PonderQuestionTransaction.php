<?php

final class PonderQuestionTransaction
  extends PhabricatorApplicationTransaction {

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

