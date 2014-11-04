<?php

final class PhrictionTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhrictionTransaction();
  }

}
