<?php

final class PhabricatorRoleTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorRoleTransaction();
  }

}
