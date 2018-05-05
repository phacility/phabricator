<?php

final class PhabricatorRepositoryIdentityTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationTransactionType() {
    return PhabricatorRepositoryIdentityPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorRepositoryIdentityTransactionType';
  }

  public function getApplicationName() {
    return 'repository';
  }

}
