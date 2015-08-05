<?php

final class PhabricatorPhurlURLTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new PhabricatorPhurlURLTransaction();
  }

}
