<?php

final class AlmanacNamespaceTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new AlmanacNamespaceTransaction();
  }

}
