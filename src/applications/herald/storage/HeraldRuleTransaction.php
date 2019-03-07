<?php

final class HeraldRuleTransaction
  extends PhabricatorModularTransaction {

  const TYPE_EDIT = 'herald:edit';

  public function getApplicationName() {
    return 'herald';
  }

  public function getApplicationTransactionType() {
    return HeraldRulePHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'HeraldRuleTransactionType';
  }

}
