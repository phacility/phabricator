<?php

final class PhabricatorRepositoryTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'repository';
  }

  public function getApplicationTransactionType() {
    return PhabricatorRepositoryRepositoryPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorRepositoryTransactionType';
  }

}
