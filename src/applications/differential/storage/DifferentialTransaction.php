<?php

final class DifferentialTransaction extends PhabricatorApplicationTransaction {

  public function getApplicationName() {
    return 'differential';
  }

  public function getApplicationTransactionType() {
    return PhabricatorPHIDConstants::PHID_TYPE_DREV;
  }

  public function getApplicationTransactionCommentObject() {
    return new DifferentialTransactionComment();
  }

  public function getApplicationObjectTypeName() {
    return pht('revision');
  }

}
