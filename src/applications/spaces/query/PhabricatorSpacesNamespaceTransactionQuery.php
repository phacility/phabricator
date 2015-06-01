<?php

final class PhabricatorSpacesNamespaceTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorSpacesNamespaceTransaction();
  }

}
