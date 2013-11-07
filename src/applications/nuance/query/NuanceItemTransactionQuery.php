<?php

final class NuanceItemTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new NuanceItemTransaction();
  }

}
