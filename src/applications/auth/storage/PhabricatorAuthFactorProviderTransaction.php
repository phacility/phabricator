<?php

final class PhabricatorAuthFactorProviderTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'auth';
  }

  public function getApplicationTransactionType() {
    return PhabricatorAuthAuthFactorProviderPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorAuthFactorProviderTransactionType';
  }

}
