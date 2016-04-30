<?php

final class PhabricatorRepositoryURITransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorRepositoryURITransaction();
  }

}
