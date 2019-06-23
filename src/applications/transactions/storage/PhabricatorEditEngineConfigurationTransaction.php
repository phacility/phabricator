<?php

final class PhabricatorEditEngineConfigurationTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'search';
  }

  public function getApplicationTransactionType() {
    return PhabricatorEditEngineConfigurationPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorEditEngineTransactionType';
  }

}
