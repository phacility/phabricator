<?php

final class PhabricatorProjectTriggerTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'project';
  }

  public function getApplicationTransactionType() {
    return PhabricatorProjectTriggerPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorProjectTriggerTransactionType';
  }

}
