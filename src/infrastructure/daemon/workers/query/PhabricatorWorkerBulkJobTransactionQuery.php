<?php

final class PhabricatorWorkerBulkJobTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorWorkerBulkJobTransaction();
  }

}
