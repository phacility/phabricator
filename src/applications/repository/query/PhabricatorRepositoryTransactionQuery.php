<?php

final class PhabricatorRepositoryTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorRepositoryTransaction();
  }

}
