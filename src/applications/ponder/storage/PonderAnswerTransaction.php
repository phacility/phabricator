<?php

final class PonderAnswerTransaction
  extends PhabricatorApplicationTransaction {

  public function getApplicationName() {
    return 'ponder';
  }

  public function getTableName() {
    return 'ponder_answertransaction';
  }

  public function getApplicationTransactionType() {
    return PonderPHIDTypeAnswer::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PonderAnswerTransactionComment();
  }

  public function getApplicationObjectTypeName() {
    return pht('answer');
  }


}

