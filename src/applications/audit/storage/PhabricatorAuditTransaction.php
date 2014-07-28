<?php

final class PhabricatorAuditTransaction
  extends PhabricatorApplicationTransaction {

  public function getApplicationName() {
    return 'audit';
  }

  public function getApplicationTransactionType() {
    return PhabricatorRepositoryCommitPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorAuditTransactionComment();
  }

}
