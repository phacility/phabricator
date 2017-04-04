<?php

final class PhabricatorFileTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'file';
  }

  public function getApplicationTransactionType() {
    return PhabricatorFileFilePHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorFileTransactionComment();
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorFileTransactionType';
  }

}
