<?php

final class NuanceRequestorTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new NuanceRequestorTransaction();
  }

}
