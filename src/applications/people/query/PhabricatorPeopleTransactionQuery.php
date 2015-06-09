<?php

final class PhabricatorPeopleTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorUserTransaction();
  }

}
