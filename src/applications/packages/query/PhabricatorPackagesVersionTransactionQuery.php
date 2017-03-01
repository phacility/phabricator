<?php

final class PhabricatorPackagesVersionTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorPackagesVersionTransaction();
  }

}
