<?php

final class PhabricatorRepositoryIdentityTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorRepositoryIdentityTransaction();
  }

}
