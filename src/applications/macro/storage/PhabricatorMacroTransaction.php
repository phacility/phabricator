<?php

final class PhabricatorMacroTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'file';
  }

  public function getTableName() {
    return 'macro_transaction';
  }

  public function getApplicationTransactionType() {
    return PhabricatorMacroMacroPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorMacroTransactionComment();
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorMacroTransactionType';
  }


}
