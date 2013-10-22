<?php

final class PhabricatorProjectTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorProjectTransaction();
  }

}
