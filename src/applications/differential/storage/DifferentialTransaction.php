<?php

final class DifferentialTransaction extends PhabricatorApplicationTransaction {

  public function getApplicationName() {
    return 'differential';
  }

  public function getApplicationTransactionType() {
    return DifferentialPHIDTypeRevision::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new DifferentialTransactionComment();
  }

  public function getApplicationObjectTypeName() {
    return pht('revision');
  }

}
