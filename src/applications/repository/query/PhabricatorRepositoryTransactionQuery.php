<?php

final class PhabricatorRepositoryTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  protected function getTemplateApplicationTransaction() {
    return new PhabricatorRepositoryTransaction();
  }

}
