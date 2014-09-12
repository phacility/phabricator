<?php

final class FundBackerTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new FundBackerTransaction();
  }

}
