<?php

final class PhabricatorAuthPasswordTransaction
  extends PhabricatorApplicationTransaction {

  public function getApplicationName() {
    return 'auth';
  }

  public function getApplicationTransactionType() {
    return PhabricatorAuthPasswordPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorAuthPasswordTransactionType';
  }
}
