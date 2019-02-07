<?php

final class PhabricatorRepositoryTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'repository';
  }

  public function getApplicationTransactionType() {
    return PhabricatorRepositoryRepositoryPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorRepositoryTransactionType';
  }

}
