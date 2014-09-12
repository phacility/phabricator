<?php

final class FundBackingTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new FundBackingTransaction();
  }

}
