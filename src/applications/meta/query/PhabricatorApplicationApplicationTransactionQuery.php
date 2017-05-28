<?php

final class PhabricatorApplicationApplicationTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorApplicationApplicationTransaction();
  }

}
