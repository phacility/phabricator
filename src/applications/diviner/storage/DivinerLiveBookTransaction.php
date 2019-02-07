<?php

final class DivinerLiveBookTransaction
  extends PhabricatorApplicationTransaction {

  public function getApplicationName() {
    return 'diviner';
  }

  public function getApplicationTransactionType() {
    return DivinerBookPHIDType::TYPECONST;
  }

}
