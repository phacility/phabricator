<?php

final class PhabricatorAuthContactNumberTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorAuthContactNumberTransaction();
  }

}
