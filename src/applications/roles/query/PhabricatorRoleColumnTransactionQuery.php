<?php

final class PhabricatorRoleColumnTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorRoleColumnTransaction();
  }

}
