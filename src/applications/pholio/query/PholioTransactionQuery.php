<?php

final class PholioTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PholioTransaction();
  }

}
