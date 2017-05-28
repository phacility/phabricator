<?php

final class ConpherenceTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'conpherence';
  }

  public function getApplicationTransactionType() {
    return PhabricatorConpherenceThreadPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new ConpherenceTransactionComment();
  }

  public function getBaseTransactionClass() {
    return 'ConpherenceThreadTransactionType';
  }

}
