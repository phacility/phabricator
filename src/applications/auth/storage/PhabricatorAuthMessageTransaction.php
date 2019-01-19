<?php

final class PhabricatorAuthMessageTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'auth';
  }

  public function getApplicationTransactionType() {
    return PhabricatorAuthMessagePHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorAuthMessageTransactionType';
  }

}
