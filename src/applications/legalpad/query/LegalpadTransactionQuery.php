<?php

final class LegalpadTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new LegalpadTransaction();
  }

}
