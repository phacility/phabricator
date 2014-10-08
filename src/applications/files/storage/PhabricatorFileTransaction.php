<?php

final class PhabricatorFileTransaction
  extends PhabricatorApplicationTransaction {

  public function getApplicationName() {
    return 'file';
  }

  public function getApplicationTransactionType() {
    return PhabricatorFileFilePHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorFileTransactionComment();
  }

}
