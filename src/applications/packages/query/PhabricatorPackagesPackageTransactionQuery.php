<?php

final class PhabricatorPackagesPackageTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorPackagesPackageTransaction();
  }

}
