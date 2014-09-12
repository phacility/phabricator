<?php

final class FundInitiativeTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new FundInitiativeTransaction();
  }

}
