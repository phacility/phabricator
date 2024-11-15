<?php

final class PhabricatorRoleTriggerTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'role';
  }

  public function getApplicationTransactionType() {
    return PhabricatorRoleTriggerPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorRoleTriggerTransactionType';
  }

}
