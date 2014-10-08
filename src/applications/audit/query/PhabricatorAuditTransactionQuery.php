<?php

final class PhabricatorAuditTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorAuditTransaction();
  }

}
