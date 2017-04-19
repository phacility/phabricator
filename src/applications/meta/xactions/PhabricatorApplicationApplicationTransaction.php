<?php

final class PhabricatorApplicationApplicationTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'application';
  }

  public function getApplicationTransactionType() {
    return PhabricatorApplicationApplicationPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
  return new PhabricatorApplicationTransactionComment();
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorApplicationTransactionType';
  }

}
