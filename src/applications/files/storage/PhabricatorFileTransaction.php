<?php

/**
 * @group file
 */
final class PhabricatorFileTransaction
  extends PhabricatorApplicationTransaction {

  public function getApplicationName() {
    return 'file';
  }

  public function getApplicationTransactionType() {
    return PhabricatorFilePHIDTypeFile::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorFileTransactionComment();
  }

}
