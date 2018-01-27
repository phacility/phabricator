<?php

final class PhabricatorAuthPasswordTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorAuthPasswordTransaction();
  }

}
